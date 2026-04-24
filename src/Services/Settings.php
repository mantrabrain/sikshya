<?php

namespace Sikshya\Services;

/**
 * Centralized settings read/write helper.
 *
 * Sikshya settings are stored in WordPress options with the `_sikshya_` prefix.
 *
 * @package Sikshya\Services
 */
final class Settings
{
    public const PREFIX = '_sikshya_';

    /**
     * Get a Sikshya setting (prefixed option).
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = '')
    {
        $key = sanitize_key($key);

        $value = get_option(self::PREFIX . $key, $default);

        // Avoid runaway recursion: some filters may call back into Settings::get().
        static $inFilter = 0;
        if ($inFilter > 0) {
            return $value;
        }

        // Never filter the addon enablement option; callers often check addons inside filters.
        if ($key === 'addons_enabled') {
            return $value;
        }

        /**
         * Filter a Sikshya setting value after load.
         *
         * Allows add-ons (e.g. multilingual) to translate user-configurable labels.
         *
         * @param mixed  $value Loaded option value (may be non-string).
         * @param string $key   Unprefixed Sikshya setting key (sanitized).
         */
        $inFilter++;
        try {
            return apply_filters('sikshya_setting_value', $value, $key);
        } finally {
            $inFilter--;
        }
    }

    /**
     * Set a Sikshya setting (prefixed option).
     *
     * @param mixed $value
     */
    public static function set(string $key, $value): bool
    {
        $key = sanitize_key($key);

        return (bool) update_option(self::PREFIX . $key, $value);
    }

    /**
     * Get a raw WordPress option (no prefix).
     *
     * @param mixed $default
     * @return mixed
     */
    public static function getRaw(string $option, $default = '')
    {
        $option = sanitize_key($option);

        return get_option($option, $default);
    }

    /**
     * Set a raw WordPress option (no prefix).
     *
     * @param mixed $value
     */
    public static function setRaw(string $option, $value, ?bool $autoload = null): bool
    {
        $option = sanitize_key($option);

        return $autoload === null
            ? (bool) update_option($option, $value)
            : (bool) update_option($option, $value, $autoload);
    }

    /**
     * @param mixed $v
     */
    public static function isTruthy($v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
    }
}

