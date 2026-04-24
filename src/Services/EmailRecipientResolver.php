<?php

namespace Sikshya\Services;

/**
 * Resolves "Send to" expressions (merge tags or legacy learner/admin/instructor) to a single email address.
 *
 * @package Sikshya\Services
 */
final class EmailRecipientResolver
{
    /**
     * @param array<string, string> $merge_ctx Keys like {{student_email}}.
     */
    public static function resolve(string $expression, array $merge_ctx): string
    {
        $expression = trim($expression);
        if ($expression === '') {
            $expression = '{{student_email}}';
        }

        $lower = strtolower($expression);
        if ($lower === 'learner' || $lower === 'student') {
            $expression = '{{student_email}}';
        } elseif ($lower === 'admin') {
            $expression = '{{admin_email}}';
        } elseif ($lower === 'instructor') {
            $expression = '{{instructor_email}}';
        }

        $out = EmailTemplateMerge::apply($expression, $merge_ctx);
        $out = trim($out);

        return $out !== '' && is_email($out) ? $out : '';
    }
}
