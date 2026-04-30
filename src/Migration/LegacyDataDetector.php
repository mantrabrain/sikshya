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
     * `wp_options` keys whose presence is treated as a positive fingerprint.
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
     *     tables: string[]
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

        $found['has_legacy_data'] = !empty($found['options'])
            || !empty($found['post_types'])
            || !empty($found['taxonomies'])
            || !empty($found['tables']);

        return $found;
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
