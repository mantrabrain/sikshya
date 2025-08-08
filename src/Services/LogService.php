<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;

/**
 * Logging Service
 *
 * @package Sikshya\Services
 */
class LogService
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
     * Log message
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!get_option('sikshya_enable_logging', 'yes')) {
            return;
        }

        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'sikshya_logs',
            [
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context),
                'created_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message
     * @param array $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array $context
     */
    public function debug(string $message, array $context = []): void
    {
        if ($this->plugin->isDevelopment()) {
            $this->log('debug', $message, $context);
        }
    }
} 