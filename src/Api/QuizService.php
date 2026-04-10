<?php

namespace Sikshya\Api;

use WP_REST_Request;
use WP_REST_Response;

class QuizService
{
    public function getQuizzes(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'post_type' => 'sikshya_quiz',
            'post_status' => 'publish',
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged' => $request->get_param('page') ?: 1,
        ];
        $query = new \WP_Query($args);
        $quizzes = [];
        foreach ($query->posts as $post) {
            $quizzes[] = $this->formatQuiz($post);
        }
        return new WP_REST_Response([
            'quizzes' => $quizzes,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ]);
    }

    public function createQuiz(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_json_params();
        $post_id = wp_insert_post([
            'post_type' => 'sikshya_quiz',
            'post_title' => sanitize_text_field($data['title'] ?? ''),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_status' => 'publish',
        ]);
        if (is_wp_error($post_id)) {
            return new WP_REST_Response(['error' => $post_id->get_error_message()], 400);
        }
        return $this->getQuiz(new WP_REST_Request('GET', '', ['id' => $post_id]));
    }

    public function getQuiz(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $post = get_post($id);
        if (!$post || $post->post_type !== 'sikshya_quiz') {
            return new WP_REST_Response(['error' => 'Quiz not found'], 404);
        }
        return new WP_REST_Response($this->formatQuiz($post));
    }

    public function updateQuiz(WP_REST_Request $request): WP_REST_Response
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
        return $this->getQuiz(new WP_REST_Request('GET', '', ['id' => $id]));
    }

    public function deleteQuiz(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $result = wp_delete_post($id, true);
        if (!$result) {
            return new WP_REST_Response(['error' => 'Failed to delete quiz'], 400);
        }
        return new WP_REST_Response(['success' => true]);
    }

    private function formatQuiz($post): array
    {
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'author' => $post->post_author,
            'date' => $post->post_date,
            'meta' => get_post_meta($post->ID),
            'permalink' => get_permalink($post->ID),
        ];
    }
}
