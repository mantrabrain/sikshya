<?php
/**
 * Reusable admin markup helpers (Sikshya design system).
 *
 * @package Sikshya\Admin
 */

namespace Sikshya\Admin;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Loads partials under templates/admin/partials/ and small HTML builders for list headers.
 */
final class AdminTemplate
{
    /**
     * Render a partial template with extracted variables.
     *
     * @param string $name Partial filename without .php.
     * @param array  $args Variables to extract in the partial.
     */
    public static function partial(string $name, array $args = []): void
    {
        $file = SIKSHYA_PLUGIN_DIR . 'templates/admin/partials/' . $name . '.php';
        if (!is_readable($file)) {
            echo '<!-- Sikshya: missing admin partial ' . esc_html($name) . ' -->';
            return;
        }

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional scoped template variables.
        extract($args, EXTR_SKIP);
        include $file;
    }

    /**
     * Inline SVG for page and card headers (stroke icons, currentColor).
     *
     * @param string $key  Icon key.
     * @param int    $size Width/height in px.
     */
    public static function icon(string $key, int $size = 22): string
    {
        $stroke = ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
        $base = ' width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none"' . $stroke;

        switch ($key) {
            case 'course':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>';

            case 'lesson':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>';

            case 'quiz':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/></svg>';

            case 'students':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>';

            case 'instructors':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>';

            case 'card-course':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>';

            case 'card-lesson':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';

            case 'card-quiz':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';

            case 'card-students':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>';

            case 'card-instructors':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>';

            case 'plus':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M12 4v16m8-8H4"/></svg>';

            case 'arrow-left':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>';

            case 'content-type':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg>';

            case 'dashboard':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>';

            case 'reports':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 5-6"/></svg>';

            case 'settings':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>';

            case 'tools':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>';

            case 'help':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/></svg>';

            case 'categories':
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>';

            default:
                return '<svg' . $base . ' aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/></svg>';
        }
    }

    /**
     * Primary action anchor with plus icon (escaped label).
     */
    public static function primary_link(string $href, string $label): string
    {
        return sprintf(
            '<a href="%1$s" class="sikshya-btn sikshya-btn-primary">%2$s%3$s</a>',
            esc_url($href),
            self::icon('plus', 16),
            esc_html($label)
        );
    }

    /**
     * Secondary action anchor (escaped label).
     */
    public static function secondary_link(string $href, string $label, string $icon_key = 'arrow-left'): string
    {
        return sprintf(
            '<a href="%1$s" class="sikshya-btn sikshya-btn-secondary">%2$s%3$s</a>',
            esc_url($href),
            self::icon($icon_key, 16),
            esc_html($label)
        );
    }

    /**
     * Primary &lt;button&gt; with plus icon (for JS-driven actions).
     */
    public static function primary_button(string $label, string $extra_classes = ''): string
    {
        $classes = trim('sikshya-btn sikshya-btn-primary ' . $extra_classes);

        return sprintf(
            '<button type="button" class="%1$s">%2$s%3$s</button>',
            esc_attr($classes),
            self::icon('plus', 16),
            esc_html($label)
        );
    }
}
