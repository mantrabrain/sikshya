<?php

namespace Sikshya\Services;

/**
 * Virtual frontend routes (cart, checkout, account, learn, order) and permalink option keys.
 *
 * Pretty permalinks: /{slug}/ via rewrite rules. Plain: ?sikshya_page=cart etc.
 * Account subpages: /{account_slug}/{view}/ (see {@see PermalinkService::ACCOUNT_VIEW_VAR}); flush rewrite rules after changes.
 *
 * @package Sikshya\Services
 */
final class PermalinkService
{
    public const QUERY_VAR = 'sikshya_page';
    /**
     * Pretty URL: /{course-category-base}/ → lists all course category terms (virtual route).
     */
    public const COURSE_CATEGORY_ROOT_VAR = 'sikshya_course_category_root';
    public const INSTRUCTOR_VAR = 'sikshya_instructor';
    /** Sub-view for the virtual account page (dashboard, learning, payments, …). */
    public const ACCOUNT_VIEW_VAR = 'sikshya_account_view';
    public const LEARN_TYPE_VAR = 'sikshya_learn_type';
    public const LEARN_SLUG_VAR = 'sikshya_learn_slug';
    public const LEARN_PUBLIC_ID_VAR = 'sikshya_learn_public_id';

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
            // Avoid `/account/` collisions with Yatra (same default); existing installs keep saved slug.
            'permalink_account' => 'my-learning',
            'permalink_learn' => 'learn',
            'permalink_login' => 'login',
            'permalink_order' => 'order',
            'rewrite_base_course' => 'courses',
            'rewrite_base_lesson' => 'lessons',
            'rewrite_base_quiz' => 'quizzes',
            'rewrite_base_assignment' => 'assignments',
            'rewrite_base_certificate' => 'certificates',
            'rewrite_base_author' => 'author',
            'rewrite_tax_course_category' => 'course-category',
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
            $stored = Settings::get($key, null);
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
        return '' === (string) Settings::getRaw('permalink_structure');
    }

    /**
     * Public URL for a virtual Sikshya page (cart, checkout, account, learn, order).
     */
    public static function virtualPageUrl(string $page): string
    {
        $allowed = [ 'cart', 'checkout', 'account', 'learn', 'login', 'order' ];
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
        // Run before the main query is built so virtual routes win over duplicate WP Pages.
        add_filter('request', [ self::class, 'filterRequestResolveAccountVirtualPage' ], 0, 1);
        add_action('init', [ self::class, 'registerRewriteRules' ], 20);
        add_filter('pre_handle_404', [ self::class, 'filterPreHandle404' ], 10, 2);

        // Auto-flush rewrite rules when *any* Sikshya permalink setting changes.
        // (Only keys managed by this service; avoids flushing on unrelated settings.)
        add_action('updated_option', [ self::class, 'maybeFlushOnUpdatedOption' ], 10, 3);
    }

    /**
     * @param string $option
     * @param mixed  $old_value
     * @param mixed  $value
     */
    public static function maybeFlushOnUpdatedOption(string $option, $old_value, $value): void
    {
        if (strpos($option, Settings::PREFIX) !== 0) {
            return;
        }
        $key = substr($option, strlen(Settings::PREFIX));
        if ($key === false || $key === '') {
            return;
        }
        $key = sanitize_key((string) $key);

        // Only flush when one of our permalink keys changes.
        $defaults = self::defaults();
        if (!isset($defaults[$key])) {
            return;
        }

        self::flushRewritesOnChange($old_value, $value);
    }

    /**
     * @param mixed $old_value
     * @param mixed $value
     */
    public static function flushRewritesOnChange($old_value, $value): void
    {
        $old = self::sanitizeSlug((string) $old_value);
        $new = self::sanitizeSlug((string) $value);
        if ($old === $new) {
            return;
        }

        static $flushed = false;
        if ($flushed) {
            return;
        }
        $flushed = true;

        // Ensure rewrite rules are registered for the new values before flushing.
        add_action(
            'init',
            static function (): void {
                flush_rewrite_rules(false);
            },
            100
        );
    }

    /**
     * @param string[] $vars
     * @return string[]
     */
    public static function filterQueryVars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        $vars[] = self::COURSE_CATEGORY_ROOT_VAR;
        $vars[] = self::INSTRUCTOR_VAR;
        $vars[] = self::ACCOUNT_VIEW_VAR;
        $vars[] = self::LEARN_TYPE_VAR;
        $vars[] = self::LEARN_SLUG_VAR;
        $vars[] = self::LEARN_PUBLIC_ID_VAR;

        return $vars;
    }

    /**
     * If a published WordPress Page uses the same slug as the configured Sikshya account URL,
     * core can match that page and render an empty theme template — a blank
     * screen when the page has no blocks. Remap the request to Sikshya's virtual account route.
     *
     * @param array<string, mixed> $query_vars Public query variables for the main request.
     * @return array<string, mixed>
     */
    public static function filterRequestResolveAccountVirtualPage(array $query_vars): array
    {
        if (!empty($query_vars[self::QUERY_VAR])) {
            return $query_vars;
        }

        $p = self::get();
        $account_slug = self::sanitizeSlug($p['permalink_account'] ?? self::defaults()['permalink_account']);
        if ($account_slug === '') {
            return $query_vars;
        }

        $pagename = isset($query_vars['pagename']) ? (string) $query_vars['pagename'] : '';
        if ($pagename !== '' && $pagename === $account_slug) {
            return self::stripConflictingPageVarsAndSetVirtualAccount($query_vars);
        }

        return $query_vars;
    }

    /**
     * @param array<string, mixed> $query_vars
     * @return array<string, mixed>
     */
    private static function stripConflictingPageVarsAndSetVirtualAccount(array $query_vars): array
    {
        foreach (['pagename', 'page', 'page_id', 'attachment', 'error'] as $k) {
            if (array_key_exists($k, $query_vars)) {
                unset($query_vars[$k]);
            }
        }
        $query_vars[self::QUERY_VAR] = 'account';

        return $query_vars;
    }

    /**
     * Whether Learn URLs should include a stable public id segment.
     *
     * @example /learn/lesson/{public_id}/{slug}
     */
    public static function learnUsePublicId(): bool
    {
        $v = Settings::get('learn_permalink_use_public_id', '1');

        return (string) $v === '1';
    }

    public static function registerRewriteRules(): void
    {
        if (self::isPlainPermalinks()) {
            return;
        }

        $p = self::get();

        // Account: subpaths first (/my-learning/learning/), then base (/my-learning/).
        $account_slug = self::sanitizeSlug($p['permalink_account'] ?? self::defaults()['permalink_account']);
        $account_re   = preg_quote($account_slug, '/');
        add_rewrite_rule(
            '^' . $account_re . '/([^/]+)/?$',
            'index.php?' . self::QUERY_VAR . '=account&' . self::ACCOUNT_VIEW_VAR . '=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^' . $account_re . '/?$',
            'index.php?' . self::QUERY_VAR . '=account',
            'top'
        );

        foreach (['cart', 'checkout', 'learn', 'login', 'order'] as $page) {
            $slug = $p['permalink_' . $page] ?? $page;
            $slug = self::sanitizeSlug($slug);
            $re   = preg_quote($slug, '/');
            add_rewrite_rule(
                '^' . $re . '/?$',
                'index.php?' . self::QUERY_VAR . '=' . $page,
                'top'
            );
        }

        // Learn player routes (distraction-free UI):
        // /learn/lesson/{lesson-slug}
        // /learn/quiz/{quiz-slug}
        // /learn/assignment/{assignment-slug}
        $learn_slug = self::sanitizeSlug($p['permalink_learn'] ?? 'learn');
        $learn_re   = preg_quote($learn_slug, '/');

        // New: /learn/{type}/{public_id}/{slug}
        if (self::learnUsePublicId()) {
            add_rewrite_rule(
                '^' . $learn_re . '/(lesson|quiz|assignment)/([^/]+)/([^/]+)/?$',
                'index.php?' . self::QUERY_VAR . '=learn&' . self::LEARN_TYPE_VAR . '=$matches[1]&' . self::LEARN_PUBLIC_ID_VAR . '=$matches[2]&' . self::LEARN_SLUG_VAR . '=$matches[3]',
                'top'
            );
        }

        // Legacy: /learn/{type}/{slug}
        add_rewrite_rule(
            '^' . $learn_re . '/(lesson|quiz|assignment)/([^/]+)/?$',
            'index.php?' . self::QUERY_VAR . '=learn&' . self::LEARN_TYPE_VAR . '=$matches[1]&' . self::LEARN_SLUG_VAR . '=$matches[2]',
            'top'
        );

        // Sikshya instructor archive (separate from core WP author archives).
        $author_base = self::sanitizeSlug($p['rewrite_base_author'] ?? 'author');
        $author_re   = preg_quote($author_base, '/');
        add_rewrite_rule(
            '^' . $author_re . '/([^/]+)/?$',
            'index.php?' . self::INSTRUCTOR_VAR . '=$matches[1]',
            'top'
        );

        // Course category taxonomy base alone (e.g. /course-category/) — term archives use /base/term-slug/.
        $cat_base = self::sanitizeSlug($p['rewrite_tax_course_category'] ?? self::defaults()['rewrite_tax_course_category']);
        $cat_re   = preg_quote($cat_base, '/');
        add_rewrite_rule(
            '^' . $cat_re . '/?$',
            'index.php?' . self::COURSE_CATEGORY_ROOT_VAR . '=1',
            'top'
        );
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
        $instructor = $wp_query->get(self::INSTRUCTOR_VAR);
        if (!empty($instructor)) {
            return true;
        }

        if ((string) $wp_query->get(self::COURSE_CATEGORY_ROOT_VAR) === '1') {
            return true;
        }

        return $preempt;
    }
}
