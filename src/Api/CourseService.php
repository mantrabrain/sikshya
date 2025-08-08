<?php

namespace Sikshya\Api;

use WP_REST_Request;
use WP_REST_Response;

class CourseService
{
    /**
     * Get all courses
     */
    public function getCourses(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'post_type' => 'sikshya_course',
            'post_status' => 'publish',
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged' => $request->get_param('page') ?: 1,
        ];
        $query = new \WP_Query($args);
        $courses = [];
        foreach ($query->posts as $post) {
            $courses[] = $this->formatCourse($post);
        }
        return new WP_REST_Response([
            'courses' => $courses,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ]);
    }

    /**
     * Create a new course
     */
    public function createCourse(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_json_params();
        $post_id = wp_insert_post([
            'post_type' => 'sikshya_course',
            'post_title' => sanitize_text_field($data['title'] ?? ''),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_status' => 'publish',
        ]);
        if (is_wp_error($post_id)) {
            return new WP_REST_Response(['error' => $post_id->get_error_message()], 400);
        }
        return $this->getCourse(new WP_REST_Request('GET', '', ['id' => $post_id]));
    }

    /**
     * Get a single course
     */
    public function getCourse(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $post = get_post($id);
        if (!$post || $post->post_type !== 'sikshya_course') {
            return new WP_REST_Response(['error' => 'Course not found'], 404);
        }
        return new WP_REST_Response($this->formatCourse($post));
    }

    /**
     * Update a course
     */
    public function updateCourse(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $data = $request->get_json_params();
        $postarr = [
            'ID' => $id,
            'post_title' => sanitize_text_field($data['title'] ?? ''),
            'post_content' => wp_kses_post($data['content'] ?? ''),
        ];
        $result = wp_update_post($postarr, true);
        if (is_wp_error($result)) {
            return new WP_REST_Response(['error' => $result->get_error_message()], 400);
        }
        return $this->getCourse(new WP_REST_Request('GET', '', ['id' => $id]));
    }

    /**
     * Delete a course
     */
    public function deleteCourse(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $result = wp_delete_post($id, true);
        if (!$result) {
            return new WP_REST_Response(['error' => 'Failed to delete course'], 400);
        }
        return new WP_REST_Response(['success' => true]);
    }

    /**
     * Format course data for API response
     */
    private function formatCourse($post): array
    {
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'author' => $post->post_author,
            'date' => $post->post_date,
            'categories' => wp_get_post_terms($post->ID, 'sikshya_course_category', ['fields' => 'names']),
            'tags' => wp_get_post_terms($post->ID, 'sikshya_course_tag', ['fields' => 'names']),
            'meta' => get_post_meta($post->ID),
            'permalink' => get_permalink($post->ID),
        ];
    }
} 