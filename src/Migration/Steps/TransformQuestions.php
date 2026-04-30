<?php

/**
 * Convert legacy quiz-question post-meta into the rewrite's storage shape.
 *
 * Legacy `sik_question` posts persist the question definition across three
 * post-meta keys:
 *
 *   - `type`             string, one of `single` | `single_image` | `multi` | `multi_image`
 *   - `answers`          array<int, array{value:string, image:string}> keyed by answer id
 *   - `correct_answers`  array<int> — the answer ids the author marked correct
 *
 * The rewrite stores a flat shape that the React quiz player and REST routes
 * understand:
 *
 *   - `_sikshya_question_type`           string, one of the canonical types in
 *                                        {@see \Sikshya\Services\QuizService::evaluateAnswer()}
 *   - `_sikshya_question_options`        list<string> — option text only
 *   - `_sikshya_question_correct_answer` string for single-choice
 *                                        (the option text that's correct), or
 *                                        JSON-encoded list<string> for
 *                                        multi-response answers.
 *
 * Type mapping:
 *
 *   - `single`        -> `multiple_choice`
 *   - `single_image`  -> `multiple_choice`  (image URLs are dropped because the
 *                       rewrite renders option text only; admins can re-upload
 *                       through the Pro Advanced Quiz add-on if needed)
 *   - `multi`         -> `multiple_response` (Pro Advanced Quiz feature; data
 *                       is preserved even on Free so re-enabling the add-on
 *                       restores edits)
 *   - `multi_image`   -> `multiple_response`
 *
 * The legacy keys are deleted only after their replacement keys are written.
 * Per-question failures don't abort the run — they bump an `errors` counter
 * and log to the migration log.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class TransformQuestions extends AbstractStep
{
    /** @var array<string,string> */
    private const TYPE_MAP = [
        'single' => 'multiple_choice',
        'single_image' => 'multiple_choice',
        'multi' => 'multiple_response',
        'multi_image' => 'multiple_response',
        'true_false' => 'true_false',
        'short_answer' => 'short_answer',
    ];

    public function id(): string
    {
        return 'transform_questions';
    }

    public function description(): string
    {
        return __('Transform legacy quiz-question structure to the rewrite shape.', 'sikshya');
    }

    public function expectedItemCount(): ?int
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return null;
        }
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p"
                . " INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key IN ('type','answers','correct_answers')"
                . " WHERE p.post_type = %s",
                'sik_question'
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
                'sik_question',
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
            $qid = isset($row->ID) ? (int) $row->ID : 0;
            if ($qid <= 0) {
                continue;
            }
            $advance_cursor = $qid;
            $processed++;

            $legacy_type = (string) get_post_meta($qid, 'type', true);
            $legacy_answers_raw = get_post_meta($qid, 'answers', true);
            $legacy_correct_raw = get_post_meta($qid, 'correct_answers', true);

            // Already migrated? Cheap idempotency check that skips the rewrite
            // when the canonical key is already populated.
            $existing_type = (string) get_post_meta($qid, '_sikshya_question_type', true);
            if ($existing_type !== '' && $legacy_type === '' && !is_array($legacy_answers_raw)) {
                $state->incrementStepCount($this->id(), 'already_migrated', 1);
                if (!$dryRun) {
                    $last_cursor = $advance_cursor;
                }
                continue;
            }

            try {
                [$new_type, $options, $correct] = $this->transform($legacy_type, $legacy_answers_raw, $legacy_correct_raw);
            } catch (\Throwable $e) {
                $logger->warning(sprintf('Failed to transform question #%d: %s', $qid, $e->getMessage()));
                $state->incrementStepCount($this->id(), 'errors', 1);
                if (!$dryRun) {
                    $last_cursor = $advance_cursor;
                }
                continue;
            }

            if ($dryRun) {
                $logger->info(sprintf(
                    '[dry-run] Would transform question #%d: type=%s, options=%d, correct=%s.',
                    $qid,
                    $new_type,
                    count($options),
                    is_string($correct) ? '(string)' : '(json)'
                ));
                continue;
            }

            update_post_meta($qid, '_sikshya_question_type', $new_type);
            update_post_meta($qid, '_sikshya_question_options', array_values(array_map('strval', $options)));
            update_post_meta($qid, '_sikshya_question_correct_answer', is_string($correct) ? $correct : (string) wp_json_encode($correct));

            // Mirror title into the optional `_sikshya_question_text` key used by
            // the legacy frontend renderer in `QuizService::getQuizQuestions`.
            $title = (string) get_post_field('post_title', $qid);
            if ($title !== '' && (string) get_post_meta($qid, '_sikshya_question_text', true) === '') {
                update_post_meta($qid, '_sikshya_question_text', $title);
            }

            // Default points to 1 so scoring works out of the box. Admins can
            // change in the editor.
            $existing_points = (string) get_post_meta($qid, '_sikshya_question_points', true);
            if ($existing_points === '') {
                update_post_meta($qid, '_sikshya_question_points', 1);
            }

            // Drop the legacy keys now that the canonical replacements exist.
            delete_post_meta($qid, 'type');
            delete_post_meta($qid, 'answers');
            delete_post_meta($qid, 'correct_answers');

            update_post_meta($qid, '_sikshya_migrated_question_legacy_type', $legacy_type);

            $state->incrementStepCount($this->id(), 'transformed', 1);
            $state->incrementStepCount($this->id(), 'type_' . $new_type, 1);

            $last_cursor = $advance_cursor;
        }

        // Persist cursor only on real runs so a dry-run doesn't poison a
        // follow-up real run with a non-zero cursor.
        if (!$dryRun) {
            $state->setStepCursor($this->id(), $last_cursor);
        }
        $state->save();

        return $processed;
    }

    /**
     * Transform legacy `(type, answers, correct_answers)` into the rewrite
     * shape `(new_type, options[], correct)`.
     *
     * @param mixed $legacy_answers_raw
     * @param mixed $legacy_correct_raw
     *
     * @return array{0:string, 1:array<int,string>, 2:string|array<int,string>}
     */
    private function transform(string $legacy_type, $legacy_answers_raw, $legacy_correct_raw): array
    {
        $key = strtolower(trim($legacy_type));
        $new_type = self::TYPE_MAP[$key] ?? 'multiple_choice';

        $answers = is_array($legacy_answers_raw) ? $legacy_answers_raw : [];
        $correct_ids = is_array($legacy_correct_raw)
            ? array_map('strval', $legacy_correct_raw)
            : ($legacy_correct_raw === '' || $legacy_correct_raw === null ? [] : [(string) $legacy_correct_raw]);

        // Build ordered list of option text plus a (legacy-id => option-text)
        // lookup so we can resolve `correct_answers` (which references ids).
        $options = [];
        $id_to_text = [];

        foreach ($answers as $answer_id => $answer) {
            $text = '';
            if (is_array($answer)) {
                $text = isset($answer['value']) ? (string) $answer['value'] : '';
                if ($text === '' && isset($answer['image'])) {
                    $text = (string) $answer['image'];
                }
            } elseif (is_string($answer)) {
                $text = $answer;
            }
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $options[] = $text;
            $id_to_text[(string) $answer_id] = $text;
        }

        if ($new_type === 'multiple_response') {
            $correct = [];
            foreach ($correct_ids as $cid) {
                if (isset($id_to_text[$cid])) {
                    $correct[] = $id_to_text[$cid];
                }
            }
            return [$new_type, $options, array_values($correct)];
        }

        // Single-choice / true-false / short-answer all collapse to a string
        // value: the text of the first correct answer.
        $first_correct = '';
        foreach ($correct_ids as $cid) {
            if (isset($id_to_text[$cid])) {
                $first_correct = $id_to_text[$cid];
                break;
            }
        }

        return [$new_type, $options, $first_correct];
    }
}
