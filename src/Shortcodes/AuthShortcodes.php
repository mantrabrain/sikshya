<?php

namespace Sikshya\Shortcodes;

use Sikshya\Frontend\Public\CartStorage;
use Sikshya\Services\PermalinkService;
use WP_Error;

/**
 * Shortcodes:
 * - [sikshya_login]
 * - [sikshya_registration type="student|instructor"] — always creates a Sikshya student;
 *   instructor intent seeds a pending instructor application (role added only after admin approval).
 *
 * Primary UX: AJAX (admin-ajax) with inline messages. Forms still POST to admin-post.php when JS is off.
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
        add_action('wp_ajax_nopriv_sikshya_ajax_auth_login', [self::class, 'ajaxLogin']);
        add_action('wp_ajax_sikshya_ajax_auth_login', [self::class, 'ajaxLogin']);
        add_action('wp_ajax_nopriv_sikshya_ajax_auth_register', [self::class, 'ajaxRegister']);
        add_action('wp_ajax_sikshya_ajax_auth_register', [self::class, 'ajaxRegister']);
    }

    public static function register(): void
    {
        add_shortcode('sikshya_login', [self::class, 'renderLogin']);
        add_shortcode('sikshya_registration', [self::class, 'renderRegistration']);
        self::registerPublicScript();
    }

    private static function registerPublicScript(): void
    {
        if (!defined('SIKSHYA_PLUGIN_FILE') || !defined('SIKSHYA_VERSION')) {
            return;
        }
        $url = plugins_url('assets/js/auth-public.js', SIKSHYA_PLUGIN_FILE);
        wp_register_script(
            'sikshya-auth-public',
            $url,
            [],
            SIKSHYA_VERSION,
            true
        );
        wp_localize_script(
            'sikshya-auth-public',
            'sikshyaAuthPublic',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'loginAction' => 'sikshya_ajax_auth_login',
                'registerAction' => 'sikshya_ajax_auth_register',
                'strings' => [
                    'networkError' => __('Network error. Please try again.', 'sikshya'),
                    'requestFailed' => __('Request failed. Please try again.', 'sikshya'),
                    'signedInRedirect' => __('Signed in. Redirecting…', 'sikshya'),
                    'accountCreatedRedirect' => __('Account created. Redirecting…', 'sikshya'),
                ],
            ]
        );
    }

    private static function enqueuePublicScript(): void
    {
        if (wp_script_is('sikshya-auth-public', 'enqueued')) {
            return;
        }
        wp_enqueue_script('sikshya-auth-public');
    }

    /**
     * @param array<string, mixed> $atts
     */
    public static function renderLogin($atts = []): string
    {
        if (is_user_logged_in()) {
            return '<div class="sikshya-card"><p>' . esc_html__('You are already signed in.', 'sikshya') . '</p></div>';
        }

        self::enqueuePublicScript();

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
        $lost = wp_lostpassword_url($redirect_to !== '' ? $redirect_to : home_url('/'));
        $fieldId = wp_unique_id('sikshya-auth-login-');

        $ajax_url = esc_url(admin_url('admin-ajax.php'));
        $out = '';
        $out .= '<div class="sikshya-auth sikshya-auth--login" data-sikshya-auth-ajax="1" data-sikshya-auth-kind="login" data-sikshya-ajax-url="' . esc_attr($ajax_url) . '" data-sikshya-login-action="sikshya_ajax_auth_login" data-sikshya-auth-redirect-to="' . esc_attr($redirect_to) . '">';
        $out .= '<div class="sikshya-auth__messages" role="region" aria-live="polite" aria-relevant="additions text"' . ($err === '' ? ' hidden' : '') . '>';
        if ($err !== '') {
            $out .= '<div class="sikshya-notice sikshya-notice--error" role="alert">' . esc_html($err) . '</div>';
        }
        $out .= '</div>';
        $out .= '<form method="post" action="' . $action . '" class="sikshya-auth__form" autocomplete="on" novalidate>';
        $out .= '<input type="hidden" name="action" value="sikshya_auth_login" />';
        $out .= wp_nonce_field('sikshya_auth_login', '_wpnonce', true, false);
        $out .= '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '" />';

        $out .= '<div class="sikshya-auth__field">';
        $out .= '<label class="sikshya-auth__label" for="' . esc_attr($fieldId) . '-user">' . esc_html__('Email or username', 'sikshya') . '</label>';
        $out .= '<input id="' . esc_attr($fieldId) . '-user" name="username" type="text" class="sikshya-input sikshya-auth__control" autocomplete="username" required placeholder="' . esc_attr__('you@example.com or username', 'sikshya') . '" />';
        $out .= '</div>';

        $out .= '<div class="sikshya-auth__field">';
        $out .= '<label class="sikshya-auth__label" for="' . esc_attr($fieldId) . '-pass">' . esc_html__('Password', 'sikshya') . '</label>';
        $out .= '<input id="' . esc_attr($fieldId) . '-pass" name="password" type="password" class="sikshya-input sikshya-auth__control" autocomplete="current-password" required placeholder="' . esc_attr__('Enter your password', 'sikshya') . '" />';
        $out .= '</div>';

        $out .= '<div class="sikshya-auth__row sikshya-auth__row--split">';
        $out .= '<label class="sikshya-auth__remember"><input type="checkbox" name="remember" value="1" checked="checked" /> <span>' . esc_html__('Remember me', 'sikshya') . '</span></label>';
        $out .= '<a class="sikshya-auth__link" href="' . esc_url($lost) . '">' . esc_html__('Forgot password?', 'sikshya') . '</a>';
        $out .= '</div>';

        $out .= '<div class="sikshya-auth__actions"><button type="submit" class="sikshya-btn sikshya-btn--primary sikshya-auth__submit">' . esc_html__('Sign in', 'sikshya') . '</button></div>';
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

        self::enqueuePublicScript();

        $a = shortcode_atts(
            [
                'type' => 'student',
                'redirect_to' => '',
            ],
            is_array($atts) ? $atts : [],
            'sikshya_registration'
        );

        $type = sanitize_key((string) ($a['type'] ?? 'student'));
        if (!in_array($type, ['instructor', 'student'], true)) {
            $type = 'student';
        }

        $redirect_to = self::resolveRedirectTo((string) ($a['redirect_to'] ?? ''));
        $err = self::getFlash('register');

        $action = esc_url(admin_url('admin-post.php'));
        $fieldId = wp_unique_id('sikshya-auth-reg-');

        $ajax_url = esc_url(admin_url('admin-ajax.php'));
        $out = '';
        $out .= '<div class="sikshya-auth sikshya-auth--register" data-sikshya-auth-ajax="1" data-sikshya-auth-kind="register" data-sikshya-ajax-url="' . esc_attr($ajax_url) . '" data-sikshya-register-action="sikshya_ajax_auth_register" data-sikshya-auth-redirect-to="' . esc_attr($redirect_to) . '">';
        $out .= '<div class="sikshya-auth__messages" role="region" aria-live="polite" aria-relevant="additions text"' . ($err === '' ? ' hidden' : '') . '>';
        if ($err !== '') {
            $out .= '<div class="sikshya-notice sikshya-notice--error" role="alert">' . esc_html($err) . '</div>';
        }
        $out .= '</div>';
        $out .= '<form method="post" action="' . $action . '" class="sikshya-auth__form" autocomplete="on" novalidate>';
        $out .= '<input type="hidden" name="action" value="sikshya_auth_register" />';
        $out .= wp_nonce_field('sikshya_auth_register', '_wpnonce', true, false);
        $out .= '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '" />';
        $out .= '<input type="hidden" name="registration_type" value="' . esc_attr($type) . '" />';

        $out .= '<div class="sikshya-auth__field">';
        $out .= '<label class="sikshya-auth__label" for="' . esc_attr($fieldId) . '-name">' . esc_html__('Display name (optional)', 'sikshya') . '</label>';
        $out .= '<input id="' . esc_attr($fieldId) . '-name" name="display_name" type="text" class="sikshya-input sikshya-auth__control" autocomplete="name" placeholder="' . esc_attr__('How we should greet you', 'sikshya') . '" />';
        $out .= '</div>';

        $out .= '<div class="sikshya-auth__field">';
        $out .= '<label class="sikshya-auth__label" for="' . esc_attr($fieldId) . '-email">' . esc_html__('Email', 'sikshya') . '</label>';
        $out .= '<input id="' . esc_attr($fieldId) . '-email" name="email" type="email" class="sikshya-input sikshya-auth__control" autocomplete="email" required placeholder="' . esc_attr__('you@example.com', 'sikshya') . '" />';
        $out .= '</div>';

        $out .= '<div class="sikshya-auth__field">';
        $out .= '<label class="sikshya-auth__label" for="' . esc_attr($fieldId) . '-pass">' . esc_html__('Password', 'sikshya') . '</label>';
        $out .= '<input id="' . esc_attr($fieldId) . '-pass" name="password" type="password" class="sikshya-input sikshya-auth__control" autocomplete="new-password" required placeholder="' . esc_attr__('Choose a strong password', 'sikshya') . '" />';
        $out .= '</div>';

        $label = $type === 'instructor'
            ? esc_html__('Create account & apply to teach', 'sikshya')
            : esc_html__('Create account', 'sikshya');
        $out .= '<div class="sikshya-auth__actions"><button type="submit" class="sikshya-btn sikshya-btn--primary sikshya-auth__submit">' . $label . '</button></div>';
        $out .= '</form></div>';

        return $out;
    }

    public static function ajaxLogin(): void
    {
        if (!check_ajax_referer('sikshya_auth_login', '_wpnonce', false)) {
            wp_send_json_error(['message' => __('Invalid request.', 'sikshya')], 403);
        }

        $redirect_to = self::resolveRedirectTo((string) wp_unslash($_POST['redirect_to'] ?? ''));

        if (is_user_logged_in()) {
            wp_send_json_success(
                [
                    'redirect' => $redirect_to !== '' ? $redirect_to : home_url('/'),
                    'message' => __('Already signed in. Redirecting…', 'sikshya'),
                ]
            );
        }

        $username = trim(sanitize_text_field((string) wp_unslash($_POST['username'] ?? '')));
        $password = (string) wp_unslash($_POST['password'] ?? '');
        $remember = isset($_POST['remember']) && (string) wp_unslash($_POST['remember']) === '1';

        $user = self::attemptSignon($username, $password, $remember);
        if (is_wp_error($user)) {
            wp_send_json_error(['message' => self::normalizeFlashMessage($user->get_error_message())]);
        }

        wp_set_current_user((int) $user->ID);
        wp_set_auth_cookie((int) $user->ID, $remember, is_ssl());

        wp_send_json_success(
            [
                'redirect' => $redirect_to !== '' ? $redirect_to : home_url('/'),
                'message' => __('Signed in successfully. Redirecting…', 'sikshya'),
            ]
        );
    }

    public static function ajaxRegister(): void
    {
        if (!check_ajax_referer('sikshya_auth_register', '_wpnonce', false)) {
            wp_send_json_error(['message' => __('Invalid request.', 'sikshya')], 403);
        }

        $redirect_to = self::resolveRedirectTo((string) wp_unslash($_POST['redirect_to'] ?? ''));

        if (is_user_logged_in()) {
            wp_send_json_success(
                [
                    'redirect' => $redirect_to !== '' ? $redirect_to : home_url('/'),
                    'message' => __('Already signed in. Redirecting…', 'sikshya'),
                ]
            );
        }

        $email = sanitize_email((string) wp_unslash($_POST['email'] ?? ''));
        $password = (string) wp_unslash($_POST['password'] ?? '');
        $display_name = sanitize_text_field((string) wp_unslash($_POST['display_name'] ?? ''));
        $type = sanitize_key((string) wp_unslash($_POST['registration_type'] ?? 'student'));

        $result = self::createRegisteredUser($email, $password, $display_name, $type);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => self::normalizeFlashMessage($result->get_error_message())]);
        }

        $user_id = (int) $result;
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());
        CartStorage::adoptGuestCartForUser($user_id);

        wp_send_json_success(
            [
                'redirect' => $redirect_to !== '' ? $redirect_to : home_url('/'),
                'message' => __('Account created. Redirecting…', 'sikshya'),
            ]
        );
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

        $username = trim(sanitize_text_field((string) wp_unslash($_POST['username'] ?? '')));
        $password = (string) wp_unslash($_POST['password'] ?? '');
        $redirect_to = self::resolveRedirectTo((string) wp_unslash($_POST['redirect_to'] ?? ''));
        $remember = isset($_POST['remember']) && (string) wp_unslash($_POST['remember']) === '1';

        $user = self::attemptSignon($username, $password, $remember);
        if (is_wp_error($user)) {
            self::redirectBackWithFlash('login', $user->get_error_message(), $redirect_to);
        }

        wp_set_current_user((int) $user->ID);
        wp_set_auth_cookie((int) $user->ID, $remember, is_ssl());

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
        $redirect_to = self::resolveRedirectTo((string) wp_unslash($_POST['redirect_to'] ?? ''));

        $result = self::createRegisteredUser($email, $password, $display_name, $type);
        if (is_wp_error($result)) {
            self::redirectBackWithFlash('register', $result->get_error_message(), $redirect_to);
        }

        $user_id = (int) $result;
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());
        CartStorage::adoptGuestCartForUser($user_id);

        wp_safe_redirect($redirect_to !== '' ? $redirect_to : home_url('/'));
        exit;
    }

    /**
     * @return WP_User|WP_Error
     */
    private static function attemptSignon(string $username, string $password, bool $remember)
    {
        if ($username === '' || $password === '') {
            return new WP_Error('empty', __('Email and password are required.', 'sikshya'));
        }

        return wp_signon(
            [
                'user_login' => $username,
                'user_password' => $password,
                'remember' => $remember,
            ],
            is_ssl()
        );
    }

    /**
     * @return int|WP_Error User ID or error.
     */
    private static function createRegisteredUser(
        string $email,
        string $password,
        string $display_name,
        string $type
    ) {
        $type = $type === 'instructor' ? 'instructor' : 'student';

        if ($email === '' || !is_email($email) || $password === '') {
            return new WP_Error('invalid_input', __('Valid email and password are required.', 'sikshya'));
        }

        if (email_exists($email)) {
            return new WP_Error(
                'email_exists',
                __('An account with this email already exists. Please sign in instead.', 'sikshya')
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
        if (is_wp_error($user_id) || (int) $user_id <= 0) {
            $msg = is_wp_error($user_id) ? $user_id->get_error_message() : __('Could not create account.', 'sikshya');

            return new WP_Error('create_failed', $msg);
        }

        $u = get_userdata((int) $user_id);
        if ($u) {
            // Instructor capability is granted only after admin approval; see InstructorApplicationsService::approve().
            $u->set_role('sikshya_student');
        }

        if ($display_name !== '') {
            wp_update_user(['ID' => (int) $user_id, 'display_name' => $display_name]);
        }

        if ($type === 'instructor') {
            self::seedPendingInstructorApplication((int) $user_id, $display_name);
        }

        self::sendCoreNewUserNotifications((int) $user_id);

        return (int) $user_id;
    }

    /**
     * Same notifications WordPress sends after {@see register_new_user()} (admin + user).
     */
    private static function sendCoreNewUserNotifications(int $user_id): void
    {
        if ($user_id <= 0 || !function_exists('wp_send_new_user_notifications')) {
            return;
        }

        /**
         * Whether to send WordPress core new-user notification emails after Sikshya registration.
         *
         * @param bool $send    Default true.
         * @param int  $user_id New user ID.
         */
        if (!apply_filters('sikshya_send_new_user_notifications', true, $user_id)) {
            return;
        }

        wp_send_new_user_notifications($user_id, 'both');
    }

    /**
     * Instructor signup: student role + pending application (mirrors account apply form meta).
     *
     * @param string $display_name Sanitized display name from registration (optional headline seed).
     */
    private static function seedPendingInstructorApplication(int $user_id, string $display_name): void
    {
        $payload = [
            'headline' => $display_name !== '' ? $display_name : '',
            'bio' => '',
            'website' => '',
        ];

        update_user_meta($user_id, '_sikshya_instructor_application', $payload);
        update_user_meta($user_id, '_sikshya_instructor_status', 'pending');
        update_user_meta($user_id, '_sikshya_instructor_applied_at', gmdate('c'));

        do_action('sikshya_instructor_application_submitted', $user_id, $payload);
    }

    private static function resolveRedirectTo(string $redirect_to): string
    {
        $redirect_to = trim($redirect_to);
        if ($redirect_to === '') {
            $ref = wp_get_referer();
            if (is_string($ref) && $ref !== '' && self::isSafeRefererRedirect($ref)) {
                $redirect_to = $ref;
            }
        }

        $url = $redirect_to !== '' ? $redirect_to : home_url('/');

        return esc_url_raw(wp_validate_redirect($url, home_url('/')));
    }

    /**
     * When redirect_to is empty we may fall back to the HTTP referer. Only allow
     * Sikshya commerce/learn routes (or site root), never arbitrary posts such as
     * the default sample "hello-world" page, which caused checkout sign-in to
     * bounce away from checkout.
     */
    private static function isSafeRefererRedirect(string $url): bool
    {
        $parsed = wp_parse_url($url);
        if (!is_array($parsed)) {
            return false;
        }

        $home = wp_parse_url(home_url('/'));
        $home_host = isset($home['host']) ? strtolower((string) $home['host']) : '';
        $url_host = isset($parsed['host']) ? strtolower((string) $parsed['host']) : '';
        if ($home_host === '' || $url_host === '' || $home_host !== $url_host) {
            return false;
        }

        $path = isset($parsed['path']) ? trim((string) $parsed['path'], '/') : '';
        $query = isset($parsed['query']) ? (string) $parsed['query'] : '';

        if ($query !== '' && strpos($query, PermalinkService::QUERY_VAR . '=') !== false) {
            return true;
        }

        if ($path === '' || $path === '/') {
            return true;
        }

        $perm = PermalinkService::get();
        $slugs = [];
        foreach (['cart', 'checkout', 'account', 'learn', 'login', 'order'] as $page) {
            $key = 'permalink_' . $page;
            $slugs[] = PermalinkService::sanitizeSlug($perm[$key] ?? $page);
        }
        $slugs[] = PermalinkService::sanitizeSlug($perm['rewrite_base_course'] ?? 'courses');
        $slugs[] = PermalinkService::sanitizeSlug($perm['rewrite_base_author'] ?? 'author');

        $first = explode('/', $path, 2)[0];
        foreach ($slugs as $slug) {
            if ($slug !== '' && $first === $slug) {
                return true;
            }
        }

        /**
         * Allow referer fallback for custom paths (e.g. landing pages with login shortcode).
         *
         * @param bool   $allow   Default false.
         * @param string $path    Path without leading/trailing slashes.
         * @param string $url     Full referer URL.
         */
        return (bool) apply_filters('sikshya_auth_allow_referer_redirect_path', false, $path, $url);
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

        return self::normalizeFlashMessage($m);
    }

    private static function redirectBackWithFlash(string $scope, string $message, string $redirect_to = ''): void
    {
        $back = $redirect_to !== '' ? $redirect_to : (wp_get_referer() ?: home_url('/'));
        $back = self::resolveRedirectTo((string) $back);
        $url = add_query_arg(
            [
                'sikshya_auth_scope' => $scope,
                'sikshya_auth_message' => self::normalizeFlashMessage($message),
            ],
            $back
        );
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Flash messages are shown in a query string; strip HTML (WordPress wp_signon errors may include markup).
     */
    private static function normalizeFlashMessage(string $message): string
    {
        $message = wp_strip_all_tags($message);
        $message = trim(preg_replace('/\s+/u', ' ', $message));
        if (strlen($message) > 500) {
            $message = substr($message, 0, 497) . '...';
        }

        return $message;
    }
}
