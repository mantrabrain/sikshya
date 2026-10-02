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

        \nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($file_path));
        // `inline` for images / PDFs renders in-browser; we use `attachment`
        // so any leaked URL surface forces a download (safer default).
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        header('X-Content-Type-Options: nosniff');
        // Don't leak the file path; just stream the bytes.
        readfile($file_path);
        exit;
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
