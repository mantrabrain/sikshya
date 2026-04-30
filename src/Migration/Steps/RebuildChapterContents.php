<?php

/**
 * Walk every migrated `sik_course` and write the rewrite's curriculum
 * meta:
 *
 *   - `_sikshya_chapters` on the course: ordered array of chapter post IDs.
 *   - `_sikshya_contents` on each chapter: ordered array of lesson/quiz IDs.
 *
 * Order is taken from `_sikshya_chapter_order` (chapters) and `_sikshya_order`
 * (lessons/quizzes), with `ID ASC` as a tiebreak.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class RebuildChapterContents extends AbstractStep
{
    public function id(): string
    {
        return 'rebuild_chapter_contents';
    }

    public function description(): string
    {
        return __('Rebuild course/chapter/content order metadata.', 'sikshya');
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
                'sik_course'
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
                'sik_course',
                $cursor,
                max(1, $batchSize)
            )
        );

        if (!is_array($rows) || count($rows) === 0) {
            $this->markComplete($state);
            return 0;
        }

        $processed = 0;
        $last_id = $cursor;

        foreach ($rows as $row) {
            $course_id = isset($row->ID) ? (int) $row->ID : 0;
            if ($course_id <= 0) {
                continue;
            }
            $last_id = $course_id;

            $chapters = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.ID, COALESCE(CAST(pm.meta_value AS UNSIGNED), p.menu_order) AS sort_order"
                    . " FROM {$wpdb->posts} p"
                    . " LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s"
                    . " WHERE p.post_type = %s AND p.post_parent = %d"
                    . " ORDER BY sort_order ASC, p.ID ASC",
                    '_sikshya_chapter_order',
                    'sik_chapter',
                    $course_id
                )
            );
            $chapter_ids = is_array($chapters) ? array_map(static fn($r) => (int) $r->ID, $chapters) : [];

            if ($dryRun) {
                $logger->info(sprintf(
                    '[dry-run] Course #%d would record %d chapters.',
                    $course_id,
                    count($chapter_ids)
                ));
            } else {
                update_post_meta($course_id, '_sikshya_chapters', $chapter_ids);
                $state->incrementStepCount($this->id(), 'courses', 1);
                $state->incrementStepCount($this->id(), 'chapters', count($chapter_ids));
            }

            foreach ($chapter_ids as $chapter_id) {
                $contents = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT p.ID, p.post_type, COALESCE(CAST(pm.meta_value AS UNSIGNED), p.menu_order) AS sort_order"
                        . " FROM {$wpdb->posts} p"
                        . " INNER JOIN {$wpdb->postmeta} cm ON cm.post_id = p.ID AND cm.meta_key = %s AND cm.meta_value = %d"
                        . " LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s"
                        . " WHERE p.post_type IN ('sik_lesson','sik_quiz','sik_assignment')"
                        . " ORDER BY sort_order ASC, p.ID ASC",
                        '_sikshya_chapter_id',
                        $chapter_id,
                        '_sikshya_order'
                    )
                );
                $content_ids = is_array($contents) ? array_map(static fn($r) => (int) $r->ID, $contents) : [];

                if (!$dryRun) {
                    update_post_meta($chapter_id, '_sikshya_contents', $content_ids);
                    $state->incrementStepCount($this->id(), 'contents', count($content_ids));
                }
            }
            $processed++;
        }

        if (!$dryRun) {
            $state->setStepCursor($this->id(), $last_id);
        }
        $state->save();

        if ($dryRun) {
            $this->markComplete($state);
        }

        return $processed;
    }
}
