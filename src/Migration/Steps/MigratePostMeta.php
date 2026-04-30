<?php

/**
 * Rename legacy post-meta keys to the rewrite's `_sikshya_*` keys.
 *
 * Each mapping is constrained to a target post type so we don't accidentally
 * rename keys that other plugins use. Renames go through `wpdb` directly
 * (`UPDATE IGNORE`) because `update_post_meta` would force an upsert and
 * preserve the legacy row. The `IGNORE` clause swallows any UNIQUE-key
 * collision when a post already has both the legacy and rewrite keys
 * (idempotent re-run safety).
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class MigratePostMeta extends AbstractStep
{
    /**
     * Map of `post_type` => `legacy_meta_key` => `rewrite_meta_key`.
     * Post types referenced here are the **rewrite** slugs (after
     * `MigratePostTypes` and `MigrateSectionsToChapters` have run), which
     * is why this step is scheduled after them.
     *
     * @var array<string, array<string, string>>
     */
    private const RENAME_MAP = [
        'sik_course' => [
            'sikshya_course_regular_price' => '_sikshya_price',
            'sikshya_course_discounted_price' => '_sikshya_sale_price',
            'sikshya_course_duration' => '_sikshya_duration',
            'sikshya_course_duration_time' => '_sikshya_duration_unit',
            'sikshya_course_level' => '_sikshya_difficulty',
            'sikshya_course_maximum_students' => '_sikshya_max_students',
            'sikshya_course_video_source' => '_sikshya_video_source',
            'sikshya_course_youtube_video_url' => '_sikshya_video_url',
            'sikshya_course_outcomes' => '_sikshya_learning_outcomes',
            'sikshya_course_requirements' => '_sikshya_target_audience',
            'sikshya_instructor' => '_sikshya_instructor',
        ],
        'sik_lesson' => [
            'sikshya_lesson_duration' => '_sikshya_lesson_duration',
            'sikshya_lesson_duration_time' => '_sikshya_lesson_duration_unit',
            'sikshya_is_preview_lesson' => '_sikshya_is_free',
            'sikshya_lesson_video_source' => '_sikshya_lesson_video_source',
            'sikshya_lesson_youtube_video_url' => '_sikshya_lesson_video_url',
            'sikshya_order_number' => '_sikshya_order',
            'section_id' => '_sikshya_chapter_id',
            'course_id' => '_sikshya_lesson_course',
        ],
        'sik_quiz' => [
            'sikshya_order_number' => '_sikshya_order',
            'section_id' => '_sikshya_chapter_id',
            'course_id' => '_sikshya_quiz_course',
        ],
        // NOTE: `sik_question` post-meta is intentionally NOT renamed here.
        // Question records carry a serialized answers shape that the rewrite
        // can't read directly; {@see TransformQuestions} handles the value
        // transformation and deletes the legacy keys after writing the
        // canonical ones. The relationship from quiz -> questions is rebuilt
        // by {@see RebuildQuizQuestions}.
    ];

    public function id(): string
    {
        return 'post_meta';
    }

    public function description(): string
    {
        return __('Rename legacy post-meta keys to the rewrite naming.', 'sikshya');
    }

    public function expectedItemCount(): ?int
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return null;
        }
        $count = 0;
        foreach (self::RENAME_MAP as $post_type => $keys) {
            $legacy_keys = array_keys($keys);
            $placeholders = implode(',', array_fill(0, count($legacy_keys), '%s'));
            $params = array_merge([$post_type], $legacy_keys);
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id"
                . " WHERE p.post_type = %s AND pm.meta_key IN ($placeholders)",
                $params
            );
            $count += (int) $wpdb->get_var($sql);
        }
        return $count;
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
        $processed = 0;

        foreach (self::RENAME_MAP as $post_type => $keys) {
            foreach ($keys as $legacy_key => $rewrite_key) {
                if ($processed >= $batchSize) {
                    break 2;
                }

                $remaining = max(1, $batchSize - $processed);

                if ($dryRun) {
                    $sql = $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id"
                        . " WHERE p.post_type = %s AND pm.meta_key = %s",
                        $post_type,
                        $legacy_key
                    );
                    $count = (int) $wpdb->get_var($sql);
                    if ($count > 0) {
                        $logger->info(sprintf(
                            '[dry-run] Would rename %d meta rows on %s: %s -> %s',
                            $count,
                            $post_type,
                            $legacy_key,
                            $rewrite_key
                        ));
                        $processed += min($count, $remaining);
                        $state->incrementStepCount($this->id(), $post_type . ':' . $rewrite_key, min($count, $remaining));
                    }
                    continue;
                }

                // MySQL doesn't allow LIMIT on multi-table UPDATEs, so we
                // first collect the candidate post IDs (capped) and then
                // run an UPDATE IGNORE / DELETE constrained to that set.
                $ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT pm.meta_id FROM {$wpdb->postmeta} pm"
                        . " INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id"
                        . " WHERE p.post_type = %s AND pm.meta_key = %s"
                        . " ORDER BY pm.meta_id ASC LIMIT %d",
                        $post_type,
                        $legacy_key,
                        $remaining
                    )
                );
                if (!is_array($ids) || count($ids) === 0) {
                    continue;
                }

                $ids = array_map('intval', $ids);
                $in_clause = implode(',', $ids);

                $updated = (int) $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE IGNORE {$wpdb->postmeta} SET meta_key = %s WHERE meta_id IN ($in_clause) AND meta_key = %s",
                        $rewrite_key,
                        $legacy_key
                    )
                );

                // Drop any rows that survived the UPDATE IGNORE — they
                // collided with an existing rewrite row and are now
                // duplicates we can discard safely.
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ($in_clause) AND meta_key = %s",
                        $legacy_key
                    )
                );

                if (is_int($updated) && $updated > 0) {
                    $processed += $updated;
                    $state->incrementStepCount($this->id(), $post_type . ':' . $rewrite_key, $updated);
                    $logger->info(sprintf(
                        'Renamed %d meta rows on %s: %s -> %s',
                        $updated,
                        $post_type,
                        $legacy_key,
                        $rewrite_key
                    ));
                }
            }
        }

        $state->save();

        // Dry-run is single-pass — nothing is mutated, so the COUNT/SELECT
        // returns the same rows on the next call. Mark complete on dry-run.
        if ($processed === 0 || $dryRun) {
            $this->markComplete($state);
        }

        return $processed;
    }
}
