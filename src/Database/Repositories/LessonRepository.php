<?php

namespace Sikshya\Database\Repositories;

use WP_Query;

class LessonRepository
{
    public function findAll(array $args = []): array
    {
        $defaults = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ];
        
        $query_args = wp_parse_args($args, $defaults);
        $query = new WP_Query($query_args);
        
        return $query->posts;
    }

    public function findById(int $id): ?object
    {
        $post = get_post($id);
        return ($post && $post->post_type === 'sikshya_lesson') ? $post : null;
    }

    public function create(array $data): int
    {
        $post_data = [
            'post_type' => 'sikshya_lesson',
            'post_title' => sanitize_text_field($data['title'] ?? ''),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_status' => $data['status'] ?? 'draft',
            'post_author' => $data['author_id'] ?? get_current_user_id(),
            'menu_order' => $data['order'] ?? 0,
        ];

        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            return 0;
        }

        // Set custom meta fields
        $this->setMetaFields($post_id, $data);
        
        return $post_id;
    }

    public function update(int $id, array $data): bool
    {
        $post_data = [
            'ID' => $id,
            'post_title' => sanitize_text_field($data['title'] ?? ''),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_status' => $data['status'] ?? 'draft',
            'menu_order' => $data['order'] ?? 0,
        ];

        $result = wp_update_post($post_data, true);
        
        if (is_wp_error($result)) {
            return false;
        }

        // Update custom meta fields
        $this->setMetaFields($id, $data);
        
        return true;
    }

    public function delete(int $id): bool
    {
        $result = wp_delete_post($id, true);
        return $result !== false;
    }

    public function findByCourse(int $course_id, array $args = []): array
    {
        $defaults = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => '_sikshya_course_id',
                    'value' => $course_id,
                    'compare' => '=',
                ],
            ],
        ];
        
        $query_args = wp_parse_args($args, $defaults);
        $query = new WP_Query($query_args);
        
        return $query->posts;
    }

    public function findByType(string $type, array $args = []): array
    {
        $defaults = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => '_sikshya_lesson_type',
                    'value' => $type,
                    'compare' => '=',
                ],
            ],
        ];
        
        $query_args = wp_parse_args($args, $defaults);
        $query = new WP_Query($query_args);
        
        return $query->posts;
    }

    public function findByStatus(string $status, array $args = []): array
    {
        $defaults = [
            'post_type' => 'sikshya_lesson',
            'post_status' => $status,
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ];
        
        $query_args = wp_parse_args($args, $defaults);
        $query = new WP_Query($query_args);
        
        return $query->posts;
    }

    public function search(string $search_term, array $args = []): array
    {
        $defaults = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            's' => $search_term,
        ];
        
        $query_args = wp_parse_args($args, $defaults);
        $query = new WP_Query($query_args);
        
        return $query->posts;
    }

    public function findNextByCourse(int $course_id, int $current_order): ?object
    {
        $args = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => '_sikshya_course_id',
                    'value' => $course_id,
                    'compare' => '=',
                ],
            ],
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_sikshya_course_id',
                    'value' => $course_id,
                    'compare' => '=',
                ],
                [
                    'key' => '_sikshya_order',
                    'value' => $current_order,
                    'compare' => '>',
                ],
            ],
        ];
        
        $query = new WP_Query($args);
        return !empty($query->posts) ? $query->posts[0] : null;
    }

    public function findPreviousByCourse(int $course_id, int $current_order): ?object
    {
        $args = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'menu_order',
            'order' => 'DESC',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_sikshya_course_id',
                    'value' => $course_id,
                    'compare' => '=',
                ],
                [
                    'key' => '_sikshya_order',
                    'value' => $current_order,
                    'compare' => '<',
                ],
            ],
        ];
        
        $query = new WP_Query($args);
        return !empty($query->posts) ? $query->posts[0] : null;
    }

    private function setMetaFields(int $post_id, array $data): void
    {
        // Course ID
        if (isset($data['course_id'])) {
            update_post_meta($post_id, '_sikshya_course_id', intval($data['course_id']));
        }

        // Lesson type
        if (isset($data['type'])) {
            update_post_meta($post_id, '_sikshya_lesson_type', sanitize_text_field($data['type']));
        }

        // Media URL
        if (isset($data['media_url'])) {
            update_post_meta($post_id, '_sikshya_media_url', esc_url_raw($data['media_url']));
        }

        // Duration
        if (isset($data['duration'])) {
            update_post_meta($post_id, '_sikshya_duration', intval($data['duration']));
        }

        // Order
        if (isset($data['order'])) {
            update_post_meta($post_id, '_sikshya_order', intval($data['order']));
        }

        // Is free
        if (isset($data['is_free'])) {
            update_post_meta($post_id, '_sikshya_is_free', (bool) $data['is_free']);
        }
    }

    public function getMeta(int $post_id, string $key, bool $single = true)
    {
        return get_post_meta($post_id, '_sikshya_' . $key, $single);
    }

    public function setMeta(int $post_id, string $key, $value): bool
    {
        return update_post_meta($post_id, '_sikshya_' . $key, $value);
    }

    public function deleteMeta(int $post_id, string $key): bool
    {
        return delete_post_meta($post_id, '_sikshya_' . $key);
    }
} 