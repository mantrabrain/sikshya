<?php

namespace Sikshya\Utils;

/**
 * Central rich text sanitization + rendering helpers.
 *
 * Use this for any "description" or WYSIWYG-like HTML coming from admin UIs (Quill, etc).
 *
 * @package Sikshya\Utils
 */
final class RichText
{
    /**
     * Sanitize rich HTML for safe storage.
     */
    public static function sanitize(?string $html): string
    {
        $html = is_string($html) ? $html : '';
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Store as safe HTML.
        return wp_kses_post($html);
    }

    /**
     * Render rich HTML safely (sanitized + formatted).
     *
     * Note: This intentionally keeps formatting predictable (paragraphs/line breaks).
     */
    public static function render(?string $html): string
    {
        $safe = self::sanitize($html);
        if ($safe === '') {
            return '';
        }

        return wp_kses_post(wpautop($safe));
    }
}

