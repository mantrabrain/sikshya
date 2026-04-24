<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\CourseRepository;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Database\Repositories\ProgressRepository;

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

        // Check if user is already enrolled
        $existing_enrollment = $this->enrollmentRepository->findByUserAndCourse($user_id, $course_id);
        if ($existing_enrollment) {
            throw new \InvalidArgumentException('User is already enrolled in this course');
        }

        // Check course capacity
        $max_students = $this->courseRepository->getMeta($course_id, '_sikshya_max_students', true);
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
        if ($enrollment !== null) {
            return true;
        }

        /**
         * Allow add-ons to grant access without an enrollment row (e.g. active subscription).
         *
         * @param bool $has_access
         */
        return (bool) apply_filters('sikshya_user_has_course_access', false, $user_id, $course_id);
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
