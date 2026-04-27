<?php

namespace Sikshya\Shortcodes;

/**
 * Shortcode: [sikshya_instructor_registration]
 *
 * Renders a secure "apply for instructor" form for logged-in learners.
 *
 * @package Sikshya\Shortcodes
 */
final class InstructorRegistrationShortcode
{
    private static bool $registered = false;

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('init', [self::class, 'register']);
        add_action('admin_post_sikshya_instructor_apply', [self::class, 'handleApply']);
        add_action('admin_post_nopriv_sikshya_instructor_apply', [self::class, 'handleApply']);
    }

    public static function register(): void
    {
        add_shortcode('sikshya_instructor_registration', [self::class, 'render']);
    }

    /**
     * @param array<string, mixed> $atts
     */
    public static function render($atts = []): string
    {
        if (!is_user_logged_in()) {
            $login = wp_login_url((string) (is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : home_url('/')));
            return '<div class="sikshya-card"><p>' . esc_html__('Please log in to apply as an instructor.', 'sikshya') . '</p><p><a class="sikshya-btn sikshya-btn--primary" href="' . esc_url($login) . '">' . esc_html__('Log in', 'sikshya') . '</a></p></div>';
        }

        $uid = get_current_user_id();
        $status = (string) get_user_meta($uid, '_sikshya_instructor_status', true);
        $submitted_at = (string) get_user_meta($uid, '_sikshya_instructor_applied_at', true);

        $out = '';

        $out .= '<div class="sikshya-card">';
        $out .= '<h3 style="margin:0 0 0.25rem;">' . esc_html__('Apply to become an instructor', 'sikshya') . '</h3>';
        $out .= '<p style="margin:0 0 1rem;opacity:.85;">' . esc_html__('Tell us a bit about your expertise. An admin will review your application.', 'sikshya') . '</p>';

        if ($status === 'pending') {
            $out .= '<div class="sikshya-notice sikshya-notice--info" style="margin:0 0 1rem;">' . esc_html__('Your application is pending review.', 'sikshya') . '</div>';
        } elseif ($status === 'inactive' || $status === 'rejected') {
            $out .= '<div class="sikshya-notice sikshya-notice--warning" style="margin:0 0 1rem;">' . esc_html__('Your application was not approved. You can update and submit again.', 'sikshya') . '</div>';
        } elseif ($status === 'active') {
            $out .= '<div class="sikshya-notice sikshya-notice--success" style="margin:0 0 1rem;">' . esc_html__('You are an instructor.', 'sikshya') . '</div>';
        }

        if ($submitted_at !== '') {
            $ts = strtotime($submitted_at);
            $when = $ts ? wp_date(get_option('date_format'), $ts) : $submitted_at;
            $out .= '<p style="margin:0 0 1rem;opacity:.75;">' . esc_html(sprintf(__('Last submitted: %s', 'sikshya'), $when)) . '</p>';
        }

        $action = esc_url(admin_url('admin-post.php'));
        $out .= '<form method="post" action="' . $action . '">';
        $out .= '<input type="hidden" name="action" value="sikshya_instructor_apply" />';
        $out .= wp_nonce_field('sikshya_instructor_apply', '_wpnonce', true, false);

        $out .= '<p style="margin:0 0 0.75rem;"><label style="display:block;font-weight:600;margin:0 0 0.25rem;">' . esc_html__('Headline', 'sikshya') . '</label>';
        $out .= '<input name="headline" type="text" class="sikshya-input" placeholder="' . esc_attr__('e.g. Web developer & WordPress trainer', 'sikshya') . '" style="width:100%;" /></p>';

        $out .= '<p style="margin:0 0 0.75rem;"><label style="display:block;font-weight:600;margin:0 0 0.25rem;">' . esc_html__('Short bio', 'sikshya') . '</label>';
        $out .= '<textarea name="bio" class="sikshya-input" rows="5" placeholder="' . esc_attr__('What will you teach? Share your experience and approach.', 'sikshya') . '" style="width:100%;"></textarea></p>';

        $out .= '<p style="margin:0 0 0.75rem;"><label style="display:block;font-weight:600;margin:0 0 0.25rem;">' . esc_html__('Website (optional)', 'sikshya') . '</label>';
        $out .= '<input name="website" type="url" class="sikshya-input" placeholder="' . esc_attr__('https://', 'sikshya') . '" style="width:100%;" /></p>';

        $out .= '<p style="margin:0;"><button type="submit" class="sikshya-btn sikshya-btn--primary">' . esc_html__('Submit application', 'sikshya') . '</button></p>';
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
        $bio = isset($_POST['bio']) ? wp_kses_post((string) $_POST['bio']) : '';
        $website = isset($_POST['website']) ? esc_url_raw((string) $_POST['website']) : '';

        update_user_meta($uid, '_sikshya_instructor_application', [
            'headline' => $headline,
            'bio' => $bio,
            'website' => $website,
        ]);
        update_user_meta($uid, '_sikshya_instructor_status', 'pending');
        update_user_meta($uid, '_sikshya_instructor_applied_at', gmdate('c'));

        /**
         * Fires when a learner submits an instructor application.
         *
         */
        do_action('sikshya_instructor_application_submitted', $uid, [
            'headline' => $headline,
            'bio' => $bio,
            'website' => $website,
        ]);

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = home_url('/');
        }
        wp_safe_redirect($redirect);
        exit;
    }
}

