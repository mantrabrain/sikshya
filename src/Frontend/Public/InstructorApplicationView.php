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

