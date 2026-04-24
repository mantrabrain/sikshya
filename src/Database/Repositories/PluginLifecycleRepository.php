<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Tables\Tables;

/**
 * Deactivation / uninstall maintenance (options table, custom table drops).
 *
 * @package Sikshya\Database\Repositories
 */
final class PluginLifecycleRepository
{
    /**
     * Remove Sikshya-related transients from wp_options.
     */
    public function deleteSikshyaTransients(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sikshya_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sikshya_%'");
    }

    /**
     * DROP all Free plugin custom tables (uses Table classes for names + prefix).
     */
    public function dropAllFreeCustomTables(): void
    {
        global $wpdb;
        foreach (Tables::all() as $tableClass) {
            $name = $tableClass::getTableName();
            $sql = esc_sql($name);
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifier from Table class only.
            $wpdb->query("DROP TABLE IF EXISTS `{$sql}`");
        }
    }
}
