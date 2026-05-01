<?php

declare(strict_types=1);

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;

/**
 * Canonical read/write helpers for linking lesson/quiz/assignment posts to a parent course.
 *
 * Historically `_sikshya_lesson_course` vs `_sikshya_course_id` diverged across
 * repositories; Learn and progress rely on the prefixed keys. These helpers unify
 * resolution and persistence so imports and legacy creators stay consistent.
 *
 * When meta is missing (e.g. sample import edge cases), course membership can still be
 * recovered from chapter `_sikshya_contents` — the curriculum outline is authoritative.
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
        if (get_post_type($lessonId) !== PostTypes::LESSON) {
            return 0;
        }

        foreach (['_sikshya_lesson_course', 'sikshya_lesson_course', '_sikshya_course_id'] as $key) {
            $v = (int) get_post_meta($lessonId, $key, true);
            if ($v > 0) {
                return $v;
            }
        }

        $via = self::courseIdFromChapterMembership($lessonId);
        if ($via > 0) {
            self::persistLessonCourseId($lessonId, $via);
        }

        return $via;
    }

    /**
     * Resolve course ID for a quiz (Learn shell, REST, admin bar).
     */
    public static function resolvedCourseIdForQuiz(int $quizId): int
    {
        $quizId = max(0, $quizId);
        if ($quizId <= 0) {
            return 0;
        }
        if (get_post_type($quizId) !== PostTypes::QUIZ) {
            return 0;
        }

        $direct = (int) get_post_meta($quizId, '_sikshya_quiz_course', true);
        if ($direct > 0) {
            return $direct;
        }

        $via = self::courseIdFromChapterMembership($quizId);
        if ($via > 0) {
            update_post_meta($quizId, '_sikshya_quiz_course', $via);
        }

        return $via;
    }

    /**
     * Resolve course ID for an assignment (Learn shell, grading).
     */
    public static function resolvedCourseIdForAssignment(int $assignmentId): int
    {
        $assignmentId = max(0, $assignmentId);
        if ($assignmentId <= 0) {
            return 0;
        }
        if (get_post_type($assignmentId) !== PostTypes::ASSIGNMENT) {
            return 0;
        }

        $direct = (int) get_post_meta($assignmentId, '_sikshya_assignment_course', true);
        if ($direct > 0) {
            return $direct;
        }

        $via = self::courseIdFromChapterMembership($assignmentId);
        if ($via > 0) {
            update_post_meta($assignmentId, '_sikshya_assignment_course', $via);
        }

        return $via;
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

    /**
     * Derive course ID when `_sikshya_*_course` meta is absent by finding which chapter
     * lists this post in `_sikshya_contents`.
     *
     * @return int Course post ID or 0.
     */
    private static function courseIdFromChapterMembership(int $postId): int
    {
        static $memo = [];

        $postId = max(0, $postId);
        if ($postId <= 0) {
            return 0;
        }

        if (isset($memo[$postId])) {
            return $memo[$postId];
        }

        $pt = get_post_type($postId);
        if (
            !is_string($pt)
            || !in_array($pt, [PostTypes::LESSON, PostTypes::QUIZ, PostTypes::ASSIGNMENT], true)
        ) {
            return $memo[$postId] = 0;
        }

        $chapterIds = get_posts(
            [
                'post_type' => PostTypes::CHAPTER,
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids',
                'suppress_filters' => true,
                'no_found_rows' => true,
            ]
        );

        foreach ($chapterIds as $chid) {
            $chid = (int) $chid;
            $contents = get_post_meta($chid, '_sikshya_contents', true);
            if (!is_array($contents)) {
                continue;
            }

            foreach ($contents as $cid) {
                if ((int) $cid !== $postId) {
                    continue;
                }

                $courseId = (int) wp_get_post_parent_id($chid);
                if ($courseId <= 0) {
                    $courseId = (int) get_post_meta($chid, '_sikshya_chapter_course_id', true);
                }
                if ($courseId > 0) {
                    return $memo[$postId] = $courseId;
                }
            }
        }

        return $memo[$postId] = 0;
    }
}
