<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Database\Repositories\ProgressRepository;

/**
 * Learner-facing progress API used by frontend controllers (Model layer).
 *
 * @package Sikshya\Services
 */
final class LearningProgressService
{
    private ProgressRepository $progress;
    private EnrollmentRepository $enrollments;

    public function __construct()
    {
        $this->progress = new ProgressRepository();
        $this->enrollments = new EnrollmentRepository();
    }

    /**
     * @return array<int, object>
     */
    public function getCourseProgress(int $course_id, int $user_id): array
    {
        return $this->progress->getCourseProgress($user_id, $course_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLessonProgress(int $lesson_id, int $user_id): array
    {
        $rows = $this->progress->findByUserAndLesson($user_id, $lesson_id);
        $out  = [];
        foreach ($rows as $row) {
            $out[] = [
                'id'             => (int) $row->id,
                'status'         => (string) $row->status,
                'percentage'     => isset($row->percentage) ? (float) $row->percentage : 0.0,
                'completed_date' => $row->completed_date ?? null,
            ];
        }

        return $out;
    }

    public function markLessonComplete(int $lesson_id, int $user_id): bool
    {
        $course_id = $this->lessonCourseId($lesson_id);
        if ($course_id <= 0 || $user_id <= 0) {
            return false;
        }

        return $this->progress->markLessonComplete($user_id, $course_id, $lesson_id);
    }

    public function markLessonIncomplete(int $lesson_id, int $user_id): bool
    {
        $course_id = $this->lessonCourseId($lesson_id);
        if ($course_id <= 0 || $user_id <= 0) {
            return false;
        }

        return $this->progress->deleteLessonCompletionRows($user_id, $course_id, $lesson_id);
    }

    /**
     * @param array<string, mixed> $progress_data
     */
    public function saveLessonProgress(int $lesson_id, int $user_id, array $progress_data): bool
    {
        $course_id = $this->lessonCourseId($lesson_id);
        if ($course_id <= 0 || $user_id <= 0) {
            return false;
        }

        $rows = $this->progress->findByUserAndLesson($user_id, $lesson_id);
        if ($rows === []) {
            return $this->progress->create(
                [
                    'user_id'    => $user_id,
                    'course_id'  => $course_id,
                    'lesson_id'  => $lesson_id,
                    'status'     => 'in_progress',
                    'percentage' => isset($progress_data['percentage']) ? (float) $progress_data['percentage'] : 0.0,
                    'time_spent' => isset($progress_data['time_spent']) ? (int) $progress_data['time_spent'] : 0,
                ]
            ) > 0;
        }

        $first = $rows[0];

        return $this->progress->update(
            (int) $first->id,
            [
                'percentage' => isset($progress_data['percentage']) ? (float) $progress_data['percentage'] : (float) $first->percentage,
                'time_spent' => isset($progress_data['time_spent']) ? (int) $progress_data['time_spent'] : (int) ($first->time_spent ?? 0),
            ]
        );
    }

    public function getCompletedCoursesCount(int $user_id): int
    {
        return $this->enrollments->countForUserByStatus($user_id, 'completed');
    }

    public function getTotalLessonsCount(int $user_id): int
    {
        unset($user_id);

        return 0;
    }

    public function getCompletedLessonsCount(int $user_id): int
    {
        return $this->countCompletedLessonsGlobal($user_id);
    }

    public function getTotalLearningTime(int $user_id): int
    {
        return $this->progress->sumTimeSpentForUser($user_id);
    }

    /**
     * Completed lessons across all courses for a user (distinct lesson IDs).
     */
    private function countCompletedLessonsGlobal(int $user_id): int
    {
        return $this->progress->countDistinctCompletedLessonsForUser($user_id);
    }

    private function lessonCourseId(int $lesson_id): int
    {
        return LessonCourseLink::resolvedCourseIdForLesson($lesson_id);
    }
}
