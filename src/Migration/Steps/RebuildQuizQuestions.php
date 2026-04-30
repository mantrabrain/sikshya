<?php

/**
 * Rebuild the quiz->questions map for migrated quizzes.
 *
 * The legacy plugin stored the relationship on the question post via the
 * `quiz_id` post-meta key, the rewrite stores it on the **quiz** as a
 * `_sikshya_quiz_questions` array of question IDs. This step walks each
 * `sik_quiz` post and writes that array.
 *
 * Order: this step must run AFTER {@see MigratePostTypes} so the singular
 * `sik_quiz` slug exists, but BEFORE {@see TransformQuestions} (which
 * deletes the legacy `quiz_id` meta as part of its cleanup pass).
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class RebuildQuizQuestions extends AbstractStep
{
    public function id(): string
    {
        return 'rebuild_quiz_questions';
    }

    public function description(): string
    {
        return __('Build _sikshya_quiz_questions on each migrated quiz.', 'sikshya');
    }

    public function expectedItemCount(): ?int
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return null;
        }
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
                'sik_quiz'
            )
        );
    }

    public function executeBatch(
        MigrationState $state,
        LegacyMigrationLogger $logger,
        int $batchSize,
        bool $dryRun
    ): int {
        global $wpdb;
        if (!isset($wpdb)) {
            $this->markComplete($state);
            return 0;
        }

        $this->markRunning($state);

        $cursor = $state->getStepCursor($this->id());

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
                'sik_quiz',
                $cursor,
                max(1, $batchSize)
            )
        );

        if (!is_array($rows) || count($rows) === 0) {
            $this->markComplete($state);
            return 0;
        }

        $processed = 0;
        $last_cursor = $cursor;

        foreach ($rows as $row) {
            $quiz_id = isset($row->ID) ? (int) $row->ID : 0;
            if ($quiz_id <= 0) {
                continue;
            }

            // Look up the question IDs by both legacy and renamed keys so the
            // step is correct regardless of whether MigratePostMeta has run yet.
            // `menu_order` is the legacy ordering primitive used by the question
            // editor; fall back to ID for stability.
            $question_ids_rows = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT pm.post_id"
                    . " FROM {$wpdb->postmeta} pm"
                    . " INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = %s"
                    . " WHERE pm.meta_key IN ('quiz_id','_sikshya_quiz_id') AND pm.meta_value = %d"
                    . " GROUP BY pm.post_id"
                    . " ORDER BY p.menu_order ASC, p.ID ASC",
                    'sik_question',
                    $quiz_id
                )
            );

            $question_ids = is_array($question_ids_rows)
                ? array_values(array_unique(array_map('intval', $question_ids_rows)))
                : [];

            if ($dryRun) {
                $logger->info(sprintf(
                    '[dry-run] Quiz #%d would link %d questions.',
                    $quiz_id,
                    count($question_ids)
                ));
            } else {
                update_post_meta($quiz_id, '_sikshya_quiz_questions', $question_ids);
                $state->incrementStepCount($this->id(), 'quizzes', 1);
                $state->incrementStepCount($this->id(), 'questions_linked', count($question_ids));

                // Default a few quiz settings so the rewrite's quiz player has
                // working values out of the box. These mirror the rewrite's
                // sample-data defaults; admins can override afterwards.
                if ((string) get_post_meta($quiz_id, '_sikshya_quiz_passing_score', true) === '') {
                    update_post_meta($quiz_id, '_sikshya_quiz_passing_score', 70);
                }
                if ((string) get_post_meta($quiz_id, '_sikshya_quiz_attempts_allowed', true) === '') {
                    update_post_meta($quiz_id, '_sikshya_quiz_attempts_allowed', 0);
                }
            }

            $processed++;

            if (!$dryRun) {
                $last_cursor = $quiz_id;
            }
        }

        if (!$dryRun) {
            $state->setStepCursor($this->id(), $last_cursor);
        }
        $state->save();

        return $processed;
    }
}
