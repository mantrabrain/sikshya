<?php

declare(strict_types=1);

namespace Sikshya\Import;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The question types an import can carry faithfully, and nothing else.
 *
 * Deliberately short. Every type on this list can be written in the exact
 * encoding {@see \Sikshya\Services\QuizGrader} reads, so an imported question
 * grades the way it did in the old plugin. Types that cannot are skipped and
 * named in the report.
 *
 * `ordering` and `matching` are the interesting omissions. Sikshya supports
 * both, and a source usually stores enough to rebuild them — but it stores the
 * options *in their correct order*, and importing them that way hands the
 * learner the answer unless the player happens to shuffle. A question that is
 * trivially correct is worse than one the report told you to rebuild.
 *
 * @package Sikshya\Import
 */
final class QuestionTypes
{
    public const TRUE_FALSE = 'true_false';
    public const SINGLE = 'multiple_choice';
    public const MULTIPLE = 'multiple_response';
    public const SHORT_ANSWER = 'short_answer';
    public const FILL_BLANK = 'fill_blank';
    public const ESSAY = 'essay';

    /** @return array<int, string> */
    public static function supported(): array
    {
        return [
            self::TRUE_FALSE,
            self::SINGLE,
            self::MULTIPLE,
            self::SHORT_ANSWER,
            self::FILL_BLANK,
            self::ESSAY,
        ];
    }

    public static function isSupported(string $type): bool
    {
        return in_array($type, self::supported(), true);
    }

    /** Types whose answer key is a list of accepted strings rather than options. */
    public static function isTextAnswer(string $type): bool
    {
        return $type === self::SHORT_ANSWER || $type === self::FILL_BLANK;
    }
}
