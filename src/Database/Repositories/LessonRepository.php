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

        // Content type specific meta fields
        $this->setContentTypeMetaFields($post_id, $data);
    }

    /**
     * Set content type specific meta fields
     * 
     * @param int $post_id
     * @param array $data
     * @return void
     */
    private function setContentTypeMetaFields(int $post_id, array $data): void
    {
        $content_type = $data['type'] ?? 'text';

        switch ($content_type) {
            case 'text':
                $this->setTextLessonMeta($post_id, $data);
                break;
            case 'video':
                $this->setVideoLessonMeta($post_id, $data);
                break;
            case 'audio':
                $this->setAudioLessonMeta($post_id, $data);
                break;
            case 'assignment':
                $this->setAssignmentMeta($post_id, $data);
                break;
            case 'quiz':
                $this->setQuizMeta($post_id, $data);
                break;
        }
    }

    /**
     * Set text lesson specific meta fields
     * 
     * @param int $post_id
     * @param array $data
     * @return void
     */
    private function setTextLessonMeta(int $post_id, array $data): void
    {
        $meta_fields = [
            'difficulty', 'objectives', 'takeaways', 'resources', 'completion',
            'comments', 'progress', 'print', 'prerequisites', 'tags', 'seo',
            'format', 'reading_level', 'word_count', 'language', 'toc', 'search', 'related'
        ];

        foreach ($meta_fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if (is_array($value)) {
                    $value = wp_json_encode($value);
                }
                update_post_meta($post_id, '_sikshya_' . $field, $value);
            }
        }
    }

    /**
     * Set video lesson specific meta fields
     * 
     * @param int $post_id
     * @param array $data
     * @return void
     */
    private function setVideoLessonMeta(int $post_id, array $data): void
    {
        $meta_fields = [
            'difficulty', 'video_source', 'video_url', 'video_file', 'video_quality',
            'autoplay', 'show_controls', 'allow_download', 'transcript', 'subtitles'
        ];

        foreach ($meta_fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if (is_array($value)) {
                    $value = wp_json_encode($value);
                }
                update_post_meta($post_id, '_sikshya_' . $field, $value);
            }
        }
    }

    /**
     * Set audio lesson specific meta fields
     * 
     * @param int $post_id
     * @param array $data
     * @return void
     */
    private function setAudioLessonMeta(int $post_id, array $data): void
    {
        $meta_fields = [
            'difficulty', 'audio_source', 'audio_url', 'audio_file', 'audio_quality',
            'autoplay', 'show_controls', 'allow_download', 'transcript'
        ];

        foreach ($meta_fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if (is_array($value)) {
                    $value = wp_json_encode($value);
                }
                update_post_meta($post_id, '_sikshya_' . $field, $value);
            }
        }
    }

    /**
     * Set assignment specific meta fields
     * 
     * @param int $post_id
     * @param array $data
     * @return void
     */
    private function setAssignmentMeta(int $post_id, array $data): void
    {
        $meta_fields = [
            'difficulty', 'instructions', 'objectives', 'criteria', 'submission_type',
            'file_types', 'max_file_size', 'due_date', 'points', 'attempts'
        ];

        foreach ($meta_fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if (is_array($value)) {
                    $value = wp_json_encode($value);
                }
                update_post_meta($post_id, '_sikshya_' . $field, $value);
            }
        }
    }

    /**
     * Set quiz specific meta fields
     * 
     * @param int $post_id
     * @param array $data
     * @return void
     */
    private function setQuizMeta(int $post_id, array $data): void
    {
        $meta_fields = [
            'difficulty', 'time_limit', 'passing_score', 'attempts',
            'randomize_questions', 'show_results', 'show_correct_answers'
        ];

        foreach ($meta_fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if (is_array($value)) {
                    $value = wp_json_encode($value);
                }
                update_post_meta($post_id, '_sikshya_' . $field, $value);
            }
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