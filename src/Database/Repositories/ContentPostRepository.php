<?php

/**
 * Lesson / quiz / assignment posts (curriculum content items).
 *
 * @package Sikshya\Database\Repositories
 */

namespace Sikshya\Database\Repositories;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\Contracts\RepositoryInterface;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class ContentPostRepository implements RepositoryInterface
{
    /**
     * Map builder type string to post type constant.
     */
    public function resolvePostType(string $content_type): string
    {
        switch ($content_type) {
            case 'quiz':
                return PostTypes::QUIZ;
            case 'assignment':
                return PostTypes::ASSIGNMENT;
            case 'lesson':
            default:
                return PostTypes::LESSON;
        }
    }

    /**
     * @return int|\WP_Error
     */
    public function insert(string $post_type, string $title, string $content, string $status = 'publish')
    {
        return wp_insert_post(
            [
                'post_title' => sanitize_text_field($title),
                'post_content' => wp_kses_post($content),
                'post_type' => $post_type,
                'post_status' => sanitize_key($status),
            ],
            true
        );
    }

    public function updateCore(int $id, string $title, string $content): bool
    {
        $r = wp_update_post(
            [
                'ID' => $id,
                'post_title' => sanitize_text_field($title),
                'post_content' => wp_kses_post($content),
            ],
            true
        );

        return !is_wp_error($r);
    }

    public function findById(int $id): ?\WP_Post
    {
        $post = get_post($id);
        if (!$post) {
            return null;
        }

        $allowed = [PostTypes::LESSON, PostTypes::QUIZ, PostTypes::ASSIGNMENT];
        return in_array($post->post_type, $allowed, true) ? $post : null;
    }
}
