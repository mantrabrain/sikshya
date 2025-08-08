<?php

namespace Sikshya\Core;

/**
 * Plugin Requirements Checker
 *
 * @package Sikshya\Core
 */
class Requirements
{
    /**
     * Check if all requirements are met
     */
    public static function check(): bool
    {
        return self::checkWordPressVersion() 
            && self::checkPHPVersion() 
            && self::checkExtensions();
    }

    /**
     * Check WordPress version
     */
    public static function checkWordPressVersion(): bool
    {
        return version_compare(get_bloginfo('version'), SIKSHYA_MINIMUM_WP_VERSION, '>=');
    }

    /**
     * Check PHP version
     */
    public static function checkPHPVersion(): bool
    {
        return version_compare(PHP_VERSION, SIKSHYA_MINIMUM_PHP_VERSION, '>=');
    }

    /**
     * Check required PHP extensions
     */
    public static function checkExtensions(): bool
    {
        $required_extensions = [
            'json',
            'mbstring',
            'xml',
            'curl',
        ];

        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get requirements error message
     */
    public static function getErrorMessage(): string
    {
        $errors = [];

        if (!self::checkWordPressVersion()) {
            $errors[] = sprintf(
                __('WordPress %s or higher is required. Current version: %s', 'sikshya'),
                SIKSHYA_MINIMUM_WP_VERSION,
                get_bloginfo('version')
            );
        }

        if (!self::checkPHPVersion()) {
            $errors[] = sprintf(
                __('PHP %s or higher is required. Current version: %s', 'sikshya'),
                SIKSHYA_MINIMUM_PHP_VERSION,
                PHP_VERSION
            );
        }

        if (!self::checkExtensions()) {
            $errors[] = __('Required PHP extensions are missing: json, mbstring, xml, curl', 'sikshya');
        }

        return implode('<br>', $errors);
    }

    /**
     * Display admin notice for requirements
     */
    public static function displayAdminNotice(): void
    {
        if (!self::check()) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('Sikshya LMS Error:', 'sikshya'); ?></strong>
                    <?php echo self::getErrorMessage(); ?>
                </p>
            </div>
            <?php
        }
    }
} 