<?php

namespace Sikshya\Security;

/**
 * Transient-based brute-force protection for the Sikshya auth endpoints.
 *
 * `/auth/login` (JWT issuance) and `/auth/web-login` (cookie session) both
 * call `wp_authenticate` / `wp_signon` directly, bypassing the protections
 * WordPress' `wp-login.php` form normally enjoys (no CAPTCHA hook, no
 * built-in throttling, no `wp_attempted_logins` analog). Without this
 * limiter, an attacker can credential-stuff at line-rate over HTTPS.
 *
 * Design:
 *   - Bucket key is `sikshya_login_fail_<sha1(ip|lower(username))>` — same
 *     IP attacking different usernames spawns separate buckets, which is
 *     intentional so one rate-limited brute-force can't lock out every
 *     other learner's login.
 *   - Threshold: 5 failures in 15 minutes (defaults; both tunable via
 *     filters below).
 *   - Successful login wipes the bucket so a legitimate user mistyping
 *     their password 3× before getting it right isn't punished afterward.
 *   - Username is lowercased for keying so an attacker can't sidestep the
 *     bucket by varying case (`Admin` vs `admin`).
 *
 * Filters:
 *   - `sikshya_login_max_failed_attempts` (int, default 5)
 *   - `sikshya_login_lockout_seconds` (int, default 900)
 *
 * @package Sikshya\Security
 */
final class LoginRateLimiter
{
    private const DEFAULT_MAX_ATTEMPTS = 5;
    private const DEFAULT_LOCKOUT_SECONDS = 900; // 15 minutes

    public static function maxAttempts(): int
    {
        $n = (int) apply_filters('sikshya_login_max_failed_attempts', self::DEFAULT_MAX_ATTEMPTS);
        return $n > 0 ? $n : self::DEFAULT_MAX_ATTEMPTS;
    }

    public static function lockoutSeconds(): int
    {
        $n = (int) apply_filters('sikshya_login_lockout_seconds', self::DEFAULT_LOCKOUT_SECONDS);
        return $n > 0 ? $n : self::DEFAULT_LOCKOUT_SECONDS;
    }

    public static function isBlocked(string $ip, string $username): bool
    {
        $count = (int) get_transient(self::bucketKey($ip, $username));
        return $count >= self::maxAttempts();
    }

    /**
     * Increment the failure counter and (re)set its TTL so the lockout window
     * always extends from the most recent failed attempt — i.e., a sustained
     * brute-force stays locked rather than expiring mid-attack.
     */
    public static function recordFailure(string $ip, string $username): void
    {
        $key = self::bucketKey($ip, $username);
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, self::lockoutSeconds());
    }

    /**
     * Wipe the bucket — called on a successful authentication so legitimate
     * users aren't penalised for prior typos.
     */
    public static function clear(string $ip, string $username): void
    {
        delete_transient(self::bucketKey($ip, $username));
    }

    /**
     * Source of truth for the client IP used in rate-limit keys. We
     * intentionally read REMOTE_ADDR ONLY — `X-Forwarded-For` /
     * `X-Real-IP` / `HTTP_CLIENT_IP` are attacker-controllable on hosts
     * that aren't actually behind a reverse proxy, so trusting them here
     * would let an attacker bypass the limit by varying the header.
     * Sites genuinely behind Cloudflare / a reverse proxy should resolve
     * the real IP at the web-server layer (`set_real_ip_from` for nginx)
     * so REMOTE_ADDR is already correct by the time PHP runs.
     */
    public static function clientIp(): string
    {
        $raw = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        return filter_var($raw, FILTER_VALIDATE_IP) ? $raw : '0.0.0.0';
    }

    private static function bucketKey(string $ip, string $username): string
    {
        // Lowercase username so `Admin` and `admin` share a bucket — same
        // account, same bucket.
        return 'sikshya_login_fail_' . sha1($ip . '|' . strtolower($username));
    }
}
