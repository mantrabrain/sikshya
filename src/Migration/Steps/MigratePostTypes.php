<?php

/**
 * Rename legacy plural post-type slugs to the rewrite's singular slugs in
 * `wp_posts` (preserving every post ID and relationship). Each migrated
 * post is tagged with `_sikshya_migrated_from_legacy` containing its
 * original legacy slug for traceability.
 *
 * Sections (`sik_sections`) are intentionally **not** handled here — they
 * become hierarchical chapters and have their own dedicated step
 * ({@see MigrateSectionsToChapters}) that also assigns `post_parent`.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class MigratePostTypes extends AbstractStep
{
    /** @var array<string, string> */
    private const RENAME_MAP = [
        'sik_courses' => 'sik_course',
        'sik_lessons' => 'sik_lesson',
        'sik_quizzes' => 'sik_quiz',
        'sik_questions' => 'sik_question',
    ];

    public function id(): string
    {
        return 'post_types';
    }

    public function description(): string
    {
        return __('Rename legacy course/lesson/quiz/question post types to singular slugs.', 'sikshya');
    }

    public function expectedItemCount(): ?int
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return null;
        }

        $legacy_types = array_keys(self::RENAME_MAP);
        $placeholders = implode(',', array_fill(0, count($legacy_types), '%s'));
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ($placeholders)",
            $legacy_types
        );
        return (int) $wpdb->get_var($sql);
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

        $remaining = $this->expectedItemCount();
        if ($remaining === 0) {
            $this->markComplete($state);
            return 0;
        }

        $processed = 0;

        foreach (self::RENAME_MAP as $legacy => $rewrite) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC LIMIT %d",
                    $legacy,
                    max(1, $batchSize - $processed)
                )
            );
            if (!is_array($rows) || count($rows) === 0) {
                continue;
            }

            foreach ($rows as $row) {
                $post_id = isset($row->ID) ? (int) $row->ID : 0;
                if ($post_id <= 0) {
                    continue;
                }
                if ($dryRun) {
                    $logger->info(sprintf('[dry-run] Would rename post #%d %s -> %s', $post_id, $legacy, $rewrite));
                } else {
                    $wpdb->update(
                        $wpdb->posts,
                        ['post_type' => $rewrite],
                        ['ID' => $post_id],
                        ['%s'],
                        ['%d']
                    );
                    update_post_meta($post_id, '_sikshya_migrated_from_legacy', $legacy);
                    clean_post_cache($post_id);
                }
                $processed++;
                $state->incrementStepCount($this->id(), $rewrite, 1);

                if ($processed >= $batchSize) {
                    break 2;
                }
            }
        }

        $state->save();

        // Dry-run is single-pass: nothing is mutated, so the same SELECT would
        // return the same rows on a re-entry. Mark complete to stop the loop.
        if ($processed === 0 || $dryRun) {
            $this->markComplete($state);
        }

        return $processed;
    }
}
