<?php

/**
 * Locate a quiz that references a question CPT via `_sikshya_quiz_questions`.
 *
 * @package Sikshya\Services
 */

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Find a published quiz whose saved question-id list contains a given question.
 *
 * Stored meta is PHP-serialized, so the only way to ask "which quiz holds this
 * question" is a `LIKE '%i:123;%'` over `postmeta`. That leading wildcard means
 * no index can be used: every call reads the whole table.
 *
 * One call is acceptable. Twenty are not, and twenty is exactly what the
 * question list asks for — the `sikshya_learn_view_url` REST field runs this
 * once per row. Measured on a site with 291,956 postmeta rows, that was 402ms
 * of a 535ms response, and it grows with the table.
 *
 * So lookups are **batched and memoised**: {@see self::prime()} resolves a
 * whole page of questions in a single scan, and {@see self::forget()} exists
 * for tests. Behaviour per question is unchanged — the same quiz id comes
 * back, including 0 when nothing references it.
 */
final class QuizQuestionInverseLookup
{
    /**
     * Resolved question id => quiz id (0 means "looked, found nothing").
     *
     * @var array<int, int>
     */
    private static array $resolved = [];

    /** Keep one batched statement well inside any sane query-size limit. */
    private const MAX_BATCH = 100;

    /**
     * @return int Quiz post ID or 0.
     */
    public static function firstPublishedQuizIdForQuestion(int $question_id): int
    {
        $question_id = absint($question_id);
        if ($question_id <= 0) {
            return 0;
        }

        if (!array_key_exists($question_id, self::$resolved)) {
            self::prime([$question_id]);
        }

        return self::$resolved[$question_id] ?? 0;
    }

    /**
     * Resolve many questions in one pass over `postmeta`.
     *
     * Ids already resolved are skipped, so priming a page twice is free.
     *
     * @param int[] $question_ids
     */
    public static function prime(array $question_ids): void
    {
        global $wpdb;

        $wanted = [];
        foreach ($question_ids as $id) {
            $id = absint($id);
            if ($id > 0 && !array_key_exists($id, self::$resolved)) {
                $wanted[$id] = true;
            }
        }
        $wanted = array_keys($wanted);
        if ($wanted === []) {
            return;
        }

        foreach (array_chunk($wanted, self::MAX_BATCH) as $chunk) {
            // Every id in the chunk is marked resolved up front, so a question
            // no quiz references is remembered as 0 rather than re-scanned.
            foreach ($chunk as $id) {
                self::$resolved[$id] = 0;
            }

            $clauses = [];
            $args = [PostTypes::QUIZ, 'publish', '_sikshya_quiz_questions'];
            foreach ($chunk as $id) {
                $clauses[] = 'pm.meta_value LIKE %s';
                $args[] = '%' . $wpdb->esc_like('i:' . $id . ';') . '%';
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are built above and passed through prepare().
            $sql = $wpdb->prepare(
                "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} AS pm
				INNER JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id AND p.post_type = %s AND p.post_status = %s
				WHERE pm.meta_key = %s AND (" . implode(' OR ', $clauses) . ')',
                $args
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $rows = $wpdb->get_results($sql);
            if (!is_array($rows)) {
                continue;
            }

            // A quiz row can satisfy several of the questions we asked about,
            // so each row is checked against the whole chunk.
            foreach ($rows as $row) {
                $quiz_id = (int) $row->post_id;
                if ($quiz_id <= 0) {
                    continue;
                }
                $value = (string) $row->meta_value;
                foreach ($chunk as $id) {
                    if (self::$resolved[$id] !== 0) {
                        continue;   // first match wins, as before
                    }
                    if (strpos($value, 'i:' . $id . ';') !== false) {
                        self::$resolved[$id] = $quiz_id;
                    }
                }
            }
        }
    }

    /**
     * Drop the memo.
     *
     * Hooked to writes of `_sikshya_quiz_questions` (see {@see self::listen()})
     * so a request that changes a quiz's questions and then asks about them —
     * an import, the content bank, AI apply — cannot be answered from before
     * the change. Without that, memoising would have been a correctness
     * regression rather than a speed-up.
     */
    public static function forget(): void
    {
        self::$resolved = [];
    }

    /**
     * Watch for changes to any quiz's question list.
     */
    public static function listen(): void
    {
        $forget = static function ($meta_id, $object_id, $meta_key): void {
            unset($meta_id, $object_id);
            if ($meta_key === '_sikshya_quiz_questions') {
                self::forget();
            }
        };

        foreach (['updated_post_meta', 'added_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, $forget, 10, 3);
        }
    }
}
