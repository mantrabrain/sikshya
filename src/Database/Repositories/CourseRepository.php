<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\Contracts\RepositoryInterface;
use WP_Query;

class CourseRepository implements RepositoryInterface
{
    public function findAll(array $args = []): array
    {
        $query = $this->queryCourses($args);

        return $query->posts;
    }

    /**
     * Run a course listing query (caller may read {@see WP_Query::found_posts} for pagination).
     */
    public function queryCourses(array $args = []): WP_Query
    {
        $defaults = [
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query_args = wp_parse_args($args, $defaults);

        return new WP_Query($query_args);
    }

    public function findById(int $id): ?object
    {
        $post = get_post($id);
        return ($post && $post->post_type === PostTypes::COURSE) ? $post : null;
    }

    public function create(array $data): int
    {
        $post_data = [
            'post_type' => PostTypes::COURSE,
            'post_title' => sanitize_text_field($data['title'] ?? ''),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_excerpt' => sanitize_textarea_field($data['excerpt'] ?? ''),
            'post_status' => $data['status'] ?? 'draft',
            'post_author' => $data['author_id'] ?? get_current_user_id(),
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
            'post_excerpt' => sanitize_textarea_field($data['excerpt'] ?? ''),
            'post_status' => $data['status'] ?? 'draft',
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

    /**
     * Whether the ID refers to a Sikshya course post.
     */
    public function isCourse(int $id): bool
    {
        $post = get_post($id);
        return $post !== null && $post->post_type === PostTypes::COURSE;
    }

    /**
     * Insert a course post from builder fields (title + HTML description).
     *
     * @return int|\WP_Error Post ID or error.
     */
    public function insertFromBuilder(string $title, string $description_html, string $post_status)
    {
        return wp_insert_post(
            [
                'post_title' => sanitize_text_field($title),
                'post_content' => wp_kses_post($description_html),
                'post_type' => PostTypes::COURSE,
                'post_status' => sanitize_key($post_status),
            ],
            true
        );
    }

    /**
     * Create a draft course directly via wp_insert_post (React “New course” modal).
     *
     * Avoids POST /wp/v2/sik_course — WordPress core REST create_item() has a long-standing bug
     * when combining draft status + explicit slug (reads `$prepared_post->id` vs `ID`).
     *
     * @return int|\WP_Error Post ID or error.
     */
    public function insertDraftFromModal(string $title, string $slug = '')
    {
        $postarr = [
            'post_title' => sanitize_text_field($title),
            'post_content' => '',
            'post_type' => PostTypes::COURSE,
            'post_status' => 'draft',
        ];
        $slug = trim($slug);
        if ($slug !== '') {
            $postarr['post_name'] = sanitize_title($slug);
        }

        return wp_insert_post($postarr, true);
    }

    /**
     * Update only post_status for an existing course row.
     */
    public function updatePostStatus(int $id, string $post_status): bool
    {
        $result = wp_update_post(
            [
                'ID' => $id,
                'post_status' => sanitize_key($post_status),
            ],
            true
        );

        return !is_wp_error($result);
    }

    public function findByInstructor(int $instructor_id, array $args = []): array
    {
        $defaults = [
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'author' => $instructor_id,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query_args = wp_parse_args($args, $defaults);
        $query = new WP_Query($query_args);

        return $query->posts;
    }

    public function findByStatus(string $status, array $args = []): array
    {
        $defaults = [
            'post_type' => PostTypes::COURSE,
            'post_status' => $status,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query_args = wp_parse_args($args, $defaults);
        $query = new WP_Query($query_args);

        return $query->posts;
    }

    public function search(string $search_term, array $args = []): array
    {
        $query = $this->querySearch($search_term, $args);

        return $query->posts;
    }

    /**
     * Course search query (caller may read {@see WP_Query::found_posts} for pagination).
     */
    public function querySearch(string $search_term, array $args = []): WP_Query
    {
        $defaults = [
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            's' => $search_term,
            'orderby' => 'relevance',
        ];

        $query_args = wp_parse_args($args, $defaults);

        return new WP_Query($query_args);
    }

    public function getFeatured(array $args = []): array
    {
        $defaults = [
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'posts_per_page' => 6,
            'meta_query' => [
                [
                    'key' => '_sikshya_featured',
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query_args = wp_parse_args($args, $defaults);
        $query = new WP_Query($query_args);

        return $query->posts;
    }

    public function getPopular(array $args = []): array
    {
        $defaults = [
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'posts_per_page' => 6,
            'meta_key' => '_sikshya_enrollment_count',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
        ];

        $query_args = wp_parse_args($args, $defaults);
        $query = new WP_Query($query_args);

        return $query->posts;
    }

    private function setMetaFields(int $post_id, array $data): void
    {
        $meta_fields = [
            '_sikshya_price' => 'price',
            '_sikshya_sale_price' => 'sale_price',
            '_sikshya_duration' => 'duration',
            '_sikshya_difficulty' => 'difficulty',
            '_sikshya_max_students' => 'max_students',
            '_sikshya_featured' => 'featured',
            '_sikshya_thumbnail' => 'thumbnail',
            '_sikshya_video_url' => 'video_url',
            '_sikshya_course_level' => 'course_level',
            '_sikshya_language' => 'language',
            '_sikshya_certificate' => 'certificate',
            '_sikshya_quizzes' => 'quizzes',
            '_sikshya_assignments' => 'assignments',
            '_sikshya_discussions' => 'discussions',
        ];

        foreach ($meta_fields as $meta_key => $data_key) {
            if (isset($data[$data_key])) {
                update_post_meta($post_id, $meta_key, $data[$data_key]);
            }
        }
    }

    public function getMeta(int $post_id, string $key, bool $single = true)
    {
        return get_post_meta($post_id, $key, $single);
    }

    public function setMeta(int $post_id, string $key, $value): bool
    {
        return update_post_meta($post_id, $key, $value);
    }

    public function deleteMeta(int $post_id, string $key): bool
    {
        return delete_post_meta($post_id, $key);
    }
}
