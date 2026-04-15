<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Services\PermalinkService;

/**
 * Resolves Sikshya frontend virtual page URLs (cart, checkout, learn, …).
 *
 * @package Sikshya\Frontend\Public
 */
final class PublicPageUrls
{
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
     * Pretty permalinks: /learn/lesson/{slug}
     * Plain permalinks:  /?sikshya_page=learn&sikshya_learn_type=lesson&sikshya_learn_slug={slug}
     */
    public static function learnContent(string $type, string $slug): string
    {
        $type = sanitize_key($type);
        $slug = sanitize_title($slug);
        if ($type === '' || $slug === '') {
            return self::url('learn');
        }

        if (PermalinkService::isPlainPermalinks()) {
            return add_query_arg(
                [
                    PermalinkService::QUERY_VAR => 'learn',
                    PermalinkService::LEARN_TYPE_VAR => $type,
                    PermalinkService::LEARN_SLUG_VAR => $slug,
                ],
                home_url('/')
            );
        }

        $base = untrailingslashit(self::url('learn'));

        return user_trailingslashit($base . '/' . rawurlencode($type) . '/' . rawurlencode($slug));
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

        return add_query_arg('order_key', $t, self::url('order'));
    }
}
