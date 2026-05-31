<?php

declare(strict_types=1);

namespace Sikshya\Security;

/**
 * Transient-based throttling for the `/auth/web-register` and
 * `/auth/register` endpoints.
 *
 * Without this, a script can hammer `/auth/web-register` to:
 *   - enumerate which emails already exist (the endpoint distinguishes
 *     `email_exists` vs `created` cleanly);
 *   - spam-create accounts (each fresh email mints a user row).
 *
 * Bucket key is the client IP only — varying the email doesn't dodge the
 * limit. Threshold defaults to 10 successful or attempted registrations
 * per IP per hour, tunable via `sikshya_register_max_attempts` and
 * `sikshya_register_lockout_seconds`. Reads {@see clientIp()} from
 * `REMOTE_ADDR` only (see {@see LoginRateLimiter} for the rationale on
 * not trusting `X-Forwarded-For`).
 *
 * @package Sikshya\Security
 */
final class RegistrationRateLimiter
{
    private const DEFAULT_MAX_ATTEMPTS = 10;
    private const DEFAULT_LOCKOUT_SECONDS = 3600;

    public static function maxAttempts(): int
    {
        $n = (int) apply_filters('sikshya_register_max_attempts', self::DEFAULT_MAX_ATTEMPTS);
        return $n > 0 ? $n : self::DEFAULT_MAX_ATTEMPTS;
    }

    public static function lockoutSeconds(): int
    {
        $n = (int) apply_filters('sikshya_register_lockout_seconds', self::DEFAULT_LOCKOUT_SECONDS);
        return $n > 0 ? $n : self::DEFAULT_LOCKOUT_SECONDS;
    }

    public static function isBlocked(string $ip): bool
    {
        $count = (int) get_transient(self::bucketKey($ip));
        return $count >= self::maxAttempts();
    }

    /**
     * Increment the counter. Refresh the TTL so a sustained spam burst
     * stays locked for `lockoutSeconds` past the most recent hit.
     */
    public static function recordAttempt(string $ip): void
    {
        $key = self::bucketKey($ip);
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, self::lockoutSeconds());
    }

    public static function clientIp(): string
    {
        return LoginRateLimiter::clientIp();
    }

    private static function bucketKey(string $ip): string
    {
        return 'sikshya_register_attempts_' . sha1($ip);
    }
}
