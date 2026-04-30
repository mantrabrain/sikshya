<?php

/**
 * Convert legacy `sik_sections` posts to rewrite `sik_chapter` posts.
 *
 * The rewrite's chapter CPT is hierarchical and uses the post's
 * `post_parent` to express the chapter -> course relationship, plus a
 * `_sikshya_chapter_course_id` meta as a denormalized fast-lookup. The
 * legacy plugin stored the relationship purely via the `course_id`
 * post-meta on the section. We:
 *
 *  1. Read `course_id` from each section post-meta.
 *  2. Set `post_parent` to that course ID and rewrite `post_type` to
 *     `sik_chapter`.
 *  3. Rename `course_id` -> `_sikshya_chapter_course_id` and
 *     `section_order` -> `_sikshya_chapter_order`.
 *  4. Tag with `_sikshya_migrated_from_legacy = sik_sections`.
 *
 * After the chapter rename a follow-up step ({@see RebuildChapterContents})
 * walks every course and writes the `_sikshya_chapters` array.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrateSectionsToChapters extends AbstractStep
{
    public function id(): string
    {
        return 'sections_to_chapters';
    }

    public function description(): string
    {
        return __('Convert legacy course sections into hierarchical chapters.', 'sikshya');
    }

    public function expectedItemCount(): ?int
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return null;
        }
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'sik_sections')
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

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC LIMIT %d",
                'sik_sections',
                max(1, $batchSize)
            )
        );

        if (!is_array($rows) || count($rows) === 0) {
            $this->markComplete($state);
            return 0;
        }

        $processed = 0;
        foreach ($rows as $row) {
            $section_id = isset($row->ID) ? (int) $row->ID : 0;
            if ($section_id <= 0) {
                continue;
            }

            $course_id = (int) get_post_meta($section_id, 'course_id', true);
            $section_order = (int) get_post_meta($section_id, 'section_order', true);

            if ($dryRun) {
                $logger->info(sprintf(
                    '[dry-run] Would convert section #%d to chapter under course #%d (order %d).',
                    $section_id,
                    $course_id,
                    $section_order
                ));
            } else {
                $update = [
                    'post_type' => 'sik_chapter',
                    'menu_order' => $section_order,
                ];
                if ($course_id > 0) {
                    $update['post_parent'] = $course_id;
                }
                $wpdb->update(
                    $wpdb->posts,
                    $update,
                    ['ID' => $section_id]
                );

                if ($course_id > 0) {
                    update_post_meta($section_id, '_sikshya_chapter_course_id', $course_id);
                }
                if ($section_order > 0) {
                    update_post_meta($section_id, '_sikshya_chapter_order', $section_order);
                    update_post_meta($section_id, '_sikshya_order', $section_order);
                }
                update_post_meta($section_id, '_sikshya_migrated_from_legacy', 'sik_sections');
                clean_post_cache($section_id);
            }

            $processed++;
            $state->incrementStepCount($this->id(), 'chapters', 1);
        }

        $state->save();

        // Dry-run is single-pass: nothing is mutated, so the SELECT keeps
        // returning the same rows. Mark complete unconditionally for dry-runs.
        if ($processed === 0 || $dryRun) {
            $this->markComplete($state);
        }

        return $processed;
    }
}
