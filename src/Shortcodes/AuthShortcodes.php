<?php

namespace Sikshya\Shortcodes;

/**
 * Shortcodes:
 * - [sikshya_login]
 * - [sikshya_registration type="student|instructor"]
 *
 * Uses admin-post handlers to keep behaviour uniform everywhere (including checkout).
 */
final class AuthShortcodes
{
    private static bool $registered = false;

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('init', [self::class, 'register']);
        add_action('admin_post_nopriv_sikshya_auth_login', [self::class, 'handleLogin']);
        add_action('admin_post_nopriv_sikshya_auth_register', [self::class, 'handleRegister']);
    }

    public static function register(): void
    {
        add_shortcode('sikshya_login', [self::class, 'renderLogin']);
        add_shortcode('sikshya_registration', [self::class, 'renderRegistration']);
    }

    /**
     * @param array<string, mixed> $atts
     */
    public static function renderLogin($atts = []): string
    {
        if (is_user_logged_in()) {
            return '<div class="sikshya-card"><p>' . esc_html__('You are already signed in.', 'sikshya') . '</p></div>';
        }

        $a = shortcode_atts(
            [
                'redirect_to' => '',
            ],
            is_array($atts) ? $atts : [],
            'sikshya_login'
        );

        $redirect_to = self::resolveRedirectTo((string) ($a['redirect_to'] ?? ''));
        $err = self::getFlash('login');

        $action = esc_url(admin_url('admin-post.php'));
        $out = '';

        $out .= '<div class="sikshya-auth sikshya-auth--login">';
        if ($err !== '') {
            $out .= '<div class="sikshya-notice sikshya-notice--warning" style="margin:0 0 1rem;">' . esc_html($err) . '</div>';
        }
        $out .= '<form method="post" action="' . $action . '" class="sikshya-auth__form" autocomplete="on">';
        $out .= '<input type="hidden" name="action" value="sikshya_auth_login" />';
        $out .= wp_nonce_field('sikshya_auth_login', '_wpnonce', true, false);
        $out .= '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '" />';

        $out .= '<p style="margin:0 0 0.75rem;">';
        $out .= '<label style="display:block;font-weight:600;margin:0 0 0.25rem;">' . esc_html__('Email', 'sikshya') . '</label>';
        $out .= '<input name="username" type="email" class="sikshya-input" autocomplete="username" required style="width:100%;" />';
        $out .= '</p>';

        $out .= '<p style="margin:0 0 0.75rem;">';
        $out .= '<label style="display:block;font-weight:600;margin:0 0 0.25rem;">' . esc_html__('Password', 'sikshya') . '</label>';
        $out .= '<input name="password" type="password" class="sikshya-input" autocomplete="current-password" required style="width:100%;" />';
        $out .= '</p>';

        $out .= '<p style="margin:0;"><button type="submit" class="sikshya-btn sikshya-btn--primary">' . esc_html__('Sign in', 'sikshya') . '</button></p>';
        $out .= '</form></div>';

        return $out;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public static function renderRegistration($atts = []): string
    {
        if (is_user_logged_in()) {
            return '<div class="sikshya-card"><p>' . esc_html__('You are already signed in.', 'sikshya') . '</p></div>';
        }

        $a = shortcode_atts(
            [
                'type' => 'student',
                'redirect_to' => '',
            ],
            is_array($atts) ? $atts : [],
            'sikshya_registration'
        );

        $type = sanitize_key((string) ($a['type'] ?? 'student'));
        if (!in_array($type, ['student', 'instructor'], true)) {
            $type = 'student';
        }
        $role = $type === 'instructor' ? 'sikshya_instructor' : 'sikshya_student';

        $redirect_to = self::resolveRedirectTo((string) ($a['redirect_to'] ?? ''));
        $err = self::getFlash('register');

        $action = esc_url(admin_url('admin-post.php'));
        $out = '';

        $out .= '<div class="sikshya-auth sikshya-auth--register">';
        if ($err !== '') {
            $out .= '<div class="sikshya-notice sikshya-notice--warning" style="margin:0 0 1rem;">' . esc_html($err) . '</div>';
        }
        $out .= '<form method="post" action="' . $action . '" class="sikshya-auth__form" autocomplete="on">';
        $out .= '<input type="hidden" name="action" value="sikshya_auth_register" />';
        $out .= wp_nonce_field('sikshya_auth_register', '_wpnonce', true, false);
        $out .= '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '" />';
        $out .= '<input type="hidden" name="registration_type" value="' . esc_attr($type) . '" />';
        $out .= '<input type="hidden" name="role" value="' . esc_attr($role) . '" />';

        $out .= '<p style="margin:0 0 0.75rem;">';
        $out .= '<label style="display:block;font-weight:600;margin:0 0 0.25rem;">' . esc_html__('Name (optional)', 'sikshya') . '</label>';
        $out .= '<input name="display_name" type="text" class="sikshya-input" autocomplete="name" style="width:100%;" />';
        $out .= '</p>';

        $out .= '<p style="margin:0 0 0.75rem;">';
        $out .= '<label style="display:block;font-weight:600;margin:0 0 0.25rem;">' . esc_html__('Email', 'sikshya') . '</label>';
        $out .= '<input name="email" type="email" class="sikshya-input" autocomplete="email" required style="width:100%;" />';
        $out .= '</p>';

        $out .= '<p style="margin:0 0 0.75rem;">';
        $out .= '<label style="display:block;font-weight:600;margin:0 0 0.25rem;">' . esc_html__('Password', 'sikshya') . '</label>';
        $out .= '<input name="password" type="password" class="sikshya-input" autocomplete="new-password" required style="width:100%;" />';
        $out .= '</p>';

        $label = $type === 'instructor'
            ? esc_html__('Create instructor account', 'sikshya')
            : esc_html__('Create account', 'sikshya');
        $out .= '<p style="margin:0;"><button type="submit" class="sikshya-btn sikshya-btn--primary">' . $label . '</button></p>';
        $out .= '</form></div>';

        return $out;
    }

    public static function handleLogin(): void
    {
        if (is_user_logged_in()) {
            wp_safe_redirect(self::resolveRedirectTo((string) (wp_unslash($_POST['redirect_to'] ?? '') ?: home_url('/'))));
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) wp_unslash($_POST['_wpnonce']), 'sikshya_auth_login')) {
            self::redirectBackWithFlash('login', __('Invalid request.', 'sikshya'));
        }

        $username = sanitize_text_field((string) wp_unslash($_POST['username'] ?? ''));
        $password = (string) wp_unslash($_POST['password'] ?? '');
        $redirect_to = self::resolveRedirectTo((string) wp_unslash($_POST['redirect_to'] ?? ''));

        if ($username === '' || $password === '') {
            self::redirectBackWithFlash('login', __('Email and password are required.', 'sikshya'), $redirect_to);
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
            self::redirectBackWithFlash('login', $user->get_error_message(), $redirect_to);
        }

        wp_set_current_user((int) $user->ID);
        wp_set_auth_cookie((int) $user->ID, true, is_ssl());

        wp_safe_redirect($redirect_to !== '' ? $redirect_to : home_url('/'));
        exit;
    }

    public static function handleRegister(): void
    {
        if (is_user_logged_in()) {
            wp_safe_redirect(self::resolveRedirectTo((string) (wp_unslash($_POST['redirect_to'] ?? '') ?: home_url('/'))));
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) wp_unslash($_POST['_wpnonce']), 'sikshya_auth_register')) {
            self::redirectBackWithFlash('register', __('Invalid request.', 'sikshya'));
        }

        $email = sanitize_email((string) wp_unslash($_POST['email'] ?? ''));
        $password = (string) wp_unslash($_POST['password'] ?? '');
        $display_name = sanitize_text_field((string) wp_unslash($_POST['display_name'] ?? ''));
        $type = sanitize_key((string) wp_unslash($_POST['registration_type'] ?? 'student'));
        $role = sanitize_key((string) wp_unslash($_POST['role'] ?? 'sikshya_student'));
        $redirect_to = self::resolveRedirectTo((string) wp_unslash($_POST['redirect_to'] ?? ''));

        if ($type !== 'instructor') {
            $type = 'student';
        }
        $expected_role = $type === 'instructor' ? 'sikshya_instructor' : 'sikshya_student';
        if ($role !== $expected_role) {
            $role = $expected_role;
        }

        if ($email === '' || !is_email($email) || $password === '') {
            self::redirectBackWithFlash('register', __('Valid email and password are required.', 'sikshya'), $redirect_to);
        }

        if (email_exists($email)) {
            self::redirectBackWithFlash('register', __('An account with this email already exists. Please sign in instead.', 'sikshya'), $redirect_to);
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
        if (is_wp_error($user_id) || (int) $user_id <= 0) {
            $msg = is_wp_error($user_id) ? $user_id->get_error_message() : __('Could not create account.', 'sikshya');
            self::redirectBackWithFlash('register', $msg, $redirect_to);
        }

        $u = get_userdata((int) $user_id);
        if ($u) {
            $u->set_role($role);
        }

        if ($display_name !== '') {
            wp_update_user(['ID' => (int) $user_id, 'display_name' => $display_name]);
        }

        wp_set_current_user((int) $user_id);
        wp_set_auth_cookie((int) $user_id, true, is_ssl());

        wp_safe_redirect($redirect_to !== '' ? $redirect_to : home_url('/'));
        exit;
    }

    private static function resolveRedirectTo(string $redirect_to): string
    {
        $redirect_to = trim($redirect_to);
        if ($redirect_to === '') {
            $ref = wp_get_referer();
            if (is_string($ref) && $ref !== '') {
                $redirect_to = $ref;
            }
        }

        $url = $redirect_to !== '' ? $redirect_to : home_url('/');
        return esc_url_raw(wp_validate_redirect($url, home_url('/')));
    }

    private static function getFlash(string $scope): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash in querystring.
        $s = isset($_GET['sikshya_auth_scope']) ? sanitize_key((string) wp_unslash($_GET['sikshya_auth_scope'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash in querystring.
        $m = isset($_GET['sikshya_auth_message']) ? (string) wp_unslash($_GET['sikshya_auth_message']) : '';
        if ($s !== $scope || $m === '') {
            return '';
        }
        return sanitize_text_field($m);
    }

    private static function redirectBackWithFlash(string $scope, string $message, string $redirect_to = ''): void
    {
        $back = $redirect_to !== '' ? $redirect_to : (wp_get_referer() ?: home_url('/'));
        $back = self::resolveRedirectTo((string) $back);
        $url = add_query_arg(
            [
                'sikshya_auth_scope' => $scope,
                'sikshya_auth_message' => rawurlencode($message),
            ],
            $back
        );
        wp_safe_redirect($url);
        exit;
    }
}

