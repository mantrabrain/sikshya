<?php

/**
 * JWT auth endpoints for mobile / external clients.
 *
 * @package Sikshya\Api
 */

namespace Sikshya\Api;

use Sikshya\Core\Plugin;
use Sikshya\Frontend\Site\CartStorage;
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

        // Browser (cookie-based) auth for the public checkout page.
        register_rest_route($namespace, '/auth/web-login', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'webLogin'],
                'permission_callback' => [$this, 'requireRestNonce'],
            ],
        ]);

        register_rest_route($namespace, '/auth/web-register', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'webRegister'],
                'permission_callback' => [$this, 'requireRestNonce'],
            ],
        ]);
    }

    /**
     * Require the WP REST nonce header for CSRF protection (works for guests too).
     *
     * @return bool|\WP_Error
     */
    public function requireRestNonce(WP_REST_Request $request)
    {
        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '') {
            $nonce = (string) $request->get_param('_wpnonce');
        }

        if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'sikshya_forbidden',
                __('Invalid request.', 'sikshya'),
                ['status' => 403]
            );
        }

        return true;
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

    public function webLogin(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $username = sanitize_text_field($params['username'] ?? '');
        $password = (string) ($params['password'] ?? '');

        if ($username === '' || $password === '') {
            return new WP_REST_Response(
                ['success' => false, 'message' => __('Email and password required.', 'sikshya')],
                400
            );
        }

        $user = wp_signon(
            [
                'user_login' => $username,
                'user_password' => $password,
                'remember' => true,
            ],
            is_ssl()
        );

        if (is_wp_error($user)) {
            return new WP_REST_Response(
                ['success' => false, 'message' => $user->get_error_message()],
                401
            );
        }

        wp_set_current_user((int) $user->ID);
        wp_set_auth_cookie((int) $user->ID, true, is_ssl());

        return new WP_REST_Response(
            [
                'success' => true,
                'user' => [
                    'id' => (int) $user->ID,
                    'display_name' => (string) $user->display_name,
                    'email' => (string) $user->user_email,
                ],
            ],
            200
        );
    }

    public function webRegister(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $email = sanitize_email($params['email'] ?? '');
        $password = (string) ($params['password'] ?? '');
        $display_name = sanitize_text_field($params['display_name'] ?? '');

        if ($email === '' || !is_email($email) || $password === '') {
            return new WP_REST_Response(
                ['success' => false, 'message' => __('Valid email and password required.', 'sikshya')],
                400
            );
        }

        if (email_exists($email)) {
            return new WP_REST_Response(
                ['success' => false, 'message' => __('An account with this email already exists. Please sign in.', 'sikshya')],
                409
            );
        }

        $base = sanitize_user((string) preg_replace('/@.*/', '', $email), true);
        if ($base === '') {
            $base = 'user';
        }
        $username = $base;
        $i = 1;
        while (username_exists($username)) {
            $i++;
            $username = $base . $i;
            if ($i > 50) {
                $username = $base . wp_rand(1000, 999999);
                break;
            }
        }

        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            return new WP_REST_Response(
                ['success' => false, 'message' => $user_id->get_error_message()],
                400
            );
        }

        if ($display_name !== '') {
            wp_update_user(['ID' => (int) $user_id, 'display_name' => $display_name]);
        }

        wp_set_current_user((int) $user_id);
        wp_set_auth_cookie((int) $user_id, true, is_ssl());
        CartStorage::adoptGuestCartForUser((int) $user_id);
        $user = get_userdata((int) $user_id);

        return new WP_REST_Response(
            [
                'success' => true,
                'user' => [
                    'id' => (int) $user_id,
                    'display_name' => $user ? (string) $user->display_name : '',
                    'email' => $user ? (string) $user->user_email : $email,
                ],
            ],
            200
        );
    }
}
