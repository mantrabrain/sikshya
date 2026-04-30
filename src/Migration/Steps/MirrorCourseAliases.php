<?php

/**
 * Mirror course-meta keys between the rewrite's two-key alias pairs.
 *
 * The rewrite registers both `_sikshya_price` (set by sample data + the
 * legacy migration) and `_sikshya_course_price` (read by the storefront's
 * `CheckoutService`). Same story for `_sikshya_duration` /
 * `_sikshya_course_duration` and `_sikshya_difficulty` /
 * `_sikshya_course_level`. {@see PostTypeManager::registerMeta()} registers
 * both keys for a reason — the editor and the checkout each pick a
 * different one historically.
 *
 * After {@see MigratePostMeta} renames the legacy `sikshya_course_*` keys
 * to one half of the alias pair, this step copies the value into the other
 * half so all reading code paths see the migrated value. We never overwrite
 * a key that the admin has already set manually.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class MirrorCourseAliases extends AbstractStep
{
    /**
     * Each pair is mirrored in both directions; we copy from whichever side
     * has a non-empty value into the empty one.
     *
     * @var array<int, array{0:string,1:string}>
     */
    private const ALIAS_PAIRS = [
        ['_sikshya_price', '_sikshya_course_price'],
        ['_sikshya_duration', '_sikshya_course_duration'],
        ['_sikshya_difficulty', '_sikshya_course_level'],
    ];

    public function id(): string
    {
        return 'mirror_course_aliases';
    }

    public function description(): string
    {
        return __('Mirror price/duration/level meta into both rewrite alias keys.', 'sikshya');
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
        $last_cursor = $cursor;

        foreach ($rows as $row) {
            $course_id = isset($row->ID) ? (int) $row->ID : 0;
            if ($course_id <= 0) {
                continue;
            }

            foreach (self::ALIAS_PAIRS as [$a, $b]) {
                $val_a = get_post_meta($course_id, $a, true);
                $val_b = get_post_meta($course_id, $b, true);

                $a_empty = ($val_a === '' || $val_a === null || $val_a === false);
                $b_empty = ($val_b === '' || $val_b === null || $val_b === false);

                if ($a_empty && !$b_empty) {
                    if ($dryRun) {
                        $logger->info(sprintf('[dry-run] Course #%d would mirror %s -> %s.', $course_id, $b, $a));
                    } else {
                        update_post_meta($course_id, $a, $val_b);
                        $state->incrementStepCount($this->id(), 'mirrored', 1);
                    }
                } elseif (!$a_empty && $b_empty) {
                    if ($dryRun) {
                        $logger->info(sprintf('[dry-run] Course #%d would mirror %s -> %s.', $course_id, $a, $b));
                    } else {
                        update_post_meta($course_id, $b, $val_a);
                        $state->incrementStepCount($this->id(), 'mirrored', 1);
                    }
                }
            }

            $processed++;
            if (!$dryRun) {
                $last_cursor = $course_id;
            }
        }

        if (!$dryRun) {
            $state->setStepCursor($this->id(), $last_cursor);
        }
        $state->save();

        return $processed;
    }
}
