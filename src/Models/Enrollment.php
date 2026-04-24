<?php

namespace Sikshya\Models;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Tables\EnrollmentsTable;

/**
 * Enrollment Model
 *
 * Handles all enrollment-related data operations
 *
 * @package Sikshya\Models
 */
class Enrollment
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // No dependencies needed for this model
    }

    /**
     * Get all enrollments
     *
     * @param array $args Query arguments
     * @return array Array of enrollments
     */
    public function getAll(array $args = []): array
    {
        global $wpdb;
        $t = EnrollmentsTable::getTableName();

        $defaults = [
            'user_id' => 0,
            'course_id' => 0,
            'status' => '',
            'limit' => -1,
            'offset' => 0,
            'orderby' => 'enrolled_date',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where_conditions = [];
        $where_values = [];

        if ($args['user_id'] > 0) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        if ($args['course_id'] > 0) {
            $where_conditions[] = 'course_id = %d';
            $where_values[] = $args['course_id'];
        }

        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $order_clause = 'ORDER BY ' . esc_sql($args['orderby']) . ' ' . esc_sql($args['order']);

        $limit_clause = '';
        if ($args['limit'] > 0) {
            $limit_clause = 'LIMIT ' . (int) $args['limit'];
            if ($args['offset'] > 0) {
                $limit_clause .= ' OFFSET ' . (int) $args['offset'];
            }
        }

        $query = "SELECT * FROM {$t} {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Get enrollment by ID
     *
     * @param int $enrollment_id Enrollment ID
     * @return object|null Enrollment object or null
     */
    public function getById(int $enrollment_id)
    {
        global $wpdb;
        $t = EnrollmentsTable::getTableName();

        $query = $wpdb->prepare(
            "SELECT * FROM {$t} WHERE id = %d",
            $enrollment_id
        );

        return $wpdb->get_row($query);
    }

    /**
     * Get enrollment by user and course
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @return object|null Enrollment object or null
     */
    public function getByUserAndCourse(int $user_id, int $course_id)
    {
        global $wpdb;
        $t = EnrollmentsTable::getTableName();

        $query = $wpdb->prepare(
            "SELECT * FROM {$t} WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        );

        return $wpdb->get_row($query);
    }

    /**
     * Create a new enrollment
     *
     * @param array $data Enrollment data
     * @return int|false Enrollment ID or false
     */
    public function create(array $data)
    {
        global $wpdb;
        $t = EnrollmentsTable::getTableName();

        $defaults = [
            'user_id' => 0,
            'course_id' => 0,
            'status' => 'enrolled',
            'enrolled_date' => current_time('mysql'),
            'completed_date' => null,
            'payment_method' => '',
            'amount' => 0.0,
            'transaction_id' => '',
            'progress' => 0.0,
            'notes' => '',
        ];

        $data = wp_parse_args($data, $defaults);

        // Validate required fields
        if (empty($data['user_id']) || empty($data['course_id'])) {
            return false;
        }

        // Check if enrollment already exists
        $existing = $this->getByUserAndCourse($data['user_id'], $data['course_id']);
        if ($existing) {
            return false;
        }

        $insert = [
            'user_id' => (int) $data['user_id'],
            'course_id' => (int) $data['course_id'],
            'status' => sanitize_text_field((string) $data['status']),
            'enrolled_date' => $data['enrolled_date'],
            'completed_date' => $data['completed_date'],
            'payment_method' => sanitize_text_field((string) $data['payment_method']),
            'amount' => (float) $data['amount'],
            'transaction_id' => sanitize_text_field((string) $data['transaction_id']),
            'progress' => (float) $data['progress'],
            'notes' => sanitize_textarea_field((string) $data['notes']),
        ];

        $result = $wpdb->insert(
            $t,
            $insert,
            ['%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%s']
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update enrollment
     *
     * @param int $enrollment_id Enrollment ID
     * @param array $data Enrollment data
     * @return bool Success
     */
    public function update(int $enrollment_id, array $data): bool
    {
        global $wpdb;
        $t = EnrollmentsTable::getTableName();

        $result = $wpdb->update(
            $t,
            $data,
            ['id' => $enrollment_id],
            null,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete enrollment
     *
     * @param int $enrollment_id Enrollment ID
     * @return bool Success
     */
    public function delete(int $enrollment_id): bool
    {
        global $wpdb;
        $t = EnrollmentsTable::getTableName();

        $result = $wpdb->delete(
            $t,
            ['id' => $enrollment_id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Enroll user in course
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @return int|\WP_Error Enrollment ID or error
     */
    public function enroll(int $user_id, int $course_id)
    {
        // Check if user exists
        if (!get_user_by('id', $user_id)) {
            return new \WP_Error('invalid_user', __('Invalid user', 'sikshya'));
        }

        // Check if course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== PostTypes::COURSE) {
            return new \WP_Error('invalid_course', __('Invalid course', 'sikshya'));
        }

        // Check if already enrolled
        if ($this->isEnrolled($user_id, $course_id)) {
            return new \WP_Error('already_enrolled', __('User is already enrolled in this course', 'sikshya'));
        }

        $enrollment_data = [
            'user_id' => $user_id,
            'course_id' => $course_id,
            'status' => 'enrolled',
            'enrolled_date' => current_time('mysql'),
        ];

        $enrollment_id = $this->create($enrollment_data);

        if ($enrollment_id === false) {
            return new \WP_Error('enrollment_failed', __('Failed to create enrollment', 'sikshya'));
        }

        // Trigger enrollment action
        do_action('sikshya_user_enrolled', $user_id, $course_id, $enrollment_id);

        return $enrollment_id;
    }

    /**
     * Unenroll user from course
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @return bool|\WP_Error Success or error
     */
    public function unenroll(int $user_id, int $course_id)
    {
        $enrollment = $this->getByUserAndCourse($user_id, $course_id);

        if (!$enrollment) {
            return new \WP_Error('not_enrolled', __('User is not enrolled in this course', 'sikshya'));
        }

        $result = $this->delete($enrollment->id);

        if ($result) {
            // Trigger unenrollment action
            do_action('sikshya_user_unenrolled', $user_id, $course_id, $enrollment->id);
        }

        return $result;
    }

    /**
     * Check if user is enrolled in course
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @return bool True if enrolled
     */
    public function isEnrolled(int $user_id, int $course_id): bool
    {
        $enrollment = $this->getByUserAndCourse($user_id, $course_id);
        return $enrollment !== null;
    }

    /**
     * Get user enrollments
     *
     * @param int $user_id User ID
     * @return array User enrollments
     */
    public function getUserEnrollments(int $user_id): array
    {
        return $this->getAll(['user_id' => $user_id]);
    }

    /**
     * Get course enrollments
     *
     * @param int $course_id Course ID
     * @return array Course enrollments
     */
    public function getCourseEnrollments(int $course_id): array
    {
        return $this->getAll(['course_id' => $course_id]);
    }

    /**
     * Update enrollment progress
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @param float $progress Progress percentage (0-100)
     * @return bool Success
     */
    public function updateProgress(int $user_id, int $course_id, float $progress): bool
    {
        $enrollment = $this->getByUserAndCourse($user_id, $course_id);

        if (!$enrollment) {
            return false;
        }

        $data = [
            'progress' => max(0, min(100, $progress)),
        ];

        // Mark as completed if progress is 100%
        if ($progress >= 100 && $enrollment->status !== 'completed') {
            $data['status'] = 'completed';
            $data['completed_date'] = current_time('mysql');
        }

        return $this->update($enrollment->id, $data);
    }

    /**
     * Get enrollment progress
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @return array Progress data
     */
    public function getProgress(int $user_id, int $course_id): array
    {
        $enrollment = $this->getByUserAndCourse($user_id, $course_id);

        if (!$enrollment) {
            return [
                'enrolled' => false,
                'progress' => 0,
                'status' => 'not_enrolled',
                'completion_date' => null,
            ];
        }

        return [
            'enrolled' => true,
            'progress' => isset($enrollment->progress) ? (float) $enrollment->progress : 0.0,
            'status' => $enrollment->status,
            'enrolled_date' => $enrollment->enrolled_date ?? null,
            'completed_date' => $enrollment->completed_date ?? null,
        ];
    }

    /**
     * Get enrollment statistics
     *
     * @param int $course_id Course ID
     * @return array Statistics
     */
    public function getCourseStatistics(int $course_id): array
    {
        global $wpdb;
        $t = EnrollmentsTable::getTableName();

        $query = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_enrollments,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_enrollments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_enrollments,
                AVG(progress) as average_progress
            FROM {$t} 
            WHERE course_id = %d",
            $course_id
        );

        $stats = $wpdb->get_row($query);

        return [
            'total_enrollments' => (int) ($stats->total_enrollments ?? 0),
            'active_enrollments' => (int) ($stats->active_enrollments ?? 0),
            'completed_enrollments' => (int) ($stats->completed_enrollments ?? 0),
            'average_progress' => (float) ($stats->average_progress ?? 0),
        ];
    }

    /**
     * Get user statistics
     *
     * @param int $user_id User ID
     * @return array Statistics
     */
    public function getUserStatistics(int $user_id): array
    {
        global $wpdb;
        $t = EnrollmentsTable::getTableName();

        $query = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_enrollments,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_enrollments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_enrollments,
                AVG(progress) as average_progress
            FROM {$t} 
            WHERE user_id = %d",
            $user_id
        );

        $stats = $wpdb->get_row($query);

        return [
            'total_enrollments' => (int) ($stats->total_enrollments ?? 0),
            'active_enrollments' => (int) ($stats->active_enrollments ?? 0),
            'completed_enrollments' => (int) ($stats->completed_enrollments ?? 0),
            'average_progress' => (float) ($stats->average_progress ?? 0),
        ];
    }

    /**
     * Create enrollment table
     *
     * @return bool Success
     */
    public function createTable(): bool
    {
        // Schema is owned by Sikshya\Database\Database::createTables() — do not create a divergent table here.
        return true;
    }
}
