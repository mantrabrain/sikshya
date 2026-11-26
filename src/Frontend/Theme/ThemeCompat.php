<?php

declare(strict_types=1);

namespace Sikshya\Frontend\Theme;

/**
 * Makes Sikshya's front end sit inside the active theme rather than beside it.
 *
 * Two problems this solves:
 *
 * 1. Width. Sikshya's templates used a fixed container, so on a theme whose
 *    content width is 1280px the course archive stopped at the plugin's own
 *    figure and looked inset; on a narrow theme it overflowed. Block themes
 *    publish their widths in theme.json (`layout.contentSize` / `wideSize`),
 *    and classic themes publish `$content_width`. Both are read here and
 *    exposed as CSS custom properties the stylesheets consume.
 *
 * 2. Colour and type. The plugin's design tokens are brand constants. On a
 *    theme with its own palette the course cards were the only blue things on
 *    the page. A theme can now opt into having Sikshya follow it:
 *
 *        add_theme_support( 'sikshya', [ 'palette' => true ] );
 *
 *    which binds the Sikshya brand tokens to the theme's `primary` /
 *    `secondary` / `accent` palette slots (theme.json presets, or classic
 *    `editor-color-palette`), and leaves them alone when those slots are
 *    absent. Nothing is inherited unless the theme asks, so no existing site
 *    changes appearance on upgrade.
 *
 * Everything emitted is a custom property on :root. No selector the theme owns
 * is touched, and no stylesheet is dequeued.
 *
 * @package Sikshya\Frontend\Theme
 */
final class ThemeCompat
{
    private const HANDLE = 'sikshya-theme-compat';

    private static bool $booted = false;

    public static function init(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        add_action('wp_enqueue_scripts', [self::class, 'emitVariables'], 11);
        add_action('enqueue_block_assets', [self::class, 'emitVariablesInEditor'], 11);
        add_action('switch_theme', [TemplateLoader::class, 'flushCache']);
    }

    /**
     * What the active theme declared via add_theme_support('sikshya', [...]).
     *
     * @return array{supported: bool, palette: bool, typography: bool, layout: bool}
     */
    public static function themeSupport(): array
    {
        $defaults = [
            'supported' => false,
            'palette' => false,
            'typography' => false,
            // Layout is the one piece that applies with or without opt-in: a
            // container that matches the theme's width is never a regression,
            // and the properties have the plugin's own figures as fallbacks.
            'layout' => true,
        ];

        if (!function_exists('current_theme_supports') || !current_theme_supports('sikshya')) {
            return $defaults;
        }

        $defaults['supported'] = true;

        $args = function_exists('get_theme_support') ? get_theme_support('sikshya') : false;
        $config = (is_array($args) && isset($args[0]) && is_array($args[0])) ? $args[0] : [];

        foreach (['palette', 'typography', 'layout'] as $key) {
            if (array_key_exists($key, $config)) {
                $defaults[$key] = (bool) $config[$key];
            }
        }

        return $defaults;
    }

    /**
     * Content widths for the active theme, in CSS length units.
     *
     * @return array{content: string, wide: string}
     */
    public static function layoutWidths(): array
    {
        $content = '';
        $wide = '';

        if (function_exists('wp_get_global_settings')) {
            $layout = wp_get_global_settings(['layout']);
            if (is_array($layout)) {
                $content = self::cssLength($layout['contentSize'] ?? '');
                $wide = self::cssLength($layout['wideSize'] ?? '');
            }
        }

        if ($content === '') {
            // Classic themes: $content_width is the only declaration there is.
            $global = isset($GLOBALS['content_width']) ? $GLOBALS['content_width'] : 0;
            if (is_numeric($global) && (float) $global > 0) {
                $content = (string) (int) $global . 'px';
            }
        }

        if ($wide === '' && $content !== '') {
            $wide = $content;
        }

        return [
            'content' => $content,
            'wide' => $wide,
        ];
    }

    /**
     * The CSS Sikshya adds for the active theme. '' when there is nothing to say.
     */
    public static function variablesCss(): string
    {
        $support = self::themeSupport();
        $declarations = [];

        if ($support['layout']) {
            $widths = self::layoutWidths();
            if ($widths['content'] !== '') {
                $declarations[] = '--sikshya-theme-content-size:' . $widths['content'];
            }
            if ($widths['wide'] !== '') {
                $declarations[] = '--sikshya-theme-wide-size:' . $widths['wide'];
            }
        }

        if ($support['palette']) {
            foreach (self::paletteBridge() as $property => $value) {
                $declarations[] = $property . ':' . $value;
            }
        }

        if ($support['typography']) {
            $declarations[] = '--sikshya-font-family:inherit';
            $declarations[] = '--sikshya-heading-font-family:inherit';
        }

        /**
         * Custom properties Sikshya sets for the active theme.
         *
         * @param string[] $declarations Each entry is a full `--prop:value` pair.
         * @param array    $support      Resolved theme support flags.
         */
        $declarations = apply_filters('sikshya_theme_compat_declarations', $declarations, $support);
        $declarations = array_values(array_filter(array_map('strval', (array) $declarations)));

        if ($declarations === []) {
            return '';
        }

        return ':root{' . implode(';', $declarations) . '}';
    }

    public static function emitVariables(): void
    {
        $css = self::variablesCss();
        if ($css === '') {
            return;
        }

        // Attached to a registered-but-empty handle so it lands after the
        // plugin's own stylesheets in the <head>, whichever of them loaded.
        if (!wp_style_is(self::HANDLE, 'registered')) {
            wp_register_style(self::HANDLE, false, [], SIKSHYA_VERSION);
        }
        wp_enqueue_style(self::HANDLE);
        wp_add_inline_style(self::HANDLE, $css);
    }

    public static function emitVariablesInEditor(): void
    {
        if (!is_admin()) {
            return;
        }

        self::emitVariables();
    }

    /**
     * Sikshya brand tokens bound to the theme's palette, where the theme has one.
     *
     * @return array<string, string>
     */
    private static function paletteBridge(): array
    {
        $slots = self::themePaletteColors();
        $map = [
            'primary' => ['--sikshya-primary'],
            'secondary' => ['--sikshya-secondary'],
            'accent' => ['--sikshya-accent'],
        ];

        $out = [];
        foreach ($map as $slot => $properties) {
            if (!isset($slots[$slot]) || $slots[$slot] === '') {
                continue;
            }
            foreach ($properties as $property) {
                $out[$property] = $slots[$slot];
            }
        }

        return $out;
    }

    /**
     * Palette slugs → colour values for the active theme.
     *
     * @return array<string, string>
     */
    private static function themePaletteColors(): array
    {
        $palette = [];

        if (function_exists('wp_get_global_settings')) {
            $theme = wp_get_global_settings(['color', 'palette', 'theme']);
            if (is_array($theme)) {
                $palette = array_merge($palette, self::indexPalette($theme));
            }
            $custom = wp_get_global_settings(['color', 'palette', 'custom']);
            if (is_array($custom)) {
                // Site-level (user) choices override the theme's own.
                $palette = array_merge($palette, self::indexPalette($custom));
            }
        }

        if ($palette === [] && function_exists('get_theme_support')) {
            $classic = get_theme_support('editor-color-palette');
            if (is_array($classic) && isset($classic[0]) && is_array($classic[0])) {
                $palette = self::indexPalette($classic[0]);
            }
        }

        return $palette;
    }

    /**
     * @param array<int, mixed> $entries
     * @return array<string, string>
     */
    private static function indexPalette(array $entries): array
    {
        $out = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $slug = isset($entry['slug']) ? sanitize_key((string) $entry['slug']) : '';
            $color = isset($entry['color']) ? trim((string) $entry['color']) : '';
            if ($slug === '' || $color === '') {
                continue;
            }
            // Only literal colour values; a palette entry holding a var() or a
            // gradient string would be pasted straight into our declaration.
            if (!self::isSafeColor($color)) {
                continue;
            }
            $out[$slug] = $color;
        }

        return $out;
    }

    private static function isSafeColor(string $value): bool
    {
        if (preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value) === 1) {
            return true;
        }

        return preg_match('/^(?:rgb|hsl)a?\(\s*[0-9a-zA-Z.,%\/\s+-]{1,64}\)$/', $value) === 1;
    }

    /**
     * @param mixed $value
     */
    private static function cssLength($value): string
    {
        if (is_numeric($value)) {
            return (float) $value > 0 ? (string) (int) $value . 'px' : '';
        }

        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // theme.json widths are plain lengths or clamp()/min()/max() expressions.
        if (preg_match('/^[0-9a-zA-Z.,%()\s*\/+-]{1,120}$/', $value) !== 1) {
            return '';
        }

        return $value;
    }
}
