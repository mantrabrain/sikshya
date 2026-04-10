<?php

/**
 * Chapter posts (child of course).
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

class ChapterRepository implements RepositoryInterface
{
    /**
     * @return \WP_Post[]
     */
    public function findByCourseId(int $course_id): array
    {
        return get_posts(
            [
                'post_type' => PostTypes::CHAPTER,
                'post_parent' => $course_id,
                'post_status' => 'any',
                'numberposts' => -1,
                'orderby' => 'meta_value_num',
                'meta_key' => '_sikshya_order',
                'order' => 'ASC',
            ]
        );
    }

    public function findById(int $id): ?\WP_Post
    {
        $post = get_post($id);
        return ($post && $post->post_type === PostTypes::CHAPTER) ? $post : null;
    }

    /**
     * @return int|\WP_Error
     */
    public function insert(int $course_id, string $title, string $content = '', string $status = 'publish')
    {
        return wp_insert_post(
            [
                'post_title' => sanitize_text_field($title),
                'post_content' => wp_kses_post($content),
                'post_type' => PostTypes::CHAPTER,
                'post_status' => sanitize_key($status),
                'post_parent' => $course_id,
            ],
            true
        );
    }

    public function update(int $id, array $fields): bool
    {
        $fields['ID'] = $id;
        $r = wp_update_post($fields, true);
        return !is_wp_error($r);
    }

    public function delete(int $id, bool $force = true): bool
    {
        return wp_delete_post($id, $force) !== false;
    }
}
