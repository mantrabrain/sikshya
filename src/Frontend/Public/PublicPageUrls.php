<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Services\PermalinkService;
use Sikshya\Services\LearnPublicIdService;
use Sikshya\Constants\PostTypes;

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

        return add_query_arg('order_key', $t, self::url('order'));
    }
}
