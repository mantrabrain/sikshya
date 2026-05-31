<?php

declare(strict_types=1);

namespace Sikshya\Security;

/**
 * Envelope-encrypted secret storage.
 *
 * Wraps libsodium's `crypto_secretbox` so secrets at rest in `wp_options`
 * (JWT signing keys, OAuth provider secrets, addon API keys) can't be lifted
 * by a SQL read alone. The encryption key is derived from a site's existing
 * `wp-config.php` constants (`AUTH_KEY` + `SECURE_AUTH_KEY` + `NONCE_KEY` +
 * `DB_NAME`), so a stolen DB dump is not enough to decrypt — the attacker
 * also needs the config file.
 *
 * Ciphertext format: `enc:v1:<base64(nonce(24B) || ciphertext)>`. The prefix
 * lets callers detect already-encrypted values and transparently migrate
 * plaintext on first read after upgrade — no manual migration script.
 *
 * Falls back to the input as-is when libsodium isn't available or no key
 * material is configured (very old hosting), so the plugin keeps booting
 * instead of locking site owners out of their JWT-issuing endpoint.
 *
 * @package Sikshya\Security
 */
final class SecretVault
{
    public const CIPHERTEXT_PREFIX = 'enc:v1:';

    /** @var string|null Cached 32-byte symmetric key derived from wp-config constants. */
    private static ?string $cachedKey = null;

    /**
     * Encrypt a plaintext secret. Returns the input unchanged when libsodium
     * isn't available so callers don't need defensive fallbacks at every site.
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        if (!self::cryptoAvailable()) {
            return $plaintext;
        }
        $key = self::deriveKey();
        if ($key === '') {
            return $plaintext;
        }
        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ct = sodium_crypto_secretbox($plaintext, $nonce, $key);
            return self::CIPHERTEXT_PREFIX . base64_encode($nonce . $ct);
        } catch (\Throwable $e) {
            return $plaintext;
        }
    }

    /**
     * Decrypt a ciphertext produced by {@see encrypt()}.
     *
     * Returns the input as-is when it's NOT prefixed with `enc:v1:` (plaintext
     * legacy value) — the caller can then opt to re-encrypt + persist via
     * {@see encrypt()}. Returns an empty string on tamper / wrong-key.
     */
    public static function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (!self::isEncrypted($value)) {
            return $value;
        }
        if (!self::cryptoAvailable()) {
            return '';
        }
        $key = self::deriveKey();
        if ($key === '') {
            return '';
        }
        $raw = base64_decode(substr($value, strlen(self::CIPHERTEXT_PREFIX)), true);
        if (!is_string($raw) || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ct = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        try {
            $pt = sodium_crypto_secretbox_open($ct, $nonce, $key);
            return $pt === false ? '' : (string) $pt;
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function isEncrypted(string $value): bool
    {
        return strpos($value, self::CIPHERTEXT_PREFIX) === 0;
    }

    public static function cryptoAvailable(): bool
    {
        return function_exists('sodium_crypto_secretbox')
            && function_exists('sodium_crypto_secretbox_open')
            && function_exists('random_bytes')
            && defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES');
    }

    /**
     * Derive a 32-byte symmetric key from the WordPress secret constants.
     *
     * `AUTH_KEY` is always defined on a real install (wp_install sets the
     * other two by side-effect of the salt API); the additional inputs
     * shrink the chance of collision in shared-config scenarios. Returns
     * an empty string when no key material exists so callers can fall back
     * to plaintext rather than encrypting under a known-empty key.
     */
    private static function deriveKey(): string
    {
        if (self::$cachedKey !== null) {
            return self::$cachedKey;
        }
        $parts = [];
        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'NONCE_KEY', 'DB_NAME'] as $name) {
            if (defined($name)) {
                $v = constant($name);
                if (is_string($v) && $v !== '') {
                    $parts[] = $v;
                }
            }
        }
        if ($parts === []) {
            self::$cachedKey = '';
            return '';
        }
        $material = implode('|', $parts) . '|sikshya/v1';
        self::$cachedKey = hash('sha256', $material, true);
        return self::$cachedKey;
    }

    /** Test seam: clear the cached derived key. */
    public static function resetCacheForTesting(): void
    {
        self::$cachedKey = null;
    }
}
