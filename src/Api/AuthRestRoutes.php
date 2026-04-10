<?php

/**
 * JWT auth endpoints for mobile / external clients.
 *
 * @package Sikshya\Api
 */

namespace Sikshya\Api;

use Sikshya\Core\Plugin;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class AuthRestRoutes
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/auth/login', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'login'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function login(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $username = sanitize_text_field($params['username'] ?? '');
        $password = $params['password'] ?? '';

        if ($username === '' || $password === '') {
            return new WP_REST_Response(
                ['success' => false, 'message' => __('Username and password required', 'sikshya')],
                400
            );
        }

        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            return new WP_REST_Response(
                ['success' => false, 'message' => $user->get_error_message()],
                401
            );
        }

        $jwt = $this->plugin->getService('jwtAuth');
        if (!$jwt instanceof JwtAuthService) {
            return new WP_REST_Response(['success' => false, 'message' => 'JWT unavailable'], 500);
        }

        $token = $jwt->issueToken((int) $user->ID);

        return new WP_REST_Response(
            [
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->ID,
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                    'roles' => $user->roles,
                ],
            ],
            200
        );
    }
}
