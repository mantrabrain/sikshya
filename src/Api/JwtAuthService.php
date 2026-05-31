<?php

/**
 * HS256 JWT for mobile / external API clients.
 *
 * Uses the `firebase/php-jwt` Composer package (namespace Firebase\JWT — not Google Firebase).
 *
 * @package Sikshya\Api
 */

namespace Sikshya\Api;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Sikshya\Security\SecretVault;
use Sikshya\Services\Settings;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class JwtAuthService
{
    private const OPTION_KEY = 'sikshya_jwt_secret';

    /**
     * User-meta key holding the user's current "token generation". Every
     * issued JWT carries this value in the `tv` claim; bumping the meta
     * value (via {@see self::revokeAllTokensForUser()} or the password-reset
     * hook in {@see self::registerRevocationHooks()}) instantly invalidates
     * every outstanding token for that user without needing a per-token
     * blocklist table. "Log out everywhere" semantics.
     */
    private const TOKEN_VERSION_META_KEY = 'sikshya_jwt_token_version';

    private string $secret;

    public function __construct()
    {
        $stored = Settings::getRaw(self::OPTION_KEY, '');
        if (!is_string($stored)) {
            $stored = '';
        }

        // Empty store → mint and persist (one-time bootstrap). Encrypt on
        // write so the freshly-minted secret never lives in `wp_options` as
        // plaintext.
        if ($stored === '') {
            $secret = bin2hex(random_bytes(32));
            Settings::setRaw(self::OPTION_KEY, SecretVault::encrypt($secret), false);
            $this->secret = $secret;
            return;
        }

        // Encrypted value at rest → decrypt and use; do NOT rewrite the store
        // on transient decryption failures (a wrong derived key would cause
        // the persistent fail-and-mint loop to invalidate every issued JWT on
        // every request). Fall back to the raw stored value so existing JWTs
        // verify at least until an admin resets the secret manually.
        if (SecretVault::isEncrypted($stored)) {
            $decrypted = SecretVault::decrypt($stored);
            $this->secret = $decrypted !== '' ? $decrypted : $stored;
            return;
        }

        // Legacy plaintext on disk → keep using it for verification, then
        // best-effort upgrade to encrypted-at-rest. Skip the write if the
        // encryption layer isn't available (very old hosting) or returns the
        // input unchanged.
        $this->secret = $stored;
        $encrypted = SecretVault::encrypt($stored);
        if ($encrypted !== $stored && SecretVault::isEncrypted($encrypted)) {
            Settings::setRaw(self::OPTION_KEY, $encrypted, false);
        }
    }

    /**
     * Issue a signed JWT for a learner / mobile client.
     *
     * Default TTL is 24h — short enough that a stolen / leaked token has
     * limited shelf life, long enough that mobile clients don't need a refresh
     * round-trip for every session. Callers that need a longer-lived service
     * token can pass a higher ttl explicitly OR override via the
     * `sikshya_jwt_default_ttl_seconds` filter.
     */
    public function issueToken(int $user_id, int $ttl_seconds = 0): string
    {
        if ($ttl_seconds <= 0) {
            $default = 24 * HOUR_IN_SECONDS;
            $ttl_seconds = (int) \apply_filters('sikshya_jwt_default_ttl_seconds', $default);
            if ($ttl_seconds <= 0) {
                $ttl_seconds = $default;
            }
        }
        $now = time();
        $payload = [
            'iss' => \home_url('/'),
            'sub' => (string) $user_id,
            'iat' => $now,
            'exp' => $now + $ttl_seconds,
            // Token version: pinned to the user's current generation. If the
            // user later hits `/auth/logout` or their password is reset, the
            // server bumps the meta and every previously-issued token fails
            // the `tv` check in `validateToken()`. No revocation DB table
            // needed — just a single user-meta lookup per request.
            'tv' => self::currentTokenVersion($user_id),
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * Read the user's current token generation. Stored as an int; absent =
     * generation 0 (the implicit baseline for users who pre-date this code).
     */
    public static function currentTokenVersion(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }
        $raw = \get_user_meta($user_id, self::TOKEN_VERSION_META_KEY, true);
        return is_numeric($raw) ? (int) $raw : 0;
    }

    /**
     * Invalidate every outstanding JWT for a user. Returns the new version
     * so a caller can issue a fresh token at the bumped generation if they
     * want "log out everywhere AND re-login on this device" semantics.
     */
    public static function revokeAllTokensForUser(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }
        $next = self::currentTokenVersion($user_id) + 1;
        \update_user_meta($user_id, self::TOKEN_VERSION_META_KEY, $next);
        return $next;
    }

    /**
     * Hook listeners that auto-revoke on security-sensitive events. Wired
     * once from {@see Plugin::onInit()}. Currently:
     *
     *   - `password_reset` and `profile_update` (when the password actually
     *     changed) bump the version so an attacker who's compromised an
     *     existing token can't keep authenticating after the rightful user
     *     resets their password.
     */
    public static function registerRevocationHooks(): void
    {
        \add_action('password_reset', static function ($user): void {
            if (\is_object($user) && isset($user->ID)) {
                self::revokeAllTokensForUser((int) $user->ID);
            }
        });
        \add_action('profile_update', static function (int $user_id, $old_user_data): void {
            // Only revoke when the password actually changed. `profile_update`
            // fires on every wp_update_user() — name edit, email change,
            // bio update — and we don't want to nuke active sessions on
            // every keystroke in the profile screen.
            if ($user_id <= 0 || !is_object($old_user_data)) {
                return;
            }
            $old_hash = isset($old_user_data->user_pass) ? (string) $old_user_data->user_pass : '';
            $new_user = \get_userdata($user_id);
            $new_hash = ($new_user && isset($new_user->user_pass)) ? (string) $new_user->user_pass : '';
            if ($old_hash !== '' && $new_hash !== '' && $old_hash !== $new_hash) {
                self::revokeAllTokensForUser($user_id);
            }
        }, 10, 2);
    }

    /**
     * @return int|\WP_Error User ID
     */
    public function validateToken(string $jwt)
    {
        try {
            $decoded = JWT::decode($jwt, new Key($this->secret, 'HS256'));

            // Issuer pinning: reject tokens minted for a different site that
            // happen to share this site's secret (e.g., a clone / staging
            // environment that was never rotated). Firebase\JWT doesn't
            // enforce iss by default — we must compare explicitly.
            $token_iss = isset($decoded->iss) ? (string) $decoded->iss : '';
            $expected_iss = \home_url('/');
            if ($token_iss !== '' && $expected_iss !== '' && $token_iss !== $expected_iss) {
                return new \WP_Error('jwt_invalid', \__('Token issuer mismatch.', 'sikshya'));
            }

            $sub = $decoded->sub ?? '';
            $uid = \absint($sub);
            if ($uid <= 0) {
                return new \WP_Error('jwt_invalid', \__('Invalid token subject', 'sikshya'));
            }

            // User-existence check: a valid signature alone doesn't mean the
            // subject is still a real account. Without this, deleted /
            // banned users keep authenticating until their token expires,
            // and downstream `wp_set_current_user()` silently produces a
            // phantom user with no caps but a non-zero ID — confusing every
            // subsequent capability/IDOR check that asks "who is this".
            $user = \get_userdata($uid);
            if (!$user || empty($user->ID)) {
                return new \WP_Error('jwt_invalid', \__('Token subject no longer exists.', 'sikshya'));
            }

            // Revocation check via per-user token generation. If the user
            // hit `/auth/logout`, reset their password, or an admin force-
            // logged them out, their `sikshya_jwt_token_version` meta was
            // bumped — every token issued before the bump now fails this
            // comparison. Tokens without a `tv` claim (issued before this
            // code shipped) default to 0, which matches any user who has
            // never been revoked (meta absent → 0). Once the user is
            // revoked, the meta moves to 1, 2, … and pre-revocation tokens
            // can't catch up.
            $token_tv = isset($decoded->tv) ? (int) $decoded->tv : 0;
            $current_tv = self::currentTokenVersion($uid);
            if ($token_tv !== $current_tv) {
                return new \WP_Error('jwt_invalid', \__('Token has been revoked. Please log in again.', 'sikshya'));
            }

            return $uid;
        } catch (\Throwable $e) {
            return new \WP_Error('jwt_invalid', $e->getMessage());
        }
    }

    /**
     * Bearer token from request headers.
     */
    public static function bearerFromRequest(\WP_REST_Request $request): string
    {
        $h = $request->get_header('authorization');
        if (!is_string($h) || $h === '') {
            return '';
        }
        if (preg_match('/Bearer\s+(\S+)/i', $h, $m)) {
            return $m[1];
        }

        return '';
    }
}
