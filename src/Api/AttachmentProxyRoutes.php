<?php

namespace Sikshya\Api;

use Sikshya\Core\Plugin;
use Sikshya\Security\AttachmentTokenService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * REST proxy that streams attachment files through Sikshya rather than
 * exposing the raw `wp-content/uploads/` URL.
 *
 * Route: GET /wp-json/sikshya/v1/file/<token>
 *
 * Authorisation model:
 *   1. The token itself is HMAC-signed by {@see AttachmentTokenService} —
 *      proves the URL was minted by us and hasn't expired.
 *   2. The requester must be authenticated (cookie session OR JWT) AND
 *      their resolved user_id must equal the `uid` claim baked into the
 *      token. Even if a learner shares the URL with a friend, the friend's
 *      authenticated identity won't match and they get 403.
 *
 * @package Sikshya\Api
 */
final class AttachmentProxyRoutes
{
    /**
     * MIME types the proxy will serve with `Content-Disposition: inline`, and
     * only when the signed token asks for it.
     *
     * This list is the whole security boundary for inline delivery. Serving an
     * uploaded file for *display* from our own origin means the browser parses
     * it in our security context — an `.html` or `.svg` attachment rendered
     * that way is stored XSS against every logged-in viewer, which is exactly
     * why WordPress's own uploads are served with `nosniff` and why this route
     * defaulted to forcing a download in the first place. A PDF is parsed by
     * the browser's PDF viewer in its own sandbox, with no access to our DOM
     * or cookies, so it can be rendered without handing over the origin.
     *
     * Anything not named here is downloaded even with a valid inline token.
     */
    private const INLINE_SAFE_MIME = [
        'application/pdf',
    ];

    /** Bytes per fread() when streaming a byte range. */
    private const CHUNK_BYTES = 262144;

    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        register_rest_route('sikshya/v1', '/file/(?P<token>[^/]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'stream'],
                // Auth is enforced inside `stream()` — we need to be able to
                // return signature errors with explicit codes rather than a
                // generic permission-callback 401.
                'permission_callback' => '__return_true',
                'args' => [
                    'token' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Stream the file. Exits the process directly after streaming — REST's
     * usual JSON response shape is bypassed because we're emitting binary.
     */
    public function stream(WP_REST_Request $request): void
    {
        $token = (string) $request->get_param('token');
        $verified = AttachmentTokenService::verify($token);
        if (\is_wp_error($verified)) {
            $this->bail(
                $verified->get_error_code() === 'expired' ? 410 : 403,
                $verified->get_error_message()
            );
        }

        $current_uid = $this->resolveRequesterId($request);

        if ($current_uid <= 0) {
            $this->bail(401, \__('Authentication required to download this file.', 'sikshya'));
        }

        // Identity check: the token was minted for a specific user; only
        // that user can use it. Constant-time integer comparison is fine
        // (no side-channel risk on int compare).
        if ($current_uid !== (int) $verified['user_id']) {
            $this->bail(403, \__('This download link was issued to a different account.', 'sikshya'));
        }

        $attachment_id = (int) $verified['attachment_id'];
        $file_path = \get_attached_file($attachment_id);
        if (!$file_path || !is_string($file_path) || !file_exists($file_path)) {
            $this->bail(404, \__('Attachment not found.', 'sikshya'));
        }

        // Best-effort MIME + safe filename.
        $mime = (string) \get_post_mime_type($attachment_id);
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
        $download_name = \sanitize_file_name(basename($file_path));
        if ($download_name === '') {
            $download_name = 'download';
        }

        $disposition = $this->dispositionFor($mime, (string) ($verified['disposition'] ?? ''));

        $size = (int) filesize($file_path);

        \nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . $download_name . '"');
        header('X-Content-Type-Options: nosniff');
        // Advertised unconditionally: a client that knows it can ask for a
        // range will, and one that doesn't is unaffected.
        header('Accept-Ranges: bytes');

        $range = $this->requestedRange($size);

        if ($range === null) {
            header('Content-Length: ' . (string) $size);
            // Don't leak the file path; just stream the bytes.
            readfile($file_path);
            exit;
        }

        if ($range === false) {
            // RFC 9110 §15.5.17: an unsatisfiable range gets 416 plus the real
            // length, so the client can retry with a range that exists.
            \status_header(416);
            header('Content-Range: bytes */' . (string) $size);
            header('Content-Length: 0');
            exit;
        }

        [$start, $end] = $range;
        \status_header(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . (string) $size);
        header('Content-Length: ' . (string) ($end - $start + 1));
        $this->streamRange($file_path, $start, $end);
        exit;
    }

    /**
     * Decide whether these bytes may be rendered or must be downloaded.
     *
     * `attachment` stays the default in both directions: a leaked URL forces a
     * download rather than rendering in our origin, and a type we do not
     * recognise is downloaded even when the token asked for display. Both
     * conditions have to hold — the signature proves *we* minted an inline
     * link, the allowlist proves the content cannot execute as markup — and
     * either one alone has been enough to turn an upload form into stored XSS
     * in other people's plugins.
     *
     * @param string $claim The token's verified disposition claim.
     */
    private function dispositionFor(string $mime, string $claim): string
    {
        if ($claim !== 'inline') {
            return 'attachment';
        }
        if (!in_array($mime, self::INLINE_SAFE_MIME, true)) {
            return 'attachment';
        }

        return 'inline';
    }

    /**
     * Parse `Range` into an inclusive `[start, end]` byte pair.
     *
     * Why this route needs ranges at all: a PDF lesson points a viewer at this
     * endpoint, and browser PDF viewers fetch linearised documents a range at a
     * time rather than pulling the whole file. Answering 200-with-everything to
     * a range request makes the viewer re-download a large document on every
     * page jump, and some builds simply give up and show a blank frame.
     *
     * @return array{0:int,1:int}|null|false Pair to serve, null for "no usable
     *                                       range header, send it all", or
     *                                       false for unsatisfiable.
     */
    private function requestedRange(int $size)
    {
        $header = isset($_SERVER['HTTP_RANGE']) ? (string) \wp_unslash($_SERVER['HTTP_RANGE']) : '';
        if ($header === '' || $size <= 0) {
            return null;
        }

        // Only `bytes` is defined, and only a single range is worth supporting:
        // a multipart/byteranges response buys nothing for a PDF viewer, so a
        // comma means "send the whole thing" rather than a wrong answer.
        if (stripos($header, 'bytes=') !== 0) {
            return null;
        }
        $spec = trim(substr($header, 6));
        if ($spec === '' || strpos($spec, ',') !== false) {
            return null;
        }

        $parts = explode('-', $spec, 2);
        if (count($parts) !== 2) {
            return null;
        }
        $rawStart = trim($parts[0]);
        $rawEnd = trim($parts[1]);

        if ($rawStart === '') {
            // Suffix form, `bytes=-500`: the last N bytes.
            if ($rawEnd === '' || !ctype_digit($rawEnd)) {
                return null;
            }
            $length = (int) $rawEnd;
            if ($length <= 0) {
                return false;
            }
            $start = $length >= $size ? 0 : $size - $length;

            return [$start, $size - 1];
        }

        if (!ctype_digit($rawStart)) {
            return null;
        }
        $start = (int) $rawStart;
        if ($start >= $size) {
            return false;
        }

        if ($rawEnd === '') {
            return [$start, $size - 1];
        }
        if (!ctype_digit($rawEnd)) {
            return null;
        }
        $end = (int) $rawEnd;
        if ($end < $start) {
            return false;
        }

        return [$start, min($end, $size - 1)];
    }

    /**
     * Copy `[start, end]` to the client in chunks.
     *
     * Chunked rather than one `fread` of the whole range: a learner asking for
     * a 40 MB slice should not become a 40 MB allocation in PHP.
     */
    private function streamRange(string $file_path, int $start, int $end): void
    {
        $handle = fopen($file_path, 'rb');
        if (!$handle) {
            return;
        }
        fseek($handle, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($handle)) {
            $chunk = fread($handle, (int) min(self::CHUNK_BYTES, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
            // Keep peak memory flat on a long range rather than buffering the
            // whole response.
            flush();
        }
        fclose($handle);
    }

    /**
     * Emit a plain-text error and exit. Avoids WP's JSON wrapping because
     * the client probably expected an octet-stream and would mishandle a
     * JSON body.
     */
    /**
     * Identify who is asking for the file.
     *
     * Three sources, in cost order: the resolved REST user, a Bearer JWT (mobile
     * clients), and finally the logged-in cookie read directly.
     *
     * That last step is what makes these links usable. A signed download URL is
     * built to be *clicked* — it goes in a Downloads list, and a click is a
     * top-level navigation carrying cookies but no nonce. WordPress's
     * `rest_cookie_check_errors()` deliberately calls `wp_set_current_user(0)`
     * for any REST request that presents no nonce, so `get_current_user_id()` is
     * 0 here even for a perfectly valid session, and every protected attachment
     * answered 401 the moment a learner clicked it rather than fetched it.
     *
     * Reading the cookie back is safe for this route specifically:
     *
     *   - It is a read-only GET that streams a file and changes no state, so the
     *     nonce is not protecting anything against CSRF. The worst a forged
     *     request achieves is making someone download their own file, and the
     *     response is not readable cross-origin.
     *   - Authority comes from the HMAC token, not from the session. The token
     *     names the attachment and the single user id allowed to use it, and the
     *     identity check below still has to pass, so a valid cookie alone opens
     *     nothing.
     *   - `wp_validate_auth_cookie()` is the same primitive WordPress uses to
     *     authenticate an ordinary page load, so this accepts exactly the
     *     sessions the rest of the site accepts.
     */
    private function resolveRequesterId(WP_REST_Request $request): int
    {
        $uid = (int) \get_current_user_id();
        if ($uid > 0) {
            return $uid;
        }

        $bearer = JwtAuthService::bearerFromRequest($request);
        if ($bearer !== '') {
            $jwt = $this->plugin->getService('jwtAuth');
            if ($jwt instanceof JwtAuthService) {
                $resolved = $jwt->validateToken($bearer);
                if (!\is_wp_error($resolved)) {
                    return (int) $resolved;
                }
            }
        }

        if (defined('LOGGED_IN_COOKIE') && !empty($_COOKIE[LOGGED_IN_COOKIE])) {
            $cookie = \wp_unslash($_COOKIE[LOGGED_IN_COOKIE]);
            if (is_string($cookie) && $cookie !== '') {
                $from_cookie = (int) \wp_validate_auth_cookie($cookie, 'logged_in');
                if ($from_cookie > 0) {
                    return $from_cookie;
                }
            }
        }

        return 0;
    }

    private function bail(int $http_status, string $message): void
    {
        \status_header($http_status);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo $message;
        exit;
    }
}
