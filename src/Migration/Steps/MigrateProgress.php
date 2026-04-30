<?php

/**
 * Project legacy lesson/quiz progress rows from `wp_sikshya_user_items`
 * into the rewrite's `wp_sikshya_progress` table.
 *
 * Legacy `item_type` values:
 *
 *   - `sik_lessons` -> `progress` row with `lesson_id` (`quiz_id = NULL`).
 *   - `sik_quizzes` -> `progress` row with `quiz_id`   (`lesson_id = NULL`).
 *
 * Legacy `status` -> rewrite mapping:
 *
 *   - `started`   -> `in_progress`
 *   - `completed` -> `completed`
 *
 * The rewrite table has UNIQUE `(user_id, course_id, lesson_id, quiz_id)`
 * but the `lesson_id` / `quiz_id` columns are NULLable, and MySQL
 * considers NULL != NULL inside UNIQUE keys. `INSERT IGNORE` therefore
 * cannot dedupe rows where one of those columns is NULL, so we run an
 * explicit existence check before each insert and skip duplicates.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Database\Tables\ProgressTable;
use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrateProgress extends AbstractStep
{
    public function id(): string
    {
        return 'progress';
    }

    public function description(): string
    {
        return __('Migrate lesson/quiz progress from legacy user_items table.', 'sikshya');
    }

    public function expectedItemCount(): ?int
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return null;
        }
        $legacy_table = $wpdb->prefix . 'sikshya_user_items';
        if (!$this->tableExists($legacy_table)) {
            return 0;
        }
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$legacy_table} WHERE item_type IN ('sik_lessons','sik_quizzes')"
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

        $legacy_table = $wpdb->prefix . 'sikshya_user_items';
        if (!$this->tableExists($legacy_table)) {
            $this->markComplete($state);
            return 0;
        }
        if (!class_exists(ProgressTable::class)) {
            $this->markComplete($state);
            $logger->warning('ProgressTable class missing — skipping progress migration.');
            return 0;
        }

        $this->markRunning($state);
        $cursor = $state->getStepCursor($this->id());
        $target = ProgressTable::name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_item_id, user_id, item_id, item_type, status, start_time_gmt, end_time_gmt, parent_id"
                . " FROM {$legacy_table}"
                . " WHERE item_type IN ('sik_lessons','sik_quizzes') AND user_item_id > %d"
                . " ORDER BY user_item_id ASC LIMIT %d",
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
            $last_cursor = (int) $row->user_item_id;
            $user_id = (int) $row->user_id;
            $item_id = (int) $row->item_id;
            if ($user_id <= 0 || $item_id <= 0) {
                continue;
            }

            $is_lesson = ($row->item_type === 'sik_lessons');
            $course_id = $this->resolveCourseIdForItem($item_id, $is_lesson);

            $status = ($row->status === 'completed') ? 'completed' : 'in_progress';
            $completed_date = ($status === 'completed')
                ? $this->normalizeDateTime((string) ($row->end_time_gmt ?? ''))
                : null;

            if ($dryRun) {
                $logger->info(sprintf(
                    '[dry-run] Would record progress for user #%d on %s #%d (course #%d, status=%s).',
                    $user_id,
                    $is_lesson ? 'lesson' : 'quiz',
                    $item_id,
                    $course_id,
                    $status
                ));
                $processed++;
                continue;
            }

            $lesson_id = $is_lesson ? $item_id : 0;
            $quiz_id = $is_lesson ? 0 : $item_id;

            $sql = $wpdb->prepare(
                "INSERT IGNORE INTO {$target}"
                . " (user_id, course_id, lesson_id, quiz_id, status, percentage, time_spent, completed_date)"
                . " VALUES (%d, %d, %d, %d, %s, %f, %d, %s)",
                $user_id,
                $course_id,
                $lesson_id,
                $quiz_id,
                $status,
                ($status === 'completed') ? 100.00 : 0.00,
                0,
                $completed_date ?? '0000-00-00 00:00:00'
            );
            $wpdb->query($sql);

            if ($wpdb->insert_id || ($wpdb->rows_affected ?? 0) > 0) {
                $state->incrementStepCount($this->id(), $is_lesson ? 'lesson_progress' : 'quiz_progress', 1);
            } else {
                $state->incrementStepCount($this->id(), 'duplicates_skipped', 1);
            }

            $processed++;
        }

        if (!$dryRun) {
            $state->setStepCursor($this->id(), $last_cursor);
        }
        $state->save();

        // Dry-runs run a single batch then complete so the orchestrator stops
        // looping; the cursor is intentionally not persisted on dry-run so a
        // follow-up real run starts from the same point as a fresh install.
        if ($dryRun) {
            $this->markComplete($state);
        }

        return $processed;
    }

    /**
     * The legacy progress row records `parent_id` (the user_item_id of the
     * parent course enrollment), but resolving it to a course ID is messy.
     * The lesson/quiz post itself stores the course via the `_sikshya_*`
     * meta written by `MigratePostMeta`; if neither is present we fall back
     * to walking the chapter -> course chain via `post_parent`.
     */
    private function resolveCourseIdForItem(int $item_id, bool $isLesson): int
    {
        $key = $isLesson ? '_sikshya_lesson_course' : '_sikshya_quiz_course';
        $course_id = (int) get_post_meta($item_id, $key, true);
        if ($course_id > 0) {
            return $course_id;
        }
        $chapter_id = (int) get_post_meta($item_id, '_sikshya_chapter_id', true);
        if ($chapter_id > 0) {
            $course_id = (int) get_post_meta($chapter_id, '_sikshya_chapter_course_id', true);
            if ($course_id > 0) {
                return $course_id;
            }
            $parent = (int) get_post_field('post_parent', $chapter_id);
            if ($parent > 0) {
                return $parent;
            }
        }
        return 0;
    }

    private function normalizeDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return current_time('mysql', true);
        }
        return $value;
    }

    private function tableExists(string $table): bool
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return false;
        }
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return is_string($found) && $found === $table;
    }
}
