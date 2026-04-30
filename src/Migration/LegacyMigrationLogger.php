<?php

/**
 * Append-only structured logger for the legacy migration runner.
 *
 * Writes lines to `wp-content/uploads/sikshya-logs/legacy-migration-<ts>.log`
 * (and to `error_log()` when `WP_DEBUG_LOG` is on). The log directory is
 * created on first write, with `.htaccess` (deny from all) and `index.html`
 * stub files alongside, mirroring the convention used elsewhere in the
 * plugin.
 *
 * @package Sikshya\Migration
 */

namespace Sikshya\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class LegacyMigrationLogger
{
    private string $logFilePath;

    public function __construct(?string $logFilePath = null)
    {
        if ($logFilePath !== null && $logFilePath !== '') {
            $this->logFilePath = $logFilePath;
        } else {
            $this->logFilePath = self::resolveLogFilePath();
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * Path to the active log file (visible to the admin UI for download).
     */
    public function logFilePath(): string
    {
        return $this->logFilePath;
    }

    private function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            '[%s] [%s] %s',
            current_time('mysql', true),
            strtoupper($level),
            $message
        );

        if (!empty($context)) {
            $line .= ' ' . wp_json_encode($context);
        }

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Sikshya Legacy Migration: ' . $line);
        }

        $dir = dirname($this->logFilePath);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
            $this->createSecurityFiles($dir);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
        $handle = @fopen($this->logFilePath, 'a');
        if (!$handle) {
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
        fwrite($handle, $line . PHP_EOL);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
        fclose($handle);
    }

    private function createSecurityFiles(string $dir): void
    {
        $files = [
            'index.html' => '',
            '.htaccess' => 'deny from all',
        ];

        foreach ($files as $name => $content) {
            $path = trailingslashit($dir) . $name;
            if (file_exists($path)) {
                continue;
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents
            @file_put_contents($path, $content);
        }
    }

    private static function resolveLogFilePath(): string
    {
        $upload_dir = wp_upload_dir();
        $base = isset($upload_dir['basedir'])
            ? trailingslashit((string) $upload_dir['basedir']) . 'sikshya-logs/'
            : trailingslashit(WP_CONTENT_DIR) . 'uploads/sikshya-logs/';

        $stamp = current_time('Y-m-d', true);

        return $base . 'legacy-migration-' . $stamp . '.log';
    }
}
