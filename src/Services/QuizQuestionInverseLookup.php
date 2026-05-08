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
 * Stored meta is PHP-serialized; we match the integer token {@see maybe_serialize()}.
 */
final class QuizQuestionInverseLookup
{
    /**
     * @return int Quiz post ID or 0.
     */
    public static function firstPublishedQuizIdForQuestion(int $question_id): int
    {
        global $wpdb;

        $question_id = absint($question_id);
        if ($question_id <= 0) {
            return 0;
        }

        // Serialized int entry looks like `i:123;` inside `_sikshya_quiz_questions`.
        $needle = 'i:' . $question_id . ';';
        $like = '%' . $wpdb->esc_like($needle) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- single bounded lookup with dynamic LIKE.
        $sql = $wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} AS pm
			INNER JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id AND p.post_type = %s AND p.post_status = %s
			WHERE pm.meta_key = %s AND pm.meta_value LIKE %s
			LIMIT 1",
            PostTypes::QUIZ,
            'publish',
            '_sikshya_quiz_questions',
            $like
        );

        return (int) $wpdb->get_var($sql);
    }
}
