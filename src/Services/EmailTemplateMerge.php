<?php

namespace Sikshya\Services;

/**
 * Replace {{merge_tags}} in email subject/body.
 *
 * @package Sikshya\Services
 */
final class EmailTemplateMerge
{
    /**
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
}
