<?php

namespace Sikshya\Services;

/**
 * Virtual frontend routes (cart, checkout, account, learn, order) and permalink option keys.
 *
 * Pretty permalinks: /{slug}/ via rewrite rules. Plain: ?sikshya_page=cart etc.
 *
 * @package Sikshya\Services
 */
final class PermalinkService
{
    public const QUERY_VAR = 'sikshya_page';

    /**
     * Option keys (stored as _sikshya_{key}).
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'permalink_cart' => 'cart',
            'permalink_checkout' => 'checkout',
            'permalink_account' => 'account',
            'permalink_learn' => 'learn',
            'permalink_order' => 'order',
            'rewrite_base_course' => 'courses',
            'rewrite_base_lesson' => 'lessons',
            'rewrite_base_quiz' => 'quizzes',
            'rewrite_base_assignment' => 'assignments',
            'rewrite_base_certificate' => 'certificates',
            'rewrite_tax_course_category' => 'course-category',
            'rewrite_tax_course_tag' => 'course-tag',
        ];
    }

    /**
     * Merged saved + default permalink settings.
     *
     * @return array<string, string>
     */
    public static function get(): array
    {
        $out = [];
        foreach (self::defaults() as $key => $default) {
            $stored = get_option('_sikshya_' . $key, null);
            if ($stored === null || $stored === '') {
                $out[ $key ] = $default;
            } else {
                $out[ $key ] = self::sanitizeSlug((string) $stored);
            }
        }

        return $out;
    }

    public static function sanitizeSlug(string $slug): string
    {
        $slug = sanitize_title($slug);

        return $slug !== '' ? $slug : 'sikshya';
    }

    public static function isPlainPermalinks(): bool
    {
        return '' === (string) get_option('permalink_structure');
    }

    /**
     * Public URL for a virtual Sikshya page (cart, checkout, account, learn, order).
     */
    public static function virtualPageUrl(string $page): string
    {
        $allowed = [ 'cart', 'checkout', 'account', 'learn', 'order' ];
        if (! in_array($page, $allowed, true)) {
            return home_url('/');
        }

        if (self::isPlainPermalinks()) {
            return add_query_arg(self::QUERY_VAR, $page, home_url('/'));
        }

        $p    = self::get();
        $slug = $p[ 'permalink_' . $page ] ?? $page;

        return user_trailingslashit(home_url('/' . $slug . '/'));
    }

    public static function boot(): void
    {
        add_filter('query_vars', [ self::class, 'filterQueryVars' ]);
        add_action('init', [ self::class, 'registerRewriteRules' ], 20);
        add_filter('pre_handle_404', [ self::class, 'filterPreHandle404' ], 10, 2);
    }

    /**
     * @param string[] $vars
     * @return string[]
     */
    public static function filterQueryVars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function registerRewriteRules(): void
    {
        if (self::isPlainPermalinks()) {
            return;
        }

        $p = self::get();
        foreach ( [ 'cart', 'checkout', 'account', 'learn', 'order' ] as $page ) {
            $slug = $p[ 'permalink_' . $page ] ?? $page;
            $slug = self::sanitizeSlug($slug);
            $re   = preg_quote($slug, '/');
            add_rewrite_rule(
                '^' . $re . '/?$',
                'index.php?' . self::QUERY_VAR . '=' . $page,
                'top'
            );
        }
    }

    /**
     * @param mixed $preempt
     */
    public static function filterPreHandle404($preempt, \WP_Query $wp_query)
    {
        $v = $wp_query->get(self::QUERY_VAR);
        if (! empty($v)) {
            return true;
        }

        return $preempt;
    }
}
