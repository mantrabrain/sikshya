<?php

declare(strict_types=1);

namespace Sikshya\Services\Enrollment;

use Sikshya\Database\Repositories\CourseRepository;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Services\Settings;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Centralized policy gate for "is this user allowed to enroll in this course right now?".
 *
 * Historically the rules lived inline in {@see \Sikshya\Services\CourseService::enrollUser()};
 * a separate, lighter-weight path in {@see \Sikshya\Models\Enrollment::enroll()} skipped most
 * of them. Pulling the rules out into one place lets callers opt into the strict checks without
 * also pulling in `CourseService`'s creation side effects (enrollment-count update, progress
 * initialization, etc.).
 *
 * Throws `\InvalidArgumentException` on policy violation so callers using the legacy
 * exception-based contract (CourseService → REST routes) keep working unchanged.
 *
 * @package Sikshya\Services\Enrollment
 */
final class EnrollmentValidator
{
    private CourseRepository $courseRepository;

    private EnrollmentRepository $enrollmentRepository;

    public function __construct(
        ?CourseRepository $courseRepository = null,
        ?EnrollmentRepository $enrollmentRepository = null
    ) {
        $this->courseRepository = $courseRepository ?? new CourseRepository();
        $this->enrollmentRepository = $enrollmentRepository ?? new EnrollmentRepository();
    }

    /**
     * @param array<string, mixed> $enrollment_data Passed to the `sikshya_can_enroll_user_in_course` filter.
     *
     * @throws \InvalidArgumentException When any policy check fails.
     */
    public function assertCanEnroll(int $user_id, int $course_id, array $enrollment_data = []): void
    {
        $course = $this->courseRepository->findById($course_id);
        if (!$course) {
            throw new \InvalidArgumentException('Course not found');
        }

        $allowed = apply_filters('sikshya_can_enroll_user_in_course', true, $user_id, $course_id, $enrollment_data);
        if ($allowed instanceof \WP_Error) {
            throw new \InvalidArgumentException($allowed->get_error_message());
        }
        if ($allowed === false) {
            throw new \InvalidArgumentException('Enrollment is not allowed for this course');
        }

        $this->assertScheduleAllowsSignup($course_id);

        $existing = $this->enrollmentRepository->findByUserAndCourse($user_id, $course_id);
        if ($existing) {
            throw new \InvalidArgumentException('User is already enrolled in this course');
        }

        $max_courses = (int) Settings::get('max_courses_per_student', 0);
        if ($max_courses > 0) {
            $active = $this->enrollmentRepository->countActiveEnrollmentsForUser($user_id);
            if ($active >= $max_courses) {
                throw new \InvalidArgumentException(
                    __('You are enrolled in the maximum number of courses allowed on this site.', 'sikshya')
                );
            }
        }

        $course_cap = (int) $this->courseRepository->getMeta($course_id, '_sikshya_max_students', true);
        $global_cap = (int) Settings::get('max_students_per_course', 0);
        $max_students = 0;
        if ($global_cap > 0 && $course_cap > 0) {
            $max_students = min($global_cap, $course_cap);
        } elseif ($global_cap > 0) {
            $max_students = $global_cap;
        } else {
            $max_students = $course_cap;
        }
        if ($max_students > 0) {
            $current_enrollments = $this->enrollmentRepository->countByCourse($course_id);
            if ($current_enrollments >= $max_students) {
                throw new \InvalidArgumentException('Course is at maximum capacity');
            }
        }
    }

    /**
     * Block new enrollments outside the course schedule and (when enabled) the site default
     * enrollment window. Public so {@see \Sikshya\Services\CourseService} can delegate; private
     * helpers preserve the boundary parsing exactly as the original implementation.
     *
     * @throws \InvalidArgumentException When enrollment is not currently open.
     */
    public function assertScheduleAllowsSignup(int $course_id): void
    {
        $start_raw = $this->courseRepository->getMeta($course_id, '_sikshya_enrollment_start_date', true);
        $end_raw = $this->courseRepository->getMeta($course_id, '_sikshya_enrollment_end_date', true);
        $start_ts = $this->parseBoundary($start_raw, false);
        $end_ts = $this->parseBoundary($end_raw, true);

        $periods_on = Settings::isTruthy(Settings::get('enable_enrollment_periods', '0'));
        if ($periods_on && $start_ts === 0 && $end_ts === 0) {
            $gs = (string) Settings::get('default_enrollment_start', '');
            $ge = (string) Settings::get('default_enrollment_end', '');
            $start_ts = $gs !== '' ? strtotime($gs) : 0;
            $end_ts = $ge !== '' ? strtotime($ge) : 0;
        }

        if ($start_ts === 0 && $end_ts === 0) {
            return;
        }

        $now = (int) current_time('timestamp');
        if ($start_ts > 0 && $now < $start_ts) {
            throw new \InvalidArgumentException(
                __('Enrollment has not opened yet for this course.', 'sikshya')
            );
        }
        if ($end_ts > 0 && $now > $end_ts) {
            throw new \InvalidArgumentException(
                __('Enrollment is closed for this course.', 'sikshya')
            );
        }
    }

    /**
     * @param mixed $value Stored date (Y-m-d) or datetime string.
     */
    private function parseBoundary($value, bool $end_of_day): int
    {
        if ($value === '' || $value === null) {
            return 0;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return 0;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            $s .= $end_of_day ? ' 23:59:59' : ' 00:00:00';
        }
        $t = strtotime($s);

        return $t ? (int) $t : 0;
    }
}
