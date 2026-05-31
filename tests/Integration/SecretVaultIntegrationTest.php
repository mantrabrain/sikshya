<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Api\JwtAuthService;
use Sikshya\Security\SecretVault;
use Sikshya\Services\Settings;
use WP_UnitTestCase;

/**
 * Coverage for {@see SecretVault} envelope encryption and the JWT secret
 * migration in {@see JwtAuthService::__construct()}.
 *
 * @covers \Sikshya\Security\SecretVault
 * @covers \Sikshya\Api\JwtAuthService
 */
final class SecretVaultIntegrationTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        SecretVault::resetCacheForTesting();
        Settings::setRaw('sikshya_jwt_secret', '', false);
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        if (!SecretVault::cryptoAvailable()) {
            $this->markTestSkipped('libsodium not available on this PHP build');
        }
        $plain = 'super-secret-' . bin2hex(random_bytes(8));
        $ct = SecretVault::encrypt($plain);
        $this->assertNotSame($plain, $ct, 'ciphertext must differ from plaintext');
        $this->assertTrue(SecretVault::isEncrypted($ct), 'ciphertext must carry enc:v1: prefix');
        $this->assertSame($plain, SecretVault::decrypt($ct), 'round-trip must recover the original plaintext');
    }

    public function testDecryptReturnsPlaintextWhenNotPrefixed(): void
    {
        $value = 'legacy-plaintext-secret';
        $this->assertFalse(SecretVault::isEncrypted($value));
        $this->assertSame($value, SecretVault::decrypt($value), 'plaintext is returned as-is');
    }

    public function testTamperedCiphertextReturnsEmptyString(): void
    {
        if (!SecretVault::cryptoAvailable()) {
            $this->markTestSkipped('libsodium not available on this PHP build');
        }
        $ct = SecretVault::encrypt('original');
        // Flip a byte in the base64 payload to simulate tampering.
        $tampered = substr($ct, 0, -2) . 'AA';
        $this->assertSame('', SecretVault::decrypt($tampered));
    }

    public function testJwtServiceBootstrapEncryptsTheStoredSecret(): void
    {
        if (!SecretVault::cryptoAvailable()) {
            $this->markTestSkipped('libsodium not available on this PHP build');
        }
        // Force fresh bootstrap.
        Settings::setRaw('sikshya_jwt_secret', '', false);
        new JwtAuthService();
        $stored = (string) Settings::getRaw('sikshya_jwt_secret', '');
        $this->assertNotSame('', $stored, 'JWT bootstrap must persist a secret');
        $this->assertTrue(SecretVault::isEncrypted($stored), 'stored secret must be encrypted at rest');
        $this->assertNotSame('', SecretVault::decrypt($stored), 'stored secret must decrypt cleanly');
    }

    public function testJwtServiceMigratesLegacyPlaintextOnFirstLoad(): void
    {
        if (!SecretVault::cryptoAvailable()) {
            $this->markTestSkipped('libsodium not available on this PHP build');
        }
        $legacy = bin2hex(random_bytes(16));
        Settings::setRaw('sikshya_jwt_secret', $legacy, false);

        new JwtAuthService();
        $after = (string) Settings::getRaw('sikshya_jwt_secret', '');
        $this->assertNotSame($legacy, $after, 'plaintext value should be upgraded in-place');
        $this->assertTrue(SecretVault::isEncrypted($after), 'upgraded value carries the enc:v1: prefix');
        $this->assertSame($legacy, SecretVault::decrypt($after), 'upgrade preserves the original secret material');
    }
}
