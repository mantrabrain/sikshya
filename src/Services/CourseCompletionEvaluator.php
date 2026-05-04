<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\ProgressRepository;

/**
 * Enrollment progress % and completion rules from global course settings.
 *
 * @package Sikshya\Services
 */
final class CourseCompletionEvaluator
{
    /**
     * Progress 0–100 for display and completion checks (except manual mode, which never auto-completes here).
     */
    public static function computeProgressPercent(int $user_id, int $course_id, ProgressRepository $progress): float
    {
        $criteria = sanitize_key((string) Settings::get('course_completion_criteria', 'all_lessons'));
        $lessons = LearnerCurriculumHelper::lessonIdsForCourse($course_id);

        if ($criteria === 'manual') {
            $total = count($lessons);
            $completed = $progress->countCompletedLessons($user_id, $course_id);

            return $total > 0 ? round(100 * $completed / $total, 2) : 0.0;
        }

        if ($criteria === 'all_lessons_quizzes') {
            $quizzes = LearnerCurriculumHelper::quizIdsForCourse($course_id);
            $assignments = LearnerCurriculumHelper::assignmentIdsForCourse($course_id);
            $total = count($lessons) + count($quizzes) + count($assignments);
            if ($total <= 0) {
                return 0.0;
            }
            $done = $progress->countCompletedLessons($user_id, $course_id);
            $done += $progress->countCompletedQuizzesAmong($user_id, $course_id, $quizzes);
            $done += $progress->countCompletedAssignmentsAmong($user_id, $course_id, $assignments);

            return round(100 * $done / $total, 2);
        }

        // all_lessons + percentage both use lesson completion ratio.
        $total = count($lessons);
        $completed = $progress->countCompletedLessons($user_id, $course_id);

        return $total > 0 ? round(100 * $completed / $total, 2) : 0.0;
    }

    /**
     * Whether enrollment row should move to `completed` based on progress + criteria.
     *
     * @param float  $progress_percent From {@see self::computeProgressPercent()}.
     * @param string $criteria         Raw setting slug.
     */
    public static function shouldMarkEnrollmentCompleted(float $progress_percent, string $criteria): bool
    {
        $criteria = sanitize_key($criteria);
        if ($criteria === '' || $criteria === 'manual') {
            return false;
        }

        if ($criteria === 'percentage') {
            $min = (float) Settings::get('completion_percentage', 80);
            $min = max(1.0, min(100.0, $min));

            return $progress_percent + 0.0001 >= $min;
        }

        // all_lessons, all_lessons_quizzes: require full completion of tracked items.
        return $progress_percent + 0.0001 >= 100.0;
    }
}
