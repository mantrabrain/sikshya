<?php

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Resolve lesson / quiz / assignment IDs from course curriculum meta
 * (chapters → contents).
 *
 * The three public accessors share the same chapter-walk and only differ
 * in the post-type filter applied at the end. This used to be three
 * near-identical loops that each called `get_post_type($cid)` per
 * content item — for a course with 50 chapters × 8 contents that meant
 * 400 individual post-type lookups (uncached cold-path = 400 SELECTs).
 *
 * The refactor splits the work into two phases:
 *
 *   1. Walk `_sikshya_chapters` → `_sikshya_contents` once to gather all
 *      candidate IDs. This is pure meta walking (already cached by WP's
 *      post-meta cache), so it's cheap regardless of post count.
 *   2. Prime the post cache for the full ID set with `_prime_post_caches`
 *      (a single SELECT for everything), then `get_post_type()` becomes
 *      an O(1) cache hit per ID.
 *
 * Result: the public methods return the same ID lists as before, but
 * with **one** SQL query for post resolution instead of N.
 *
 * @package Sikshya\Services
 */
final class LearnerCurriculumHelper
{
    /**
     * Memoise the prime+gather pass per course per request — repeated
     * callers (Learn shell, REST endpoint, progress recompute) commonly
     * ask for `lessonIdsForCourse` and `quizIdsForCourse` back-to-back.
     *
     * @var array<int, array<string, array<int, int>>>
     */
    private static array $cache = [];

    /**
     * @return array<int, int>
     */
    public static function lessonIdsForCourse(int $course_id): array
    {
        return self::idsForCourse($course_id)[PostTypes::LESSON] ?? [];
    }

    /**
     * Quiz IDs attached to the course curriculum (chapters → contents).
     *
     * @return array<int, int>
     */
    public static function quizIdsForCourse(int $course_id): array
    {
        return self::idsForCourse($course_id)[PostTypes::QUIZ] ?? [];
    }

    /**
     * Assignment IDs attached to the course curriculum (chapters → contents).
     *
     * @return array<int, int>
     */
    public static function assignmentIdsForCourse(int $course_id): array
    {
        return self::idsForCourse($course_id)[PostTypes::ASSIGNMENT] ?? [];
    }

    /**
     * Internal: walk chapters → contents once, prime post cache once,
     * and partition the IDs by post type.
     *
     * @return array<string, array<int, int>> Map of post_type => int[]
     */
    private static function idsForCourse(int $course_id): array
    {
        if ($course_id <= 0) {
            return [];
        }
        if (isset(self::$cache[$course_id])) {
            return self::$cache[$course_id];
        }

        $chapter_ids = get_post_meta($course_id, '_sikshya_chapters', true);
        if (!is_array($chapter_ids)) {
            return self::$cache[$course_id] = [];
        }

        // Phase 1: gather all candidate IDs without resolving their post
        // type. Pure meta-cache work; no SQL beyond what WP already does.
        $candidate_ids = [];
        foreach ($chapter_ids as $ch_id) {
            $ch_id = (int) $ch_id;
            if ($ch_id <= 0) {
                continue;
            }
            $contents = get_post_meta($ch_id, '_sikshya_contents', true);
            if (!is_array($contents)) {
                continue;
            }
            foreach ($contents as $cid) {
                $cid = (int) $cid;
                if ($cid > 0) {
                    $candidate_ids[$cid] = true;
                }
            }
        }

        if ($candidate_ids === []) {
            return self::$cache[$course_id] = [];
        }

        $candidate_ids = array_keys($candidate_ids);

        // Phase 2: one batched SELECT. After this call, every subsequent
        // `get_post_type($cid)` for these IDs is an in-memory cache hit.
        _prime_post_caches($candidate_ids, false, false);

        $by_type = [
            PostTypes::LESSON     => [],
            PostTypes::QUIZ       => [],
            PostTypes::ASSIGNMENT => [],
        ];

        foreach ($candidate_ids as $cid) {
            $type = get_post_type($cid);
            if (isset($by_type[$type])) {
                $by_type[$type][] = $cid;
            }
        }

        return self::$cache[$course_id] = $by_type;
    }

    /**
     * Clear the in-process memo. Useful after destructive operations
     * that change a course's curriculum (chapter delete, content remove).
     * Not part of the public API today but kept addressable for tests.
     */
    public static function clearCache(int $course_id = 0): void
    {
        if ($course_id <= 0) {
            self::$cache = [];
            return;
        }
        unset(self::$cache[$course_id]);
    }
}
