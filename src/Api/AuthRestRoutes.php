<?php

/**
 * JWT auth endpoints for mobile / external clients.
 *
 * @package Sikshya\Api
 */

namespace Sikshya\Api;

use Sikshya\Core\Plugin;
use Sikshya\Frontend\Site\CartStorage;
use Sikshya\Security\RegistrationRateLimiter;
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

        // Logout: bumps the user's JWT token-version meta, instantly
        // invalidating every outstanding JWT for that user. Cookie-based
        // session is also cleared via wp_logout(). Requires either a valid
        // current JWT (Authorization: Bearer ...) OR a logged-in cookie
        // session + nonce — `webLogoutPermission` accepts either.
        register_rest_route($namespace, '/auth/logout', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'logout'],
                'permission_callback' => [$this, 'logoutPermission'],
            ],
        ]);
    }

    /**
     * Logout accepts either a valid JWT (mobile / API client) OR a logged-in
     * cookie session backed by the REST nonce (web client). Either signal is
     * sufficient — we're not granting any privileged action, we're just
     * confirming the requester owns the session they're asking to end.
     */
    public function logoutPermission(WP_REST_Request $request)
    {
        // Path 1: Bearer JWT — let `JwtAuthService::validateToken()` decide.
        $bearer = JwtAuthService::bearerFromRequest($request);
        if ($bearer !== '') {
            $jwt = $this->plugin->getService('jwtAuth');
            if ($jwt instanceof JwtAuthService) {
                $uid_or_err = $jwt->validateToken($bearer);
                if (!is_wp_error($uid_or_err) && (int) $uid_or_err > 0) {
                    return true;
                }
            }
        }
        // Path 2: cookie session + nonce.
        if (is_user_logged_in()) {
            return $this->requireRestNonce($request);
        }
        return new \WP_Error(
            'sikshya_forbidden',
            __('Authentication required.', 'sikshya'),
            ['status' => 401]
        );
    }

    public function logout(WP_REST_Request $request): WP_REST_Response
    {
        // Resolve the user from whichever auth path got us here.
        $uid = 0;
        $bearer = JwtAuthService::bearerFromRequest($request);
        if ($bearer !== '') {
            $jwt = $this->plugin->getService('jwtAuth');
            if ($jwt instanceof JwtAuthService) {
                $resolved = $jwt->validateToken($bearer);
                if (!is_wp_error($resolved)) {
                    $uid = (int) $resolved;
                }
            }
        }
        if ($uid <= 0 && is_user_logged_in()) {
            $uid = (int) get_current_user_id();
        }
        if ($uid <= 0) {
            return new WP_REST_Response(
                ['success' => false, 'message' => __('Not signed in.', 'sikshya')],
                401
            );
        }

        // Invalidate every outstanding JWT for this user by bumping their
        // token-version meta. The cookie session (if any) is also cleared.
        JwtAuthService::revokeAllTokensForUser($uid);
        wp_logout();

        return new WP_REST_Response(['success' => true], 200);
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

        // Brute-force / credential-stuffing protection: 5 fails in 15 min
        // (per-(IP, username)) blocks further attempts. Filters available
        // for sites that need to tune the policy — see LoginRateLimiter.
        $ip = \Sikshya\Security\LoginRateLimiter::clientIp();
        if (\Sikshya\Security\LoginRateLimiter::isBlocked($ip, $username)) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'code' => 'rate_limited',
                    'message' => __('Too many failed attempts. Please try again later.', 'sikshya'),
                ],
                429
            );
        }

        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            \Sikshya\Security\LoginRateLimiter::recordFailure($ip, $username);
            // Username enumeration defense: wp_authenticate returns distinct
            // error codes for "no such user" (`invalid_username`) vs "wrong
            // password" (`incorrect_password`). Surfacing either verbatim
            // lets an attacker probe which accounts exist on the site by
            // diffing the response. Collapse all credential-failure paths
            // into a single generic message + opaque code, while still
            // logging the real reason for admins via `error_log` when
            // WP_DEBUG is on. Empty-input cases were already short-circuited
            // above (HTTP 400) so they're not lumped in here.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'Sikshya /auth/login auth failure for "%s": %s',
                    is_string($username) ? $username : '',
                    $user->get_error_code()
                ));
            }
            return new WP_REST_Response(
                [
                    'success' => false,
                    'code' => 'invalid_credentials',
                    'message' => __('Invalid username or password.', 'sikshya'),
                ],
                401
            );
        }

        // Auth succeeded — wipe the failed-attempt bucket so a legitimate
        // user who fat-fingered their password 2–3× isn't left in a half-
        // throttled state for the next 15 minutes.
        \Sikshya\Security\LoginRateLimiter::clear($ip, $username);

        $jwt = $this->plugin->getService('jwtAuth');
        if (!$jwt instanceof JwtAuthService) {
            return new WP_REST_Response(['success' => false, 'message' => __('JWT unavailable', 'sikshya')], 500);
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

        // Same brute-force guard as /auth/login — both endpoints hit
        // wp_authenticate-equivalent code and would otherwise be free to
        // enumerate at line rate.
        $ip = \Sikshya\Security\LoginRateLimiter::clientIp();
        if (\Sikshya\Security\LoginRateLimiter::isBlocked($ip, $username)) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'code' => 'rate_limited',
                    'message' => __('Too many failed attempts. Please try again later.', 'sikshya'),
                ],
                429
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
            // Username-enumeration defense (mirrors /auth/login). wp_signon
            // returns distinct error codes for unknown-user vs wrong-password
            // — surface a generic 401 here so the response can't be diffed
            // to confirm an account exists.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'Sikshya /auth/web-login auth failure for "%s": %s',
                    $username,
                    $user->get_error_code()
                ));
            }
            \Sikshya\Security\LoginRateLimiter::recordFailure($ip, $username);
            return new WP_REST_Response(
                [
                    'success' => false,
                    'code' => 'invalid_credentials',
                    'message' => __('Invalid email or password.', 'sikshya'),
                ],
                401
            );
        }

        \Sikshya\Security\LoginRateLimiter::clear($ip, $username);
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

        // Anti-spam / anti-enumeration: throttle by client IP. Bypassed for
        // authenticated staff to keep the React admin "Add user" flow snappy.
        $ip = RegistrationRateLimiter::clientIp();
        $isStaff = is_user_logged_in() && current_user_can('manage_options');
        if (!$isStaff && RegistrationRateLimiter::isBlocked($ip)) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'code' => 'rate_limited',
                    'message' => __('Too many registration attempts. Please try again later.', 'sikshya'),
                ],
                429
            );
        }

        /*
         * SECURITY: record the rate-limit attempt UP FRONT — before we
         * branch on `email_exists`, `wp_create_user` succeeded, etc. —
         * so a successful bulk-registration attack can't burn through
         * the limit ONE ATTEMPT AT A TIME: the previous shape recorded
         * the attempt AFTER `wp_create_user` returned, meaning the
         * first attacker got a full free registration + auth cookie
         * even though a later attempt from the same IP would be
         * blocked. Recording on entry makes the limiter symmetric with
         * respect to success and failure and closes the "burn N free
         * accounts before the bucket fills" gap. Staff callers (already
         * logged in with `manage_options`) are exempt from the limiter.
         */
        if (!$isStaff) {
            RegistrationRateLimiter::recordAttempt($ip);
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
        // Rate-limit attempt was already recorded at entry — no duplicate charge here.

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
