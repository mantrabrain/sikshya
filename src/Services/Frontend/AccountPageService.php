<?php

/**
 * @package Sikshya\Services\Frontend
 */

namespace Sikshya\Services\Frontend;

use Sikshya\Core\Plugin;
use Sikshya\Frontend\Site\AccountTemplateData;
use Sikshya\Frontend\Site\PublicPageUrls;
use Sikshya\Presentation\Models\AccountPageModel;
use Sikshya\Services\PermalinkService;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class AccountPageService
{
    /**
     * Handles legacy redirects/guards and builds the model.
     *
     * Note: redirects are intentional (template safety + old URLs).
     */
    public static function fromRequest(): AccountPageModel
    {
        self::handleProfilePost();

        // Legacy single-page anchors: ?section=orders|quiz-attempts
        if (!empty($_GET['section'])) {
            $sec = sanitize_key((string) wp_unslash($_GET['section']));
            $legacyMap = [
                'orders' => 'payments',
                'quiz-attempts' => 'quiz-attempts',
            ];
            if (isset($legacyMap[$sec])) {
                wp_safe_redirect(PublicPageUrls::accountViewUrl($legacyMap[$sec]));
                exit;
            }
        }

        $rawView = sanitize_key((string) get_query_var(PermalinkService::ACCOUNT_VIEW_VAR));
        if ($rawView !== '' && !in_array($rawView, PublicPageUrls::allowedAccountViews(), true)) {
            wp_safe_redirect(PublicPageUrls::accountViewUrl('dashboard'));
            exit;
        }

        $legacy = AccountTemplateData::build();
        $page = AccountPageModel::fromLegacy($legacy);

        if ($page->getUserId() <= 0) {
            wp_safe_redirect(PublicPageUrls::login(PublicPageUrls::url('account')));
            exit;
        }

        return $page;
    }

    /**
     * Resolves the account section partial path (supports Pro/addon override filter).
     *
     * @return string absolute path
     */
    public static function resolvePartialPath(AccountPageModel $page): string
    {
        $plugin = Plugin::getInstance();
        $legacy = $page->toLegacyViewArray();
        $view = $page->getView();

        $partialMap = [
            'dashboard' => 'dashboard',
            'learning' => 'learning',
            'payments' => 'payments',
            'quiz-attempts' => 'quiz-attempts',
            'profile' => 'profile',
            'instructor' => 'instructor',
        ];

        $partialName = $partialMap[$view] ?? 'dashboard';
        $defaultPartial = $plugin->getTemplatePath('partials/account-view-' . $partialName . '.php');

        /**
         * Override path for an account section template (Pro / addons).
         *
         */
        $partialPath = apply_filters('sikshya_account_view_template', $defaultPartial, $view, $legacy);
        if (!is_string($partialPath) || !is_readable($partialPath)) {
            $partialPath = $plugin->getTemplatePath('partials/account-view-dashboard.php');
        }

        return (string) $partialPath;
    }

    private static function handleProfilePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $is_profile_submit = !empty($_POST['sikshya_account_profile_submit']);
        $is_password_submit = !empty($_POST['sikshya_account_password_submit']);
        if (!$is_profile_submit && !$is_password_submit) {
            return;
        }

        $uid = get_current_user_id();
        if ($uid <= 0) {
            return;
        }

        $target = PublicPageUrls::accountViewUrl('profile');
        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, 'sikshya_account_profile_update')) {
            wp_safe_redirect(add_query_arg(['sik_acc_err' => 'invalid_nonce'], $target));
            exit;
        }

        if ($is_profile_submit) {
            $user = get_userdata($uid);
            if (!$user) {
                wp_safe_redirect(add_query_arg(['sik_acc_err' => 'user_not_found'], $target));
                exit;
            }

            $display_name = isset($_POST['display_name']) ? sanitize_text_field((string) wp_unslash($_POST['display_name'])) : '';
            $first_name = isset($_POST['first_name']) ? sanitize_text_field((string) wp_unslash($_POST['first_name'])) : '';
            $last_name = isset($_POST['last_name']) ? sanitize_text_field((string) wp_unslash($_POST['last_name'])) : '';
            $email = isset($_POST['user_email']) ? sanitize_email((string) wp_unslash($_POST['user_email'])) : '';
            $current_email = (string) $user->user_email;
            $email_changed = $email !== '' && strtolower($email) !== strtolower($current_email);

            if ($display_name === '') {
                $display_name = (string) $user->display_name;
            }

            if ($email === '' || !is_email($email)) {
                wp_safe_redirect(add_query_arg(['sik_acc_err' => 'invalid_email'], $target));
                exit;
            }

            $existing = (int) email_exists($email);
            if ($existing > 0 && $existing !== $uid) {
                wp_safe_redirect(add_query_arg(['sik_acc_err' => 'email_in_use'], $target));
                exit;
            }

            $result = wp_update_user(
                [
                    'ID' => $uid,
                    'display_name' => $display_name,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    // Follow WP profile flow: email change is confirmed via link before applying.
                    'user_email' => $current_email,
                ]
            );

            if (is_wp_error($result)) {
                wp_safe_redirect(add_query_arg(['sik_acc_err' => 'profile_update_failed'], $target));
                exit;
            }

            if ($email_changed) {
                $sent = self::queuePendingEmailChange($uid, $email);
                wp_safe_redirect(
                    add_query_arg(
                        ['sik_acc_notice' => $sent ? 'email_confirmation_sent' : 'email_confirmation_failed'],
                        $target
                    )
                );
                exit;
            }

            wp_safe_redirect(add_query_arg(['sik_acc_notice' => 'profile_saved'], $target));
            exit;
        }

        $current_password = isset($_POST['current_password']) ? (string) wp_unslash($_POST['current_password']) : '';
        $new_password = isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '';
        $confirm_password = isset($_POST['confirm_password']) ? (string) wp_unslash($_POST['confirm_password']) : '';

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            wp_safe_redirect(add_query_arg(['sik_acc_err' => 'password_fields_required'], $target));
            exit;
        }

        $user = get_userdata($uid);
        if (!$user || !wp_check_password($current_password, (string) $user->user_pass, $uid)) {
            wp_safe_redirect(add_query_arg(['sik_acc_err' => 'password_current_invalid'], $target));
            exit;
        }

        if (strlen($new_password) < 8) {
            wp_safe_redirect(add_query_arg(['sik_acc_err' => 'password_too_short'], $target));
            exit;
        }

        if ($new_password !== $confirm_password) {
            wp_safe_redirect(add_query_arg(['sik_acc_err' => 'password_mismatch'], $target));
            exit;
        }

        wp_set_password($new_password, $uid);
        wp_set_current_user($uid);
        wp_set_auth_cookie($uid, true, is_ssl());
        $updated_user = get_user_by('id', $uid);
        if ($updated_user instanceof \WP_User) {
            /** This action is documented in wp-includes/pluggable.php */
            do_action('wp_login', $updated_user->user_login, $updated_user);
        }

        wp_safe_redirect(add_query_arg(['sik_acc_notice' => 'password_saved'], $target));
        exit;
    }

    private static function queuePendingEmailChange(int $uid, string $new_email): bool
    {
        $user = get_userdata($uid);
        if (!$user || $new_email === '' || !is_email($new_email)) {
            return false;
        }

        $hash = hash('sha256', $new_email . '|' . time() . '|' . wp_rand());
        update_user_meta(
            $uid,
            '_new_email',
            [
                'hash' => $hash,
                'newemail' => $new_email,
            ]
        );

        // Use WordPress core confirmation endpoint to keep behavior familiar.
        $confirm_url = add_query_arg(
            'newuseremail',
            rawurlencode($hash),
            admin_url('profile.php')
        );

        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $subject = sprintf(
            /* translators: %s: site title */
            __('[%s] Verify email address change', 'sikshya'),
            $blogname
        );
        $message = sprintf(
            /* translators: 1: site title, 2: new email, 3: confirmation URL */
            __("Howdy,\n\nA request was made to change your email address on %1\$s to %2\$s.\n\nConfirm this change by visiting the following link:\n%3\$s\n\nIf you did not request this, you can ignore this email.\n", 'sikshya'),
            $blogname,
            $new_email,
            $confirm_url
        );

        return (bool) wp_mail($new_email, wp_specialchars_decode($subject), $message);
    }
}

