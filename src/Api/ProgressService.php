<?php

namespace Sikshya\Api;

use WP_REST_Request;
use WP_REST_Response;

class ProgressService
{
    public function getProgress(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sikshya_progress';
        $user_id = $request->get_param('user_id');
        $course_id = $request->get_param('course_id');

        $where = [];
        $prepare_values = [];

        if ($user_id) {
            $where[] = 'user_id = %d';
            $prepare_values[] = $user_id;
        }

        if ($course_id) {
            $where[] = 'course_id = %d';
            $prepare_values[] = $course_id;
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT * FROM {$table} {$where_clause} ORDER BY updated_at DESC";

        if (!empty($prepare_values)) {
            $query = $wpdb->prepare($query, ...$prepare_values);
        }

        $progress = $wpdb->get_results($query);

        return new WP_REST_Response([
            'progress' => array_map([$this, 'formatProgress'], $progress),
        ]);
    }

    private function formatProgress($progress): array
    {
        return [
            'id' => $progress->id,
            'user_id' => $progress->user_id,
            'course_id' => $progress->course_id,
            'lesson_id' => $progress->lesson_id,
            'status' => $progress->status,
            'percentage' => $progress->percentage,
            'completed_date' => $progress->completed_date,
            'updated_at' => $progress->updated_at ?? null,
        ];
    }
}
