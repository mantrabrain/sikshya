<?php

namespace Sikshya\Services;

/**
 * Collects environment/system info for admin tools.
 */
final class SystemInfoService
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        global $wpdb;

        $theme = wp_get_theme();
        $plugins = (array) \Sikshya\Services\Settings::getRaw('active_plugins', []);

        return [
            'site_url' => home_url('/'),
            'is_multisite' => is_multisite(),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'sikshya_version' => defined('SIKSHYA_VERSION') ? SIKSHYA_VERSION : '',
            'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : '',
            'memory_limit' => ini_get('memory_limit'),
            'wp_memory_limit' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '',
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'theme_name' => $theme->get('Name'),
            'theme_stylesheet' => get_stylesheet(),
            'active_plugins_count' => count($plugins),
        ];
    }
}

