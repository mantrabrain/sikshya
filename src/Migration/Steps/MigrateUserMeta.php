<?php

/**
 * Rename legacy user-meta keys to the rewrite's `_sikshya_billing_*`
 * convention. Other Sikshya user-meta keys (`sikshya_avatar_attachment_id`,
 * `sikshya_instructor_*`, `sikshya_student_*`) are kept as-is because the
 * rewrite re-uses the same names.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrateUserMeta extends AbstractStep
{
    /** @var array<string,string> */
    private const RENAME_MAP = [
        'billing_first_name' => '_sikshya_billing_first_name',
        'billing_last_name' => '_sikshya_billing_last_name',
        'billing_country' => '_sikshya_billing_country',
        'billing_street_address_1' => '_sikshya_billing_address_1',
        'billing_street_address_2' => '_sikshya_billing_address_2',
        'billing_postcode' => '_sikshya_billing_postcode',
        'billing_city' => '_sikshya_billing_city',
        'billing_state' => '_sikshya_billing_state',
        'billing_phone' => '_sikshya_billing_phone',
        'billing_email' => '_sikshya_billing_email',
    ];

    public function id(): string
    {
        return 'user_meta';
    }

    public function description(): string
    {
        return __('Rename legacy billing user-meta keys.', 'sikshya');
    }

    public function expectedItemCount(): ?int
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return null;
        }
        $keys = array_keys(self::RENAME_MAP);
        $placeholders = implode(',', array_fill(0, count($keys), '%s'));
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key IN ($placeholders)",
            $keys
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
        $processed = 0;

        foreach (self::RENAME_MAP as $legacy => $rewrite) {
            if ($processed >= $batchSize) {
                break;
            }

            if ($dryRun) {
                $count = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
                        $legacy
                    )
                );
                if ($count > 0) {
                    $logger->info(sprintf('[dry-run] Would rename %d user_meta rows: %s -> %s', $count, $legacy, $rewrite));
                    $processed += $count;
                }
                continue;
            }

            $sql = $wpdb->prepare(
                "UPDATE IGNORE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s",
                $rewrite,
                $legacy
            );
            $updated = (int) $wpdb->query($sql);

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
                    $legacy
                )
            );

            if ($updated > 0) {
                $processed += $updated;
                $state->incrementStepCount($this->id(), $rewrite, $updated);
                $logger->info(sprintf('Renamed %d user_meta rows: %s -> %s', $updated, $legacy, $rewrite));
            }
        }

        $state->save();

        // Dry-run mode is single-pass — nothing is mutated, so the COUNT
        // returns the same number on re-entry. Mark complete on dry-run.
        if ($processed === 0 || $dryRun) {
            $this->markComplete($state);
        }

        return $processed;
    }
}
