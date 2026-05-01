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
use Sikshya\Services\Settings;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class JwtAuthService
{
    private const OPTION_KEY = 'sikshya_jwt_secret';

    private string $secret;

    public function __construct()
    {
        $secret = Settings::getRaw(self::OPTION_KEY, '');
        if (!is_string($secret) || $secret === '') {
            // Plugins load before pluggable.php, so wp_generate_password() is not available here.
            $secret = bin2hex(random_bytes(32));
            Settings::setRaw(self::OPTION_KEY, $secret, false);
        }
        $this->secret = $secret;
    }

    public function issueToken(int $user_id, int $ttl_seconds = 604800): string
    {
        $now = time();
        $payload = [
            'iss' => \home_url('/'),
            'sub' => (string) $user_id,
            'iat' => $now,
            'exp' => $now + $ttl_seconds,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * @return int|\WP_Error User ID
     */
    public function validateToken(string $jwt)
    {
        try {
            $decoded = JWT::decode($jwt, new Key($this->secret, 'HS256'));
            $sub = $decoded->sub ?? '';
            $uid = \absint($sub);
            if ($uid <= 0) {
                return new \WP_Error('jwt_invalid', \__('Invalid token subject', 'sikshya'));
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
