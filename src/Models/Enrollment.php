<?php

namespace Sikshya\Models;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;

/**
 * Legacy enrollment facade — all persistence goes through {@see EnrollmentRepository}.
 *
 * @package Sikshya\Models
 */
class Enrollment
{
    private EnrollmentRepository $repo;

    public function __construct()
    {
        $this->repo = new EnrollmentRepository();
    }

    /**
     * @param array<string, mixed> $args Query arguments
     * @return array<int, object>
     */
    public function getAll(array $args = []): array
    {
        return $this->repo->searchWithFilters($args);
    }

    public function getById(int $enrollment_id)
    {
        return $this->repo->findById($enrollment_id);
    }

    public function getByUserAndCourse(int $user_id, int $course_id)
    {
        return $this->repo->findByUserAndCourse($user_id, $course_id);
    }

    /**
     * @param array<string, mixed> $data Enrollment data
     * @return int|false Enrollment ID or false
     */
    public function create(array $data)
    {
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

        if (empty($data['user_id']) || empty($data['course_id'])) {
            return false;
        }

        if ($this->repo->findByUserAndCourse((int) $data['user_id'], (int) $data['course_id'])) {
            return false;
        }

        $row = [
            'user_id' => (int) $data['user_id'],
            'course_id' => (int) $data['course_id'],
            'status' => sanitize_text_field((string) $data['status']),
            'enrolled_date' => $data['enrolled_date'],
            'payment_method' => sanitize_text_field((string) $data['payment_method']),
            'amount' => (float) $data['amount'],
            'transaction_id' => sanitize_text_field((string) $data['transaction_id']),
            'progress' => (float) $data['progress'],
            'notes' => $data['notes'] !== '' ? sanitize_textarea_field((string) $data['notes']) : null,
        ];
        if (array_key_exists('completed_date', $data)) {
            $row['completed_date'] = $data['completed_date'];
        }

        $id = $this->repo->create($row);

        return $id > 0 ? $id : false;
    }

    /**
     * @param array<string, mixed> $data Enrollment data
     */
    public function update(int $enrollment_id, array $data): bool
    {
        return $this->repo->update($enrollment_id, $data);
    }

    public function delete(int $enrollment_id): bool
    {
        return $this->repo->delete($enrollment_id);
    }

    /**
     * @return int|\WP_Error
     */
    public function enroll(int $user_id, int $course_id)
    {
        if (!get_user_by('id', $user_id)) {
            return new \WP_Error('invalid_user', __('Invalid user', 'sikshya'));
        }

        $course = get_post($course_id);
        if (!$course || $course->post_type !== PostTypes::COURSE) {
            return new \WP_Error('invalid_course', __('Invalid course', 'sikshya'));
        }

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

        do_action('sikshya_user_enrolled', $user_id, $course_id, $enrollment_id);

        return $enrollment_id;
    }

    /**
     * @return bool|\WP_Error
     */
    public function unenroll(int $user_id, int $course_id)
    {
        $enrollment = $this->getByUserAndCourse($user_id, $course_id);

        if (!$enrollment) {
            return new \WP_Error('not_enrolled', __('User is not enrolled in this course', 'sikshya'));
        }

        $result = $this->delete((int) $enrollment->id);

        if ($result) {
            do_action('sikshya_user_unenrolled', $user_id, $course_id, $enrollment->id);
        }

        return $result;
    }

    public function isEnrolled(int $user_id, int $course_id): bool
    {
        return $this->getByUserAndCourse($user_id, $course_id) !== null;
    }

    /**
     * @return array<int, object>
     */
    public function getUserEnrollments(int $user_id): array
    {
        return $this->getAll(['user_id' => $user_id]);
    }

    /**
     * @return array<int, object>
     */
    public function getCourseEnrollments(int $course_id): array
    {
        return $this->getAll(['course_id' => $course_id]);
    }

    public function updateProgress(int $user_id, int $course_id, float $progress): bool
    {
        $enrollment = $this->getByUserAndCourse($user_id, $course_id);

        if (!$enrollment) {
            return false;
        }

        $data = [
            'progress' => max(0, min(100, $progress)),
        ];

        if ($progress >= 100 && $enrollment->status !== 'completed') {
            $data['status'] = 'completed';
            $data['completed_date'] = current_time('mysql');
        }

        return $this->update((int) $enrollment->id, $data);
    }

    /**
     * @return array<string, mixed>
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
     * @return array<string, int|float>
     */
    public function getCourseStatistics(int $course_id): array
    {
        return $this->repo->getStatisticsForCourse($course_id);
    }

    /**
     * @return array<string, int|float>
     */
    public function getUserStatistics(int $user_id): array
    {
        return $this->repo->getStatisticsForUser($user_id);
    }

    public function createTable(): bool
    {
        // Schema is owned by Sikshya\Database\Database::createTables() — do not create a divergent table here.
        return true;
    }
}
