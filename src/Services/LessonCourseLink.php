<?php

declare(strict_types=1);

namespace Sikshya\Services;

/**
 * Canonical read/write helpers for linking a lesson post to its parent course.
 *
 * Historically `_sikshya_lesson_course` vs `_sikshya_course_id` diverged across
 * repositories; Learn and progress rely on the prefixed keys. These helpers unify
 * resolution and persistence so imports and legacy creators stay consistent.
 */
final class LessonCourseLink
{
    /**
     * Resolve course ID for a lesson (Learn shell, progress, admin affordances).
     */
    public static function resolvedCourseIdForLesson(int $lessonId): int
    {
        $lessonId = max(0, $lessonId);
        if ($lessonId <= 0) {
            return 0;
        }

        foreach (['_sikshya_lesson_course', 'sikshya_lesson_course', '_sikshya_course_id'] as $key) {
            $v = (int) get_post_meta($lessonId, $key, true);
            if ($v > 0) {
                return $v;
            }
        }

        return 0;
    }

    /**
     * Persist course linkage keys used across the codebase (and legacy list tables).
     */
    public static function persistLessonCourseId(int $lessonId, int $courseId): void
    {
        $lessonId = max(0, $lessonId);
        $courseId = max(0, $courseId);
        if ($lessonId <= 0) {
            return;
        }

        update_post_meta($lessonId, '_sikshya_lesson_course', $courseId);
        update_post_meta($lessonId, 'sikshya_lesson_course', $courseId);
        if ($courseId > 0) {
            update_post_meta($lessonId, '_sikshya_course_id', $courseId);
        } else {
            delete_post_meta($lessonId, '_sikshya_course_id');
        }
    }
}
