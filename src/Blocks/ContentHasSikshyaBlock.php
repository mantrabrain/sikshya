<?php

namespace Sikshya\Blocks;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Detect Sikshya blocks/shortcodes in post content (for asset loading).
 *
 * @package Sikshya\Blocks
 */
final class ContentHasSikshyaBlock
{
    public static function hasCoursesListing(?\WP_Post $post = null): bool
    {
        return self::hasBlock('sikshya/courses', $post)
            || self::hasShortcode('sikshya_courses', $post);
    }

    public static function hasLogin(?\WP_Post $post = null): bool
    {
        return self::hasBlock('sikshya/login', $post)
            || self::hasShortcode('sikshya_login', $post);
    }

    public static function hasRegistration(?\WP_Post $post = null): bool
    {
        return self::hasBlock('sikshya/registration', $post)
            || self::hasShortcode('sikshya_registration', $post);
    }

    public static function hasAuth(?\WP_Post $post = null): bool
    {
        return self::hasLogin($post) || self::hasRegistration($post);
    }

    private static function hasBlock(string $block_name, ?\WP_Post $post): bool
    {
        if (!function_exists('has_block')) {
            return false;
        }

        $post = self::resolvePost($post);
        if (!$post instanceof \WP_Post) {
            return false;
        }

        return has_block($block_name, $post);
    }

    private static function hasShortcode(string $tag, ?\WP_Post $post): bool
    {
        $post = self::resolvePost($post);
        if (!$post instanceof \WP_Post) {
            return false;
        }

        return function_exists('has_shortcode') && has_shortcode($post->post_content, $tag);
    }

    private static function resolvePost(?\WP_Post $post): ?\WP_Post
    {
        if ($post instanceof \WP_Post) {
            return $post;
        }

        global $post;
        if ($post instanceof \WP_Post) {
            return $post;
        }

        $id = get_queried_object_id();
        if ($id > 0) {
            $loaded = get_post($id);

            return $loaded instanceof \WP_Post ? $loaded : null;
        }

        return null;
    }
}
