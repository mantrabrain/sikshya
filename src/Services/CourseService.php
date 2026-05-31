<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\CourseRepository;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Database\Repositories\ProgressRepository;
use Sikshya\Services\Enrollment\EnrollmentValidator;
use WP_Query;

class CourseService
{
    private CourseRepository $courseRepository;
    private EnrollmentRepository $enrollmentRepository;
    private ProgressRepository $progressRepository;
    private EnrollmentValidator $enrollmentValidator;

    /**
     * @param CourseRepository|null $courseRepository Shared instance from the plugin container when available.
     */
    public function __construct(?CourseRepository $courseRepository = null)
    {
        $this->courseRepository = $courseRepository ?? new CourseRepository();
        $this->enrollmentRepository = new EnrollmentRepository();
        $this->progressRepository = new ProgressRepository();
        $this->enrollmentValidator = new EnrollmentValidator($this->courseRepository, $this->enrollmentRepository);
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
        // Defense-in-depth price check: any caller that hasn't explicitly
        // opted out (admin manual enrol, paid-checkout fulfilment) must be
        // enrolling into a free course. This closes the TOCTOU window
        // between an external "is this free?" check and the actual write
        // here — even if a concurrent admin action raises the price between
        // the two, we refuse to grant free access to a now-paid course.
        $bypassPriceCheck = !empty($enrollment_data['bypass_price_check']);
        if (!$bypassPriceCheck) {
            $price = (float) $this->getCoursePrice($course_id);
            $sale = (float) $this->getCourseSalePrice($course_id);
            $effective = $sale > 0.00001 ? $sale : $price;
            if ($effective > 0.00001) {
                throw new \InvalidArgumentException(
                    __('This course requires payment. Please complete checkout.', 'sikshya')
                );
            }
        }
        // The flag is an internal control signal — never persist it onto the
        // enrolment row.
        unset($enrollment_data['bypass_price_check']);

        $this->enrollmentValidator->assertCanEnroll($user_id, $course_id, $enrollment_data);

        // Create enrollment.
        $enrollment_data['user_id'] = $user_id;
        $enrollment_data['course_id'] = $course_id;
        $enrollment_data['status'] = $enrollment_data['status'] ?? 'enrolled';

        $enrollment_id = $this->enrollmentRepository->create($enrollment_data);

        if ($enrollment_id) {
            $this->progressRepository->initializeProgress($user_id, $course_id);
            $this->updateEnrollmentCount($course_id);

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

    /**
     * Force-unenrol a learner because a payment was refunded.
     *
     * Bypasses the self-service gates {@see self::unenrollUser()} enforces
     * (the `allow_unenroll` setting and the `unenroll_deadline_days` window) —
     * a refund is an authorised reversal from the merchant side, not a
     * learner-initiated drop, so neither gate applies. Returns false if the
     * learner wasn't enrolled in the first place (idempotent: re-running
     * refund processing on an already-refunded order is a no-op, not an error).
     *
     * The same downstream side-effects as `unenrollUser()` fire:
     *   - Progress rows for this user+course are deleted.
     *   - Enrollment count meta on the course post is decremented.
     *   - `sikshya_user_unenrolled` action fires with a `refund` reason flag
     *     so listeners (email notifications, audit log, Pro analytics) can
     *     distinguish refund-driven removals from self-service drops.
     */
    public function forceUnenrollForRefund(int $user_id, int $course_id, int $order_id = 0): bool
    {
        $enrollment = $this->enrollmentRepository->findByUserAndCourse($user_id, $course_id);
        if (!$enrollment) {
            return false;
        }
        $ok = (bool) $this->enrollmentRepository->delete((int) $enrollment->id);
        if (!$ok) {
            return false;
        }
        $this->progressRepository->deleteProgress($user_id, $course_id);
        $this->updateEnrollmentCount($course_id);

        /**
         * Fires after a refund-driven unenrolment.
         *
         * @param int $user_id   Learner whose enrolment was reversed.
         * @param int $course_id Course they lost access to.
         * @param string $reason 'refund' so listeners can distinguish from self-drop.
         * @param int $order_id  Order that was refunded (0 if not provided).
         */
        do_action('sikshya_user_unenrolled', $user_id, $course_id, 'refund', $order_id);

        return true;
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

}
