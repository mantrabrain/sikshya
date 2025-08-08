<?php

namespace Sikshya\Api;

use WP_REST_Request;
use WP_REST_Response;

class EnrollmentService
{
    public function getEnrollments(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sikshya_enrollments';
        $per_page = $request->get_param('per_page') ?: 10;
        $page = $request->get_param('page') ?: 1;
        $offset = ($page - 1) * $per_page;
        
        $enrollments = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} LIMIT %d OFFSET %d", $per_page, $offset)
        );
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        
        return new WP_REST_Response([
            'enrollments' => array_map([$this, 'formatEnrollment'], $enrollments),
            'total' => $total,
            'pages' => ceil($total / $per_page),
        ]);
    }

    public function createEnrollment(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $data = $request->get_json_params();
        $table = $wpdb->prefix . 'sikshya_enrollments';
        
        $result = $wpdb->insert($table, [
            'user_id' => intval($data['user_id'] ?? 0),
            'course_id' => intval($data['course_id'] ?? 0),
            'status' => sanitize_text_field($data['status'] ?? 'enrolled'),
            'enrolled_date' => current_time('mysql'),
            'payment_method' => sanitize_text_field($data['payment_method'] ?? ''),
            'amount' => floatval($data['amount'] ?? 0),
        ]);
        
        if (!$result) {
            return new WP_REST_Response(['error' => 'Failed to create enrollment'], 400);
        }
        
        return $this->getEnrollment(new WP_REST_Request('GET', '', ['id' => $wpdb->insert_id]));
    }

    public function getEnrollment(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id = $request->get_param('id');
        $table = $wpdb->prefix . 'sikshya_enrollments';
        
        $enrollment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        
        if (!$enrollment) {
            return new WP_REST_Response(['error' => 'Enrollment not found'], 404);
        }
        
        return new WP_REST_Response($this->formatEnrollment($enrollment));
    }

    public function updateEnrollment(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id = $request->get_param('id');
        $data = $request->get_json_params();
        $table = $wpdb->prefix . 'sikshya_enrollments';
        
        $result = $wpdb->update($table, [
            'status' => sanitize_text_field($data['status'] ?? ''),
            'payment_method' => sanitize_text_field($data['payment_method'] ?? ''),
            'amount' => floatval($data['amount'] ?? 0),
        ], ['id' => $id]);
        
        if ($result === false) {
            return new WP_REST_Response(['error' => 'Failed to update enrollment'], 400);
        }
        
        return $this->getEnrollment(new WP_REST_Request('GET', '', ['id' => $id]));
    }

    public function deleteEnrollment(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id = $request->get_param('id');
        $table = $wpdb->prefix . 'sikshya_enrollments';
        
        $result = $wpdb->delete($table, ['id' => $id]);
        
        if (!$result) {
            return new WP_REST_Response(['error' => 'Failed to delete enrollment'], 400);
        }
        
        return new WP_REST_Response(['success' => true]);
    }

    private function formatEnrollment($enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'status' => $enrollment->status,
            'enrolled_date' => $enrollment->enrolled_date,
            'payment_method' => $enrollment->payment_method,
            'amount' => $enrollment->amount,
        ];
    }
} 