<?php

namespace Sikshya\Core;

/**
 * Plugin Deactivator
 *
 * @package Sikshya\Core
 */
class Deactivator
{
    /**
     * Deactivate the plugin
     */
    public static function deactivate(): void
    {
        // Clear scheduled events
        self::clearScheduledEvents();

        // Clear cache
        self::clearCache();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set deactivation time
        update_option('sikshya_deactivation_time', current_time('timestamp'));

        // Trigger deactivation hook
        do_action('sikshya_deactivated');
    }

    /**
     * Clear scheduled events
     */
    private static function clearScheduledEvents(): void
    {
        $events = [
            'sikshya_cleanup_expired_certificates',
            'sikshya_send_reminder_emails',
            'sikshya_update_course_statistics',
            'sikshya_backup_progress_data',
        ];

        foreach ($events as $event) {
            wp_clear_scheduled_hook($event);
        }
    }

    /**
     * Clear cache
     */
    private static function clearCache(): void
    {
        // Clear WordPress cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear object cache
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('sikshya');
        }

        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sikshya_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sikshya_%'");
    }
} 