<?php

/**
 * Detect whether a site has data left over from the legacy `sikshya-old`
 * plugin. Used both to decide whether the migrator should run on activation
 * and to short-circuit no-op upgrades.
 *
 * @package Sikshya\Migration
 */

namespace Sikshya\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class LegacyDataDetector
{
    /**
     * Custom post types written by the legacy plugin.
     *
     * @var string[]
     */
    public const LEGACY_POST_TYPES = [
        'sik_courses',
        'sik_lessons',
        'sik_sections',
        'sik_quizzes',
        'sik_questions',
        'sik_orders',
    ];

    /**
     * Taxonomies written by the legacy plugin.
     *
     * @var string[]
     */
    public const LEGACY_TAXONOMIES = [
        'sik_course_category',
        'sik_course_tag',
    ];

    /**
     * `wp_options` keys listed for the Tools migration screen only.
     *
     * These overlap with the modern Sikshya rewrite (same option names), so they
     * must NOT alone trigger {@see hasLegacyData()} — otherwise fresh installs
     * fire a no-op migration and show the “migration completed” admin notice.
     *
     * @var string[]
     */
    public const LEGACY_OPTION_KEYS = [
        'sikshya_version',
        'sikshya_permalinks',
        'sikshya_account_page',
        'sikshya_login_page',
        'sikshya_cart_page',
        'sikshya_checkout_page',
        'sikshya_thankyou_page',
        'sikshya_currency',
        'sikshya_payment_gateways',
        'sikshya_payment_gateway_test_mode',
    ];

    /**
     * Custom DB tables created by the legacy plugin (suffix only — `wpdb`
     * prefix is applied at runtime).
     *
     * @var string[]
     */
    public const LEGACY_TABLE_SUFFIXES = [
        'sikshya_user_items',
        'sikshya_user_itemmeta',
        'sikshya_order_items',
        'sikshya_order_itemmeta',
        'sikshya_logs',
    ];

    /**
     * Build a structured fingerprint of detected legacy data.
     *
     * @return array{
     *     has_legacy_data: bool,
     *     options: string[],
     *     post_types: array<string,int>,
     *     taxonomies: array<string,int>,
     *     tables: string[],
     *     legacy_item_rows: int
     * }
     */
    public static function fingerprint(): array
    {
        global $wpdb;

        $found = [
            'has_legacy_data' => false,
            'options' => [],
            'post_types' => [],
            'taxonomies' => [],
            'tables' => [],
            'legacy_item_rows' => 0,
        ];

        if (!isset($wpdb) || !is_object($wpdb)) {
            return $found;
        }

        foreach (self::LEGACY_OPTION_KEYS as $option) {
            if (get_option($option, null) !== null) {
                $found['options'][] = $option;
            }
        }

        $placeholders = implode(',', array_fill(0, count(self::LEGACY_POST_TYPES), '%s'));
        $sql = $wpdb->prepare(
            "SELECT post_type, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type IN ($placeholders) GROUP BY post_type",
            self::LEGACY_POST_TYPES
        );
        $rows = $wpdb->get_results($sql);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $type = isset($row->post_type) ? (string) $row->post_type : '';
                $count = isset($row->total) ? (int) $row->total : 0;
                if ($type !== '' && $count > 0) {
                    $found['post_types'][$type] = $count;
                }
            }
        }

        $tax_placeholders = implode(',', array_fill(0, count(self::LEGACY_TAXONOMIES), '%s'));
        $sql = $wpdb->prepare(
            "SELECT taxonomy, COUNT(*) AS total FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ($tax_placeholders) GROUP BY taxonomy",
            self::LEGACY_TAXONOMIES
        );
        $rows = $wpdb->get_results($sql);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $tax = isset($row->taxonomy) ? (string) $row->taxonomy : '';
                $count = isset($row->total) ? (int) $row->total : 0;
                if ($tax !== '' && $count > 0) {
                    $found['taxonomies'][$tax] = $count;
                }
            }
        }

        foreach (self::LEGACY_TABLE_SUFFIXES as $suffix) {
            $table = $wpdb->prefix . $suffix;
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if (is_string($exists) && $exists === $table) {
                $found['tables'][] = $table;
            }
        }

        $found['legacy_item_rows'] = self::countUnmigratedLegacyItemRows();

        // Structural signals for scheduling migration: legacy CPTs, legacy taxonomies, or
        // unmigrated rows in the legacy user_items table.
        //
        // Table *presence* must NOT count: two of the suffixes above
        // (`sikshya_order_items`, `sikshya_logs`) are also created by the current Sikshya on
        // activation, so keying on existence made fresh installs run a no-op migration and show
        // "Legacy data migration completed successfully" forever. The table list stays on the
        // Tools fingerprint for support/debugging.
        //
        // Row *content* is a different matter, and is why `legacy_item_rows` is included.
        // Enrollments and progress live only in `wp_sikshya_user_items`, which the current
        // plugin never creates, and they migrate *after* the post types are renamed. A run
        // interrupted between those two points leaves no legacy CPTs behind, so a
        // post-types-only signal reports "nothing to migrate" and every learner's enrolment and
        // progress is silently abandoned in a table nothing reads.
        //
        // The rows signal is deliberately ignored once the migration has finished. Migration
        // *copies* out of `user_items` and never empties it, so an unconditional row check would
        // stay true for the life of the site — re-arming the pending flag on every activation
        // and bringing back the "migration completed successfully" notice this detector was
        // changed to stop showing. Gating on the state keeps the signal pointed at exactly the
        // case it is for: work that was started and not carried through.
        $migration_finished = MigrationState::load()->isFinished();

        $found['has_legacy_data'] = !empty($found['post_types'])
            || !empty($found['taxonomies'])
            || ($found['legacy_item_rows'] > 0 && !$migration_finished);

        return $found;
    }

    /**
     * Rows in the legacy `user_items` table that still describe legacy content.
     *
     * Returns 0 when the table is absent — which is the case on every install
     * that never ran the old plugin, since the current Sikshya does not create
     * it. Only rows whose `item_type` is one of the legacy post types count.
     *
     * This is a raw count, reported on the Tools fingerprint for support. It is
     * NOT on its own a statement that work is pending: migration copies these
     * rows rather than consuming them, so they persist after a successful run.
     * See {@see self::fingerprint()} for how the count is qualified.
     */
    private static function countUnmigratedLegacyItemRows(): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sikshya_user_items';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!is_string($exists) || $exists !== $table) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count(self::LEGACY_POST_TYPES), '%s'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix and a literal.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE item_type IN ({$placeholders})",
                self::LEGACY_POST_TYPES
            )
        );

        return max(0, (int) $count);
    }

    /**
     * Cheap boolean wrapper used by activation/`plugins_loaded` checks.
     */
    public static function hasLegacyData(): bool
    {
        $fp = self::fingerprint();
        return !empty($fp['has_legacy_data']);
    }
}
