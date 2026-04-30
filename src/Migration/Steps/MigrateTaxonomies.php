<?php

/**
 * Rename legacy `sik_*` taxonomies to the rewrite's `sikshya_*` slugs in
 * the `wp_term_taxonomy` table. Term IDs and `wp_term_relationships`
 * entries are untouched, so existing course-to-category links survive the
 * rename.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrateTaxonomies extends AbstractStep
{
    /** @var array<string, string> */
    private const RENAME_MAP = [
        'sik_course_category' => 'sikshya_course_category',
        'sik_course_tag' => 'sikshya_course_tag',
    ];

    public function id(): string
    {
        return 'taxonomies';
    }

    public function description(): string
    {
        return __('Rename legacy taxonomies (course category, course tag).', 'sikshya');
    }

    public function expectedItemCount(): ?int
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return null;
        }
        $legacy = array_keys(self::RENAME_MAP);
        $placeholders = implode(',', array_fill(0, count($legacy), '%s'));
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ($placeholders)",
            $legacy
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
            if ($dryRun) {
                $count = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
                        $legacy
                    )
                );
                if ($count > 0) {
                    $logger->info(sprintf('[dry-run] Would rename %d %s rows -> %s', $count, $legacy, $rewrite));
                    $processed += $count;
                }
                continue;
            }

            $updated = $wpdb->update(
                $wpdb->term_taxonomy,
                ['taxonomy' => $rewrite],
                ['taxonomy' => $legacy],
                ['%s'],
                ['%s']
            );
            if ($updated && $updated > 0) {
                $processed += (int) $updated;
                $state->incrementStepCount($this->id(), $rewrite, (int) $updated);
                $logger->info(sprintf('Renamed %d term_taxonomy rows: %s -> %s', $updated, $legacy, $rewrite));
            }
        }

        if (!$dryRun) {
            // Bust the term cache so subsequent reads see the new taxonomy.
            wp_cache_flush();
        }

        $this->markComplete($state);
        return $processed;
    }
}
