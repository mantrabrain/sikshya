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

    public static function orderView(int $order_id): string
    {
        return add_query_arg('order_id', $order_id, self::url('order'));
    }
}
