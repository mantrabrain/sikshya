<?php

namespace Sikshya\Services;

/**
 * Stable public ids for Learn player items (lesson/quiz/assignment).
 *
 * Stored on the post as `_sikshya_learn_public_id`.
 *
 * @package Sikshya\Services
 */
final class LearnPublicIdService
{
    private const META_KEY = '_sikshya_learn_public_id';

    /**
     * Get or create a stable public id for a post.
     */
    public static function forPost(int $post_id): string
    {
        if ($post_id <= 0) {
            return '';
        }

        $existing = (string) get_post_meta($post_id, self::META_KEY, true);
        $existing = self::sanitize($existing);
        if ($existing !== '') {
            return $existing;
        }

        // Generate and persist (best-effort).
        $id = self::generate();
        if ($id === '') {
            return '';
        }

        update_post_meta($post_id, self::META_KEY, $id);

        return $id;
    }

    /**
     * Resolve a post id by public id (scoped by post type).
     */
    public static function postIdFromPublicId(string $public_id, string $post_type): int
    {
        $public_id = self::sanitize($public_id);
        $post_type = sanitize_key($post_type);
        if ($public_id === '' || $post_type === '') {
            return 0;
        }

        $q = new \WP_Query(
            [
                'post_type' => $post_type,
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => 1,
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
                'meta_query' => [
                    [
                        'key' => self::META_KEY,
                        'value' => $public_id,
                        'compare' => '=',
                    ],
                ],
            ]
        );

        $ids = is_array($q->posts) ? $q->posts : [];
        $id  = isset($ids[0]) ? (int) $ids[0] : 0;

        wp_reset_postdata();

        return $id;
    }

    /**
     * Sanitize a public id for use in URLs.
     */
    public static function sanitizeForUrl(string $public_id): string
    {
        return self::sanitize($public_id);
    }

    private static function generate(): string
    {
        // Short, URL-safe public key. Not sequential; not the DB ID.
        // 10 chars base62-like is enough for uniqueness at plugin scale.
        $raw = wp_generate_password(12, false, false);
        $raw = preg_replace('/[^A-Za-z0-9]/', '', (string) $raw);
        $raw = (string) $raw;

        return substr($raw, 0, 10);
    }

    private static function sanitize(string $public_id): string
    {
        $public_id = preg_replace('/[^A-Za-z0-9]/', '', $public_id);
        $public_id = (string) $public_id;

        return substr($public_id, 0, 32);
    }
}

