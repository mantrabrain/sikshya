<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\CourseRepository;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Database\Repositories\ProgressRepository;
use WP_Query;

class CourseService
{
    private CourseRepository $courseRepository;
    private EnrollmentRepository $enrollmentRepository;
    private ProgressRepository $progressRepository;

    /**
     * @param CourseRepository|null $courseRepository Shared instance from the plugin container when available.
     */
    public function __construct(?CourseRepository $courseRepository = null)
    {
        $this->courseRepository = $courseRepository ?? new CourseRepository();
        $this->enrollmentRepository = new EnrollmentRepository();
        $this->progressRepository = new ProgressRepository();
    }

    public function getAllCourses(array $args = []): array
    {
        return $this->courseRepository->findAll($args);
    }

    /**
     * Course catalog query (use {@see WP_Query::posts} and {@see WP_Query::found_posts} for listings).
     */
    public function queryCourses(array $args = []): WP_Query
    {
        return $this->courseRepository->queryCourses($args);
    }

    /**
     * Course search query (use {@see WP_Query::posts} and {@see WP_Query::found_posts} for listings).
     */
    public function querySearchCourses(string $search_term, array $args = []): WP_Query
    {
        return $this->courseRepository->querySearch($search_term, $args);
    }

    public function getCourse(int $id): ?object
    {
        return $this->courseRepository->findById($id);
    }

    public function createCourse(array $data): int
    {
        // Validate required fields
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Course title is required');
        }

        // Set default values
        $data['status'] = $data['status'] ?? 'draft';
        $data['author_id'] = $data['author_id'] ?? get_current_user_id();

        return $this->courseRepository->create($data);
    }

    public function updateCourse(int $id, array $data): bool
    {
        // Check if course exists
        $course = $this->courseRepository->findById($id);
        if (!$course) {
            throw new \InvalidArgumentException('Course not found');
        }

        return $this->courseRepository->update($id, $data);
    }

    public function deleteCourse(int $id): bool
    {
        // Check if course exists
        $course = $this->courseRepository->findById($id);
        if (!$course) {
            throw new \InvalidArgumentException('Course not found');
        }

        // Check if course has enrollments
        $enrollmentCount = $this->enrollmentRepository->countByCourse($id);
        if ($enrollmentCount > 0) {
            throw new \InvalidArgumentException('Cannot delete course with existing enrollments');
        }

        return $this->courseRepository->delete($id);
    }

    public function getFeaturedCourses(int $limit = 6): array
    {
        return $this->courseRepository->getFeatured(['posts_per_page' => $limit]);
    }

    public function getPopularCourses(int $limit = 6): array
    {
        return $this->courseRepository->getPopular(['posts_per_page' => $limit]);
    }

    public function searchCourses(string $search_term, array $args = []): array
    {
        if (empty($search_term)) {
            return [];
        }

        return $this->courseRepository->search($search_term, $args);
    }

    public function getCoursesByInstructor(int $instructor_id, array $args = []): array
    {
        return $this->courseRepository->findByInstructor($instructor_id, $args);
    }

    public function getCoursesByStatus(string $status, array $args = []): array
    {
        return $this->courseRepository->findByStatus($status, $args);
    }

    public function enrollUser(int $user_id, int $course_id, array $enrollment_data = []): int
    {
        // Check if course exists
        $course = $this->courseRepository->findById($course_id);
        if (!$course) {
            throw new \InvalidArgumentException('Course not found');
        }

        /**
         * Allow add-ons to prevent enrollment (e.g. subscription-only access).
         *
         * Return true to allow, false to block, or WP_Error for a message.
         */
        $allowed = apply_filters('sikshya_can_enroll_user_in_course', true, $user_id, $course_id, $enrollment_data);
        if ($allowed instanceof \WP_Error) {
            throw new \InvalidArgumentException($allowed->get_error_message());
        }
        if ($allowed === false) {
            throw new \InvalidArgumentException('Enrollment is not allowed for this course');
        }

        $this->assertEnrollmentScheduleAllowsSignup($course_id);

        // Check if user is already enrolled
        $existing_enrollment = $this->enrollmentRepository->findByUserAndCourse($user_id, $course_id);
        if ($existing_enrollment) {
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

        // Check course capacity
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

        // Create enrollment
        $enrollment_data['user_id'] = $user_id;
        $enrollment_data['course_id'] = $course_id;
        $enrollment_data['status'] = $enrollment_data['status'] ?? 'enrolled';

        $enrollment_id = $this->enrollmentRepository->create($enrollment_data);

        if ($enrollment_id) {
            // Initialize progress tracking
            $this->progressRepository->initializeProgress($user_id, $course_id);

            // Update enrollment count
            $this->updateEnrollmentCount($course_id);

            // Trigger enrollment event
            do_action('sikshya_user_enrolled', $user_id, $course_id, $enrollment_id);
        }

        return $enrollment_id;
    }

    public function unenrollUser(int $user_id, int $course_id): bool
    {
        $enrollment = $this->enrollmentRepository->findByUserAndCourse($user_id, $course_id);
        if (!$enrollment) {
            throw new \InvalidArgumentException('User is not enrolled in this course');
        }

        if (!Settings::isTruthy(Settings::get('allow_unenroll', '1'))) {
            throw new \InvalidArgumentException(
                __('Self-service unenrollment is disabled in LMS settings.', 'sikshya')
            );
        }

        $deadline_days = (int) Settings::get('unenroll_deadline_days', 0);
        if ($deadline_days > 0 && !empty($enrollment->enrolled_date)) {
            $enrolled_ts = strtotime((string) $enrollment->enrolled_date);
            if ($enrolled_ts && (time() - $enrolled_ts) > $deadline_days * DAY_IN_SECONDS) {
                throw new \InvalidArgumentException(
                    sprintf(
                        /* translators: %d: number of days after enrollment when unenroll is allowed */
                        __('The self-service drop period (%d days after enrollment) has ended.', 'sikshya'),
                        $deadline_days
                    )
                );
            }
        }

        $result = $this->enrollmentRepository->delete($enrollment->id);

        if ($result) {
            // Delete progress data
            $this->progressRepository->deleteProgress($user_id, $course_id);

            // Update enrollment count
            $this->updateEnrollmentCount($course_id);

            // Trigger unenrollment event
            do_action('sikshya_user_unenrolled', $user_id, $course_id);
        }

        return $result;
    }

    public function getCourseProgress(int $user_id, int $course_id): array
    {
        return $this->progressRepository->getCourseProgress($user_id, $course_id);
    }

    public function updateCourseProgress(int $user_id, int $course_id, array $progress_data): bool
    {
        return $this->progressRepository->updateCourseProgress($user_id, $course_id, $progress_data);
    }

    public function getCourseEnrollments(int $course_id, array $args = []): array
    {
        return $this->enrollmentRepository->findByCourse($course_id, $args);
    }

    public function getUserEnrollments(int $user_id, array $args = []): array
    {
        return $this->enrollmentRepository->findByUser($user_id, $args);
    }

    public function isUserEnrolled(int $user_id, int $course_id): bool
    {
        $enrollment = $this->enrollmentRepository->findByUserAndCourse($user_id, $course_id);
        if ($enrollment !== null && !$this->isEnrollmentAccessExpiredByPolicy($enrollment)) {
            return true;
        }

        /**
         * Allow add-ons to grant access without an enrollment row (e.g. active subscription).
         *
         * @param bool $has_access
         */
        return (bool) apply_filters('sikshya_user_has_course_access', false, $user_id, $course_id);
    }

    /**
     * True when global "Access length after enroll (days)" has passed since enrolled_date.
     *
     * @param object $enrollment Row from {@see EnrollmentRepository::findByUserAndCourse()}.
     */
    private function isEnrollmentAccessExpiredByPolicy(object $enrollment): bool
    {
        $days = (int) Settings::get('enrollment_expiry_days', 0);
        if ($days <= 0) {
            return false;
        }
        $ed = $enrollment->enrolled_date ?? '';
        if ($ed === '' || $ed === null) {
            return false;
        }
        $ts = strtotime((string) $ed);
        if (!$ts) {
            return false;
        }
        $limit = $ts + ($days * DAY_IN_SECONDS);

        return (int) current_time('timestamp') > $limit;
    }

    public function getCourseStats(int $course_id): array
    {
        $total_enrollments = $this->enrollmentRepository->countByCourse($course_id);
        $completed_enrollments = $this->enrollmentRepository->countByStatus('completed');
        $active_enrollments = $this->enrollmentRepository->countByStatus('enrolled');

        return [
            'total_enrollments' => $total_enrollments,
            'completed_enrollments' => $completed_enrollments,
            'active_enrollments' => $active_enrollments,
            'completion_rate' => $total_enrollments > 0 ? ($completed_enrollments / $total_enrollments) * 100 : 0,
        ];
    }

    private function updateEnrollmentCount(int $course_id): void
    {
        $count = $this->enrollmentRepository->countByCourse($course_id);
        $this->courseRepository->setMeta($course_id, '_sikshya_enrollment_count', $count);
    }

    public function getCoursePrice(int $course_id): float
    {
        $price = $this->courseRepository->getMeta($course_id, '_sikshya_price', true);
        return $price ? floatval($price) : 0.00;
    }

    public function getCourseSalePrice(int $course_id): float
    {
        $sale_price = $this->courseRepository->getMeta($course_id, '_sikshya_sale_price', true);
        return $sale_price ? floatval($sale_price) : 0.00;
    }

    public function getCourseDuration(int $course_id): int
    {
        $duration = $this->courseRepository->getMeta($course_id, '_sikshya_duration', true);
        return $duration ? intval($duration) : 0;
    }

    public function getCourseDifficulty(int $course_id): string
    {
        $difficulty = $this->courseRepository->getMeta($course_id, '_sikshya_difficulty', true);
        return $difficulty ?: 'beginner';
    }

    public function isCourseFeatured(int $course_id): bool
    {
        $featured = $this->courseRepository->getMeta($course_id, '_sikshya_featured', true);
        return (bool) $featured;
    }

    public function setCourseFeatured(int $course_id, bool $featured): bool
    {
        return $this->courseRepository->setMeta($course_id, '_sikshya_featured', $featured ? '1' : '0');
    }

    /**
     * Block new enrollments outside the course schedule and (when enabled) site default enrollment window.
     */
    private function assertEnrollmentScheduleAllowsSignup(int $course_id): void
    {
        $start_raw = $this->courseRepository->getMeta($course_id, '_sikshya_enrollment_start_date', true);
        $end_raw = $this->courseRepository->getMeta($course_id, '_sikshya_enrollment_end_date', true);
        $start_ts = $this->parseEnrollmentBoundaryToTimestamp($start_raw, false);
        $end_ts = $this->parseEnrollmentBoundaryToTimestamp($end_raw, true);

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
    private function parseEnrollmentBoundaryToTimestamp($value, bool $end_of_day): int
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
