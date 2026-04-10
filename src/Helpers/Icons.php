<?php

namespace Sikshya\Helpers;

/**
 * Shared SVG icon paths from {@see assets/admin/icons/icons.json} for PHP templates.
 */
final class Icons
{
    /**
     * @var array<string, array{viewBox?: string, paths: array<int, string>}>|null
     */
    private static ?array $cache = null;

    /**
     * Absolute path to icons.json.
     */
    public static function jsonPath(): string
    {
        return SIKSHYA_PLUGIN_DIR . 'assets/admin/icons/icons.json';
    }

    /**
     * @return array<string, array{viewBox?: string, paths: array<int, string>}>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $raw = @file_get_contents(self::jsonPath());
        if ($raw === false) {
            self::$cache = [];

            return self::$cache;
        }

        $decoded = json_decode($raw, true);
        self::$cache = is_array($decoded) ? $decoded : [];

        return self::$cache;
    }

    /**
     * Inline SVG markup for a named icon (stroke, currentColor).
     *
     * @param string $key   Key in icons.json.
     * @param string $class Optional CSS class on the root SVG.
     */
    public static function inline(string $key, string $class = 'sikshya-icon'): string
    {
        $all = self::all();
        if (!isset($all[$key]['paths']) || !is_array($all[$key]['paths'])) {
            return '';
        }

        $def = $all[$key];
        $view_box = isset($def['viewBox']) && is_string($def['viewBox']) ? $def['viewBox'] : '0 0 24 24';
        $paths = '';
        foreach ($def['paths'] as $d) {
            if (!is_string($d) || $d === '') {
                continue;
            }
            $paths .= '<path stroke-linecap="round" stroke-linejoin="round" d="' . esc_attr($d) . '"/>';
        }

        if ($paths === '') {
            return '';
        }

        return sprintf(
            '<svg class="%s" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="%s" stroke="currentColor" stroke-width="1.75" aria-hidden="true">%s</svg>',
            esc_attr($class),
            esc_attr($view_box),
            $paths
        );
    }
}
