<?php

namespace Sikshya\Api;

use Sikshya\Core\Plugin;
use Sikshya\Security\AttachmentTokenService;
use WP_REST_Request;
use WP_REST_Server;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

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

        // Resolve the requester. Try cookie session first (cheap), JWT
        // Bearer second (mobile clients).
        $current_uid = (int) \get_current_user_id();
        if ($current_uid <= 0) {
            $bearer = JwtAuthService::bearerFromRequest($request);
            if ($bearer !== '') {
                $jwt = $this->plugin->getService('jwtAuth');
                if ($jwt instanceof JwtAuthService) {
                    $resolved = $jwt->validateToken($bearer);
                    if (!\is_wp_error($resolved)) {
                        $current_uid = (int) $resolved;
                    }
                }
            }
        }

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
    private function bail(int $http_status, string $message): void
    {
        \status_header($http_status);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo $message;
        exit;
    }
}
