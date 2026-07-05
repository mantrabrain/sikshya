<?php

namespace Sikshya\Services;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Replace {{merge_tags}} in email subject/body.
 *
 * @package Sikshya\Services
 */
final class EmailTemplateMerge
{
    /**
     * Substitute merge tags without any escaping. Use for plain-text contexts
     * (subject lines) where HTML entities would render literally as
     * `&amp;` instead of `&`. The values themselves should be plain text
     * before calling — caller is responsible for that.
     *
     * @param array<string, string> $replacements Keys must include braces, e.g. '{{site_name}}'.
     */
    public static function apply(string $content, array $replacements): string
    {
        if ($content === '' || $replacements === []) {
            return $content;
        }

        $keys = array_keys($replacements);
        $vals = array_values($replacements);

        return str_replace($keys, $vals, $content);
    }

    /**
     * Substitute merge tags into an HTML body, escaping each value with
     * `esc_html()` first so user-controlled inputs (learner display name,
     * course title, instructor name, etc.) can't smuggle raw markup into
     * the rendered email.
     *
     * Why this matters: the template body is sanitised at save-time via
     * `wp_kses_post()`, but that sanitisation runs on the AUTHOR's static
     * markup — not on the merge values that get spliced in at send-time.
     * Without per-value escaping, a learner whose `display_name` is
     * `<img src=x onerror=alert(1)>` (or a tracker pixel, or a misleading
     * phishing link) gets that markup rendered into every email that
     * references `{{learner_name}}`. Most modern email clients strip script
     * execution, but the markup itself still renders — formatting breaks,
     * read-receipt trackers fire, and phishing-style links display.
     *
     * @param array<string, string> $replacements Keys must include braces, e.g. '{{learner_name}}'.
     */
    public static function applyHtml(string $content, array $replacements): string
    {
        if ($content === '' || $replacements === []) {
            return $content;
        }

        $keys = array_keys($replacements);
        $vals = array_map(static function ($v): string {
            return esc_html((string) $v);
        }, array_values($replacements));

        return str_replace($keys, $vals, $content);
    }
}
