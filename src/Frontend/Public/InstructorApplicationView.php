<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Core\Plugin;

/**
 * Account: "Apply for instructor" view for learners.
 *
 * This is intentionally frontend (account page) so sites can allow instructor
 * applications without granting wp-admin access.
 *
 * @package Sikshya\Frontend\Public
 */
final class InstructorApplicationView
{
    private static bool $registered = false;

    public const VIEW_SLUG = 'instructor-apply';

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_filter('sikshya_account_allowed_views', [self::class, 'registerView']);
        add_filter('sikshya_account_template_data', [self::class, 'inject'], 35);
        add_filter('sikshya_account_view_template', [self::class, 'overrideViewTemplate'], 10, 3);
        add_action('sikshya_account_sidebar_nav', [self::class, 'renderSidebarNav'], 6, 2);

        add_action('admin_post_sikshya_instructor_apply', [self::class, 'handleApply']);
        add_action('admin_post_nopriv_sikshya_instructor_apply', [self::class, 'handleApply']);
    }

    /**
     * HTML for the instructor application form (account shell — uses `.sik-acc-*` styles).
     */
    public static function renderFormHtml(): string
    {
        if (!is_user_logged_in()) {
            $login = wp_login_url((string) (is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : home_url('/')));

            return '<div class="sik-acc-instructor-form sik-acc-instructor-form--guest">'
                . '<p class="sik-acc-instructor-form__guest-text">' . esc_html__('Please log in to apply as an instructor.', 'sikshya') . '</p>'
                . '<p class="sik-acc-instructor-form__guest-actions"><a class="sik-acc-btn sik-acc-btn--primary" href="' . esc_url($login) . '">'
                . esc_html__('Log in', 'sikshya') . '</a></p>'
                . '</div>';
        }

        $uid = get_current_user_id();
        $status = (string) get_user_meta($uid, '_sikshya_instructor_status', true);

        $app_raw = get_user_meta($uid, '_sikshya_instructor_application', true);
        $headline_val = '';
        $bio_val = '';
        $website_val = '';
        if (is_array($app_raw)) {
            $headline_val = isset($app_raw['headline']) ? (string) $app_raw['headline'] : '';
            $bio_val = isset($app_raw['bio']) ? (string) $app_raw['bio'] : '';
            $website_val = isset($app_raw['website']) ? (string) $app_raw['website'] : '';
        }

        $out = '<div class="sik-acc-instructor-form">';

        $alerts = '';
        if ($status === 'pending') {
            $alerts .= '<div class="sik-acc-callout sik-acc-callout--info" role="status">' . esc_html__('Your application is pending review.', 'sikshya') . '</div>';
        } elseif ($status === 'inactive' || $status === 'rejected') {
            $alerts .= '<div class="sik-acc-callout sik-acc-callout--warning" role="status">' . esc_html__('Your application was not approved. You can update the details below and submit again.', 'sikshya') . '</div>';
        } elseif ($status === 'active') {
            $alerts .= '<div class="sik-acc-callout sik-acc-callout--success" role="status">' . esc_html__('You are an instructor.', 'sikshya') . '</div>';
        }

        if ($alerts !== '') {
            $out .= '<div class="sik-acc-instructor-form__alerts">' . $alerts . '</div>';
        }

        $action = esc_url(admin_url('admin-post.php'));
        $out .= '<form class="sik-acc-instructor-form__form" method="post" action="' . $action . '">';
        $out .= '<input type="hidden" name="action" value="sikshya_instructor_apply" />';
        $out .= wp_nonce_field('sikshya_instructor_apply', '_wpnonce', true, false);

        $out .= '<div class="sik-acc-form-grid sik-acc-form-grid--full sik-acc-form-grid--instructor-apply">';
        $out .= '<div class="sik-acc-field sik-acc-field--full">';
        $out .= '<label class="sik-acc-field__label" for="sikshya-instructor-headline">' . esc_html__('Headline', 'sikshya') . '</label>';
        $out .= '<input id="sikshya-instructor-headline" class="sik-acc-input" name="headline" type="text" autocomplete="organization-title" value="' . esc_attr($headline_val) . '" placeholder="' . esc_attr__('e.g. Web developer & WordPress trainer', 'sikshya') . '" /></div>';

        $out .= '<div class="sik-acc-field sik-acc-field--full">';
        $out .= '<label class="sik-acc-field__label" for="sikshya-instructor-bio">' . esc_html__('Short bio', 'sikshya') . '</label>';
        $out .= '<textarea id="sikshya-instructor-bio" class="sik-acc-textarea" name="bio" rows="5" placeholder="' . esc_attr__('What will you teach? Share your experience and approach.', 'sikshya') . '">' . esc_textarea($bio_val) . '</textarea></div>';

        $out .= '<div class="sik-acc-field sik-acc-field--full">';
        $out .= '<label class="sik-acc-field__label" for="sikshya-instructor-website">' . esc_html__('Website (optional)', 'sikshya') . '</label>';
        $out .= '<input id="sikshya-instructor-website" class="sik-acc-input" name="website" type="url" inputmode="url" autocomplete="url" value="' . esc_attr($website_val) . '" placeholder="' . esc_attr__('https://', 'sikshya') . '" /></div>';
        $out .= '</div>';

        $out .= '<div class="sik-acc-form-actions sik-acc-form-actions--full sik-acc-form-actions--instructor-apply">';
        $out .= '<button type="submit" class="sik-acc-btn sik-acc-btn--primary sik-acc-btn--lg">' . esc_html__('Submit application', 'sikshya') . '</button>';
        $out .= '</div>';
        $out .= '</form></div>';

        return $out;
    }

    public static function handleApply(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(home_url('/')));
            exit;
        }

        $uid = get_current_user_id();
        if ($uid <= 0) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'sikshya_instructor_apply')) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $headline = isset($_POST['headline']) ? sanitize_text_field((string) $_POST['headline']) : '';
        $bio      = isset($_POST['bio']) ? wp_kses_post((string) $_POST['bio']) : '';
        $website  = isset($_POST['website']) ? esc_url_raw((string) $_POST['website']) : '';

        update_user_meta($uid, '_sikshya_instructor_application', [
            'headline' => $headline,
            'bio'      => $bio,
            'website'  => $website,
        ]);
        update_user_meta($uid, '_sikshya_instructor_status', 'pending');
        update_user_meta($uid, '_sikshya_instructor_applied_at', gmdate('c'));

        /**
         * Fires when a learner submits an instructor application.
         */
        do_action('sikshya_instructor_application_submitted', $uid, [
            'headline' => $headline,
            'bio'      => $bio,
            'website'  => $website,
        ]);

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = home_url('/');
        }
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * @param string[] $views
     * @return string[]
     */
    public static function registerView($views): array
    {
        $views = is_array($views) ? $views : [];

        $uid = get_current_user_id();
        if ($uid <= 0) {
            return $views;
        }

        // Only show apply flow to non-instructors.
        if (InstructorContext::isInstructor($uid)) {
            return $views;
        }

        $views[] = self::VIEW_SLUG;
        return array_values(array_unique($views));
    }

    /**
     * @param array<string, mixed> $acc
     * @return array<string, mixed>
     */
    public static function inject($acc): array
    {
        if (!is_array($acc)) {
            return [];
        }

        $uid = (int) ($acc['user_id'] ?? 0);
        if ($uid <= 0) {
            return $acc;
        }

        if (!isset($acc['urls']) || !is_array($acc['urls'])) {
            $acc['urls'] = [];
        }

        $acc['urls']['account_instructor_apply'] = PublicPageUrls::accountViewUrl(self::VIEW_SLUG);
        $acc['instructor_application'] = [
            'status' => (string) get_user_meta($uid, '_sikshya_instructor_status', true),
            'submitted_at' => (string) get_user_meta($uid, '_sikshya_instructor_applied_at', true),
        ];

        return $acc;
    }

    /**
     * @param string               $path
     * @param string               $view
     * @param array<string, mixed> $acc
     */
    public static function overrideViewTemplate($path, $view, $acc): string
    {
        if ($view !== self::VIEW_SLUG) {
            return is_string($path) ? $path : '';
        }

        $candidate = Plugin::getInstance()->getTemplatePath('partials/account-view-instructor-apply.php');
        if (is_readable($candidate)) {
            return $candidate;
        }

        return is_string($path) ? $path : '';
    }

    /**
     * @param array<string, mixed> $acc
     */
    public static function renderSidebarNav($acc, string $view): void
    {
        if (!is_array($acc) || empty($acc['user_id'])) {
            return;
        }

        $uid = (int) $acc['user_id'];
        if ($uid <= 0) {
            return;
        }
        if (InstructorContext::isInstructor($uid)) {
            return;
        }

        $url = is_array($acc['urls'] ?? null) ? (string) ($acc['urls']['account_instructor_apply'] ?? '') : '';
        if ($url === '') {
            return;
        }

        echo '<p class="sik-acc-nav__label">' . esc_html__('Teaching', 'sikshya') . '</p>';
        echo '<a class="' . ($view === self::VIEW_SLUG ? 'is-active' : '') . '" href="' . esc_url($url) . '">';
        echo '<span class="sik-acc-nav__icon" aria-hidden="true">★</span>';
        echo esc_html__('Apply to become an instructor', 'sikshya');
        echo '</a>';
    }
}

