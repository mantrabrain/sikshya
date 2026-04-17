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

        return get_option(self::PREFIX . $key, $default);
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

