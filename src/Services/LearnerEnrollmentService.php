<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\EnrollmentRepository;

/**
 * Learner enrollment checks for frontend controllers (Model layer).
 *
 * @package Sikshya\Services
 */
final class LearnerEnrollmentService
{
    private CourseService $courses;

    private EnrollmentRepository $enrollments;

    public function __construct(CourseService $courses)
    {
        $this->courses     = $courses;
        $this->enrollments = new EnrollmentRepository();
    }

    public function isEnrolled(int $course_id, int $user_id): bool
    {
        return $this->courses->isUserEnrolled($user_id, $course_id);
    }

    /**
     * @return array<int, array{id: int}>
     */
    public function getUserCourses(int $user_id): array
    {
        $rows = $this->courses->getUserEnrollments($user_id, ['limit' => 500, 'offset' => 0]);
        $out  = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->course_id,
            ];
        }

        return $out;
    }

    public function getUserCoursesCount(int $user_id): int
    {
        return $this->enrollments->countByUser($user_id);
    }
}
