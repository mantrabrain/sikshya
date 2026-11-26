<?php

declare(strict_types=1);

namespace Sikshya\Frontend\Theme;

/**
 * Theme-overridable template resolution.
 *
 * Every front-end template and partial Sikshya ships is looked up through
 * {@see locate()} first, so a theme can replace any one of them by dropping a
 * file of the same relative name into a `sikshya/` folder:
 *
 *     yourtheme/sikshya/single-course.php
 *     yourtheme/sikshya/partials/course-card.php
 *     yourchildtheme/sikshya/archive-sik_course.php
 *
 * Child theme wins over parent, parent over the plugin. Nothing changes for a
 * theme that ships no override: the plugin copy is returned, byte for byte the
 * same file that was hard-coded before.
 *
 * The folder name is filterable (`sikshya_theme_template_dir`) so a theme that
 * already owns a `sikshya/` directory for something else can move it, and the
 * resolved path itself is filterable (`sikshya_locate_template`) so a plugin
 * can serve a template from anywhere on disk.
 *
 * @package Sikshya\Frontend\Theme
 */
final class TemplateLoader
{
    /**
     * Default folder a theme puts its overrides in, relative to the theme root.
     */
    public const DEFAULT_DIR = 'sikshya';

    /**
     * Resolved paths for this request, keyed by relative file.
     *
     * A page can ask for `partials/course-card.php` once per course in the
     * grid; resolution touches the filesystem twice per miss, so the answer is
     * remembered. Cleared by {@see flushCache()} (tests, and theme switches).
     *
     * @var array<string, string>
     */
    private static array $cache = [];

    /**
     * Absolute path to the plugin's `templates/` directory, with a trailing slash.
     */
    private static string $pluginDir = '';

    public static function setPluginTemplateDir(string $dir): void
    {
        self::$pluginDir = trailingslashit($dir);
        self::flushCache();
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }

    /**
     * Where the plugin's own templates live, with a trailing slash.
     *
     * Normally set during boot. The fallback derives it from this file's
     * location so a caller that runs before boot — or a unit test that loads
     * one class — still resolves to the shipped templates instead of to
     * nothing, which would make `locate()` return '' and silently drop the
     * plugin's front end.
     */
    private static function pluginDir(): string
    {
        if (self::$pluginDir !== '') {
            return self::$pluginDir;
        }

        // src/Frontend/Theme/ -> plugin root -> templates/
        $derived = dirname(__DIR__, 3) . '/templates/';
        if (is_dir($derived)) {
            self::$pluginDir = $derived;
        }

        return self::$pluginDir;
    }

    /**
     * Folder name a theme keeps its Sikshya overrides in.
     */
    public static function themeDirName(): string
    {
        $dir = apply_filters('sikshya_theme_template_dir', self::DEFAULT_DIR);
        $dir = is_string($dir) ? trim($dir, '/') : self::DEFAULT_DIR;

        // A filter returning '' or a traversal would make the theme root itself
        // the override dir, which would let an unrelated `single-course.php`
        // hijack the course template. Fall back instead.
        if ($dir === '' || strpos($dir, '..') !== false) {
            return self::DEFAULT_DIR;
        }

        return $dir;
    }

    /**
     * Theme candidates for a relative template, child theme first.
     *
     * @return string[] Absolute paths, in lookup order.
     */
    public static function themeCandidates(string $relative): array
    {
        $relative = self::normalize($relative);
        if ($relative === '') {
            return [];
        }

        $sub = self::themeDirName() . '/' . $relative;

        $candidates = [];
        if (function_exists('get_stylesheet_directory')) {
            $candidates[] = trailingslashit((string) get_stylesheet_directory()) . $sub;
        }
        if (function_exists('get_template_directory')) {
            $parent = trailingslashit((string) get_template_directory()) . $sub;
            if (!in_array($parent, $candidates, true)) {
                $candidates[] = $parent;
            }
        }

        return $candidates;
    }

    /**
     * Absolute path to the template to load for a relative name.
     *
     * Returns the plugin copy when the theme has no override, and '' only when
     * neither the theme nor the plugin has the file.
     *
     * @param string $relative e.g. 'single-course.php' or 'partials/course-card.php'.
     */
    public static function locate(string $relative): string
    {
        $relative = self::normalize($relative);
        if ($relative === '') {
            return '';
        }

        if (!isset(self::$cache[$relative])) {
            self::$cache[$relative] = self::resolve($relative);
        }

        /**
         * Final say on which file is loaded for a Sikshya template.
         *
         * @param string   $path       Absolute path, or '' when nothing was found.
         * @param string   $relative   Relative template name that was asked for.
         * @param string[] $candidates Theme paths that were checked, in order.
         */
        $filtered = apply_filters(
            'sikshya_locate_template',
            self::$cache[$relative],
            $relative,
            self::themeCandidates($relative)
        );

        return is_string($filtered) ? $filtered : self::$cache[$relative];
    }

    /**
     * Load a template part in an isolated scope.
     *
     * Unlike `require` from inside a template, nothing from the caller's scope
     * leaks in: the part sees exactly what $vars provides (plus the globals it
     * declares itself). Used by the block renderers, where there is no
     * surrounding template scope to inherit.
     *
     * @param array<string, mixed> $vars
     */
    public static function part(string $relative, array $vars = []): void
    {
        $path = self::locate($relative);
        if ($path === '' || !is_readable($path)) {
            return;
        }

        self::includeScoped($path, $vars);
    }

    /**
     * @param array<string, mixed> $vars
     */
    public static function render(string $relative, array $vars = []): string
    {
        ob_start();
        self::part($relative, $vars);

        return (string) ob_get_clean();
    }

    /**
     * Path currently being included.
     *
     * A property rather than a local, because an included file sees every
     * local in the including function's scope: with `$path` as a variable, a
     * part asking for its own `$path` variable got this class's file path
     * instead, and `extract(..., EXTR_SKIP)` would silently refuse to set it.
     * Nothing reads this property after the include begins, so a part that
     * loads another part cannot disturb it.
     */
    private static string $including = '';

    /**
     * Included in its own function so the part cannot see TemplateLoader's locals.
     *
     * @param array<string, mixed> $vars
     */
    private static function includeScoped(string $path, array $vars): void
    {
        self::$including = $path;

        // Both parameters leave the symbol table before the part can see them.
        unset($path);

        if ($vars !== []) {
            $extract = $vars;
            unset($vars);
            extract($extract, EXTR_SKIP);
            unset($extract);
        } else {
            unset($vars);
        }

        include self::$including;
    }

    private static function resolve(string $relative): string
    {
        foreach (self::themeCandidates($relative) as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        $plugin_dir = self::pluginDir();
        if ($plugin_dir !== '') {
            $plugin = $plugin_dir . $relative;
            if (is_readable($plugin)) {
                return $plugin;
            }

            // Return the plugin path even when unreadable: callers that
            // file_exists() it then fall through to the theme's own template,
            // which is the behaviour they had before this class existed.
            return $plugin;
        }

        return '';
    }

    /**
     * Reject anything that could escape the template directories.
     */
    private static function normalize(string $relative): string
    {
        $relative = str_replace('\\', '/', trim($relative));
        $relative = ltrim($relative, '/');

        /*
         * Callers predating this class pass paths relative to the plugin root
         * ('templates/partials/course-card.php'); templates pass them relative
         * to templates/ ('partials/course-card.php'). Both name the same file,
         * and a theme should only ever have to learn one of the two spellings.
         */
        if (strpos($relative, 'templates/') === 0) {
            $relative = substr($relative, strlen('templates/'));
        }

        if ($relative === '' || strpos($relative, '..') !== false || strpos($relative, "\0") !== false) {
            return '';
        }

        // Only ever templates. A theme dropping a .php.jpg or a .html in the
        // override folder must not become an include target.
        if (substr($relative, -4) !== '.php') {
            return '';
        }

        return $relative;
    }
}
