<?php

namespace Sikshya\Security;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * HMAC-signed download tokens for course/lesson attachments.
 *
 * Lesson attachments live under `wp-content/uploads/` which is world-readable
 * by default — anyone with the URL can stream the file regardless of
 * enrolment. The lesson template only renders URLs to enrolled learners,
 * but if a URL leaks (shared in chat, indexed by a search engine, captured
 * in browser history), unenrolled users can download paid course content.
 *
 * This service issues short-lived signed URLs that:
 *
 *   - Bind the attachment to a specific user_id at issue time. Even if the
 *     learner shares the URL, the proxy endpoint refuses to stream unless
 *     the requester's authenticated identity (cookie session or JWT) matches
 *     the token's uid claim. A different account presenting the URL → 403.
 *
 *   - Expire after a short window (default 1 hour). A URL captured in a
 *     screenshot or a Slack channel stops working an hour later.
 *
 *   - Don't require a DB table. The signature alone proves authenticity;
 *     the proxy just verifies HMAC + expiry + identity.
 *
 * Token format (URL-safe):
 *   `base64url(json({"att":<id>,"uid":<id>,"exp":<ts>})).hex(hmac_sha256)`
 *
 * @package Sikshya\Security
 */
final class AttachmentTokenService
{
    private const OPTION_KEY = 'sikshya_attachment_signing_secret';
    private const DEFAULT_TTL_SECONDS = 3600; // 1 hour

    /**
     * Generate a signed download URL for `attachment_id` that only `user_id`
     * can use. Returns the canonical raw URL (no signing) when:
     *
     *   - The `sikshya_protect_attachments` filter returns false (sites that
     *     handle auth at the reverse-proxy / CDN layer).
     *   - The attachment doesn't exist.
     *   - The signing secret can't be loaded.
     */
    public static function signedUrlFor(int $attachment_id, int $user_id, int $ttl_seconds = 0): string
    {
        if ($attachment_id <= 0) {
            return '';
        }

        // Opt-out filter for sites that already protect uploads/ at the
        // server / CDN layer. Default ON because the threat (URL leak →
        // paid-content download) is real.
        $protect = (bool) \apply_filters('sikshya_protect_attachments', true, $attachment_id, $user_id);
        if (!$protect || $user_id <= 0) {
            return (string) \wp_get_attachment_url($attachment_id);
        }

        if ($ttl_seconds <= 0) {
            $default = self::DEFAULT_TTL_SECONDS;
            $ttl_seconds = (int) \apply_filters('sikshya_attachment_token_ttl_seconds', $default);
            if ($ttl_seconds <= 0) {
                $ttl_seconds = $default;
            }
        }

        $payload = [
            'att' => $attachment_id,
            'uid' => $user_id,
            'exp' => time() + $ttl_seconds,
        ];
        $encoded = self::base64UrlEncode((string) \wp_json_encode($payload));
        $sig = \hash_hmac('sha256', $encoded, self::secret());
        $token = $encoded . '.' . $sig;

        return \rest_url('sikshya/v1/file/' . $token);
    }

    /**
     * Verify a token returned by the proxy route. Returns the decoded payload
     * on success or a WP_Error describing the failure mode.
     *
     * @return array{attachment_id: int, user_id: int}|\WP_Error
     */
    public static function verify(string $token)
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return new \WP_Error('bad_token', \__('Malformed download token.', 'sikshya'));
        }
        [$encoded, $sig] = $parts;
        if ($encoded === '' || $sig === '') {
            return new \WP_Error('bad_token', \__('Malformed download token.', 'sikshya'));
        }

        // Constant-time signature comparison — `==` here would leak signature
        // bytes via the response-time side channel under load.
        $expected = \hash_hmac('sha256', $encoded, self::secret());
        if (!\hash_equals($expected, $sig)) {
            return new \WP_Error('bad_token', \__('Invalid download token signature.', 'sikshya'));
        }

        $json = self::base64UrlDecode($encoded);
        if ($json === '') {
            return new \WP_Error('bad_token', \__('Could not decode download token.', 'sikshya'));
        }
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return new \WP_Error('bad_token', \__('Could not parse download token.', 'sikshya'));
        }

        $att = isset($payload['att']) ? (int) $payload['att'] : 0;
        $uid = isset($payload['uid']) ? (int) $payload['uid'] : 0;
        $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;

        if ($att <= 0 || $uid <= 0 || $exp <= 0) {
            return new \WP_Error('bad_token', \__('Download token missing required fields.', 'sikshya'));
        }
        if ($exp < time()) {
            return new \WP_Error('expired', \__('Download link has expired.', 'sikshya'));
        }

        return ['attachment_id' => $att, 'user_id' => $uid];
    }

    /**
     * Auto-generate + persist the HMAC secret on first use. 256-bit entropy
     * via `random_bytes(32)`. Same lazy-init pattern as the JWT secret —
     * plugins load before `pluggable.php`, so `wp_generate_password` isn't
     * always available.
     */
    private static function secret(): string
    {
        $raw = \get_option(self::OPTION_KEY, '');
        if (!is_string($raw) || $raw === '') {
            $raw = bin2hex(random_bytes(32));
            \update_option(self::OPTION_KEY, $raw, false);
        }
        return $raw;
    }

    /** URL-safe base64 (RFC 4648 §5): `+/` → `-_`, drop padding `=`. */
    private static function base64UrlEncode(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $s): string
    {
        $pad = strlen($s) % 4;
        if ($pad > 0) {
            $s .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($s, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }
}
