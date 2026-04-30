<?php

/**
 * Project legacy course-enrollment rows from `wp_sikshya_user_items` into
 * the rewrite's `wp_sikshya_enrollments` table.
 *
 * Legacy rows where `item_type = sik_courses` represent the
 * student->course relationship. Status maps:
 *
 *   - `enrolled`  -> `enrolled`  (rewrite default)
 *   - `completed` -> `completed`
 *
 * Inserts use `INSERT IGNORE` to honor the rewrite table's
 * `UNIQUE (user_id, course_id)` constraint, so re-running the step is a
 * no-op once a row exists.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Database\Tables\EnrollmentsTable;
use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrateEnrollments extends AbstractStep
{
    public function id(): string
    {
        return 'enrollments';
    }

    public function description(): string
    {
        return __('Migrate course enrollments from legacy user_items table.', 'sikshya');
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
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$legacy_table} WHERE item_type = %s",
                'sik_courses'
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

        $legacy_table = $wpdb->prefix . 'sikshya_user_items';
        if (!$this->tableExists($legacy_table)) {
            $this->markComplete($state);
            return 0;
        }
        if (!class_exists(EnrollmentsTable::class)) {
            $this->markComplete($state);
            $logger->warning('EnrollmentsTable class missing — skipping enrollment migration.');
            return 0;
        }

        $this->markRunning($state);
        $cursor = $state->getStepCursor($this->id());
        $target = EnrollmentsTable::name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_item_id, user_id, item_id, status, start_time, start_time_gmt, end_time, end_time_gmt"
                . " FROM {$legacy_table}"
                . " WHERE item_type = %s AND user_item_id > %d"
                . " ORDER BY user_item_id ASC LIMIT %d",
                'sik_courses',
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
            $course_id = (int) $row->item_id;
            $status = $this->mapStatus((string) $row->status);
            $enrolled_date = $this->normalizeDateTime((string) ($row->start_time_gmt ?? $row->start_time ?? ''));
            $completed_date = ($status === 'completed')
                ? $this->normalizeDateTime((string) ($row->end_time_gmt ?? $row->end_time ?? ''))
                : null;

            if ($user_id <= 0 || $course_id <= 0) {
                continue;
            }

            if ($dryRun) {
                $logger->info(sprintf(
                    '[dry-run] Would enroll user #%d on course #%d (status=%s).',
                    $user_id,
                    $course_id,
                    $status
                ));
                $processed++;
                continue;
            }

            $sql = $wpdb->prepare(
                "INSERT IGNORE INTO {$target}"
                . " (user_id, course_id, status, enrolled_date, completed_date, payment_method, amount, transaction_id, progress, notes)"
                . " VALUES (%d, %d, %s, %s, %s, %s, %f, %s, %f, %s)",
                $user_id,
                $course_id,
                $status,
                $enrolled_date,
                $completed_date ?? '0000-00-00 00:00:00',
                'legacy',
                0.00,
                'legacy:' . $row->user_item_id,
                0.00,
                'Migrated from sikshya-old user_items #' . $row->user_item_id
            );
            $wpdb->query($sql);

            if ($wpdb->insert_id || ($wpdb->rows_affected ?? 0) > 0) {
                $state->incrementStepCount($this->id(), 'enrollments', 1);
            } else {
                $state->incrementStepCount($this->id(), 'duplicates_skipped', 1);
            }

            $processed++;
        }

        // Dry-runs are single-pass: persist the cursor on the state row only
        // for real runs so a dry-run cannot poison a follow-up real run by
        // skipping rows it never wrote. Mark complete on dry-run so the
        // orchestrator stops re-entering this step in a single conversation.
        if (!$dryRun) {
            $state->setStepCursor($this->id(), $last_cursor);
        }
        $state->save();

        if ($dryRun) {
            $this->markComplete($state);
        }

        return $processed;
    }

    private function mapStatus(string $legacy): string
    {
        switch ($legacy) {
            case 'completed':
                return 'completed';
            case 'cancelled':
                return 'cancelled';
            case 'enrolled':
            case 'started':
            default:
                return 'enrolled';
        }
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
