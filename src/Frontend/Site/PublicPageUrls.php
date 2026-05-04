<?php

namespace Sikshya\Frontend\Site;

use Sikshya\Services\PermalinkService;
use Sikshya\Services\LearnPublicIdService;
use Sikshya\Constants\PostTypes;

/**
 * Resolves Sikshya frontend virtual page URLs (cart, checkout, learn, …).
 *
 * @package Sikshya\Frontend\Site
 */
final class PublicPageUrls
{
    /**
     * Core account sections. Extend via {@see 'sikshya_account_allowed_views'} (Pro / addons).
     *
     * @return string[]
     */
    public static function allowedAccountViews(): array
    {
        $core = ['dashboard', 'learning', 'payments', 'quiz-attempts', 'profile'];
        $merged = apply_filters('sikshya_account_allowed_views', $core);
        if (!is_array($merged)) {
            return $core;
        }
        $out = [];
        foreach ($merged as $v) {
            $k = sanitize_key((string) $v);
            if ($k !== '') {
                $out[] = $k;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Raw account section slug from the request (pretty path, plain query var, or legacy `tab`).
     *
     * Legacy `?tab=profile` on `/account/` is supported alongside {@see PermalinkService::ACCOUNT_VIEW_VAR}.
     */
    public static function requestAccountViewRaw(): string
    {
        $raw = (string) get_query_var(PermalinkService::ACCOUNT_VIEW_VAR);
        if ($raw === '' && isset($_GET['tab'])) {
            $raw = (string) wp_unslash($_GET['tab']);
        }

        return sanitize_key($raw);
    }

    /**
     * Current account sub-view (defaults to dashboard).
     */
    public static function currentAccountView(): string
    {
        $v = self::requestAccountViewRaw();

        return in_array($v, self::allowedAccountViews(), true) ? $v : 'dashboard';
    }

    /**
     * URL for a learner account section (pretty: /account/learning/, plain: query args).
     */
    public static function accountViewUrl(string $view): string
    {
        $v = sanitize_key($view);
        if (!in_array($v, self::allowedAccountViews(), true)) {
            $v = 'dashboard';
        }

        $base = PermalinkService::virtualPageUrl('account');
        if (PermalinkService::isPlainPermalinks()) {
            return add_query_arg(PermalinkService::ACCOUNT_VIEW_VAR, $v, $base);
        }

        if ($v === 'dashboard') {
            return $base;
        }

        $base = untrailingslashit($base);

        return user_trailingslashit($base . '/' . rawurlencode($v));
    }

    /**
     * Whether the main request is a Sikshya virtual page (see PermalinkService::QUERY_VAR).
     */
    public static function isCurrentVirtualPage(string $key): bool
    {
        return (string) get_query_var(PermalinkService::QUERY_VAR) === $key;
    }

    public static function url(string $key): string
    {
        return PermalinkService::virtualPageUrl($key);
    }

    /**
     * Sikshya login page (virtual). Uses WordPress auth, but in Sikshya UI.
     *
     * @param string $redirect_to Absolute or relative URL to return to after login.
     */
    public static function login(string $redirect_to = ''): string
    {
        $base = self::url('login');
        $redirect_to = trim((string) $redirect_to);
        if ($redirect_to === '') {
            return $base;
        }

        return add_query_arg('redirect_to', rawurlencode($redirect_to), $base);
    }

    public static function learnForCourse(int $course_id): string
    {
        if ($course_id <= 0) {
            return self::url('learn');
        }

        return add_query_arg('course_id', $course_id, self::url('learn'));
    }

    /**
     * Learn player URL for course content (lesson/quiz/assignment).
     *
     * Pretty permalinks:
     * - when public id enabled: /learn/lesson/{public_id}/{slug}
     * - when disabled:          /learn/lesson/{slug}
     * Plain permalinks:  /?sikshya_page=learn&sikshya_learn_type=lesson&sikshya_learn_slug={slug}
     */
    public static function learnContent(string $type, string $slug, string $public_id = ''): string
    {
        $type = sanitize_key($type);
        $slug = sanitize_title($slug);
        if ($type === '' || $slug === '') {
            return self::url('learn');
        }

        $use_pid = PermalinkService::learnUsePublicId();
        if ($use_pid) {
            $public_id = LearnPublicIdService::sanitizeForUrl($public_id);
        } else {
            $public_id = '';
        }

        if (PermalinkService::isPlainPermalinks()) {
            $args = [
                PermalinkService::QUERY_VAR => 'learn',
                PermalinkService::LEARN_TYPE_VAR => $type,
                PermalinkService::LEARN_SLUG_VAR => $slug,
            ];
            if ($use_pid && $public_id !== '') {
                $args[PermalinkService::LEARN_PUBLIC_ID_VAR] = $public_id;
            }

            return add_query_arg($args, home_url('/'));
        }

        $base = untrailingslashit(self::url('learn'));

        if ($use_pid && $public_id !== '') {
            return user_trailingslashit($base . '/' . rawurlencode($type) . '/' . rawurlencode($public_id) . '/' . rawurlencode($slug));
        }

        return user_trailingslashit($base . '/' . rawurlencode($type) . '/' . rawurlencode($slug));
    }

    /**
     * Learn player URL for a concrete content post.
     */
    public static function learnContentForPost(\WP_Post $p): string
    {
        $type = '';
        if ($p->post_type === PostTypes::LESSON) {
            $type = 'lesson';
        } elseif ($p->post_type === PostTypes::QUIZ) {
            $type = 'quiz';
        } elseif ($p->post_type === PostTypes::ASSIGNMENT) {
            $type = 'assignment';
        }

        $slug = $p->post_name ?: sanitize_title((string) $p->post_title);
        $pid  = LearnPublicIdService::forPost((int) $p->ID);

        return self::learnContent($type, $slug, $pid);
    }

    /**
     * Receipt URL using opaque 32-char hex token (not sequential order ID).
     */
    public static function orderView(string $public_token): string
    {
        $t = \Sikshya\Database\Repositories\OrderRepository::sanitizePublicToken($public_token);
        if ($t === '') {
            return self::url('order');
        }

        return add_query_arg('order_key', 'SIK-ORD-' . $t, self::url('order'));
    }

    /**
     * Printable invoice URL (same bearer token rules as {@see self::orderView()}).
     */
    public static function orderInvoiceView(string $public_token): string
    {
        $base = self::orderView($public_token);
        if ($base === self::url('order')) {
            return $base;
        }

        return add_query_arg('invoice', '1', $base);
    }
}
