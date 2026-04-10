<?php

/**
 * Quiz post persistence (thin wrapper over content posts).
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

class QuizRepository implements RepositoryInterface
{
    public function findById(int $id): ?\WP_Post
    {
        $post = get_post($id);
        return ($post && $post->post_type === PostTypes::QUIZ) ? $post : null;
    }

    /**
     * @return int|\WP_Error
     */
    public function insert(string $title, string $content = '', string $status = 'publish')
    {
        return wp_insert_post(
            [
                'post_title' => sanitize_text_field($title),
                'post_content' => wp_kses_post($content),
                'post_type' => PostTypes::QUIZ,
                'post_status' => sanitize_key($status),
            ],
            true
        );
    }
}
