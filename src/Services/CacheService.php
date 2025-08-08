<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;

/**
 * Cache Management Service
 *
 * @package Sikshya\Services
 */
class CacheService
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Get cached data
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key)
    {
        return get_transient('sikshya_cache_' . $key);
    }

    /**
     * Set cached data
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return bool
     */
    public function set(string $key, $value, int $expiration = 3600): bool
    {
        return set_transient('sikshya_cache_' . $key, $value, $expiration);
    }

    /**
     * Delete cached data
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        return delete_transient('sikshya_cache_' . $key);
    }

    /**
     * Clear all cache
     */
    public function clear(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sikshya_cache_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sikshya_cache_%'");
    }
} 