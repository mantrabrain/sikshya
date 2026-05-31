<?php

declare(strict_types=1);

namespace Sikshya\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Single source of truth for grading quiz answers across **every** question type.
 *
 * Historically, near-identical `evaluateAnswer()` + `normalizeScalarAnswer()` implementations
 * lived on both {@see \Sikshya\Api\Learner\QuizRoutes} and {@see \Sikshya\Services\QuizService}.
 * The two drifted (QuizService only handled 4 of the 8 supported question types; the scalar
 * normaliser differed on numerics). This class is the consolidated version — both call sites
 * delegate here so future question types only need to be added once.
 *
 * **Supported types** (string identifier → expected-answer encoding):
 *   - `essay` — always returns `correct: false`; manual grading happens elsewhere.
 *   - `multiple_choice` / `true_false` — `correct` is the option index as a string.
 *   - `short_answer` / `fill_blank` — `correct` is a pipe-separated list of accepted strings
 *      (case-insensitive). Empty submission is never correct.
 *   - `multiple_response` — `correct` is a JSON-encoded array of selected indices; order is
 *      ignored, duplicates collapsed.
 *   - `ordering` — `correct` is a JSON-encoded array of indices in canonical order; the
 *      submitted order is compared positionally.
 *   - `matching` — `correct` is a JSON-encoded `{matching:{map: [right-index per left-row]}}`;
 *      the submitted `map` array must equal it element-wise.
 *
 * @package Sikshya\Services
 */
final class QuizGrader
{
    /**
     * Grade a single answer.
     *
     * @param string $type    Question type (see class docblock for supported values).
     * @param string $correct Stored correct-answer encoding (see class docblock).
     * @param mixed  $answer  Learner's submitted answer (any JSON-decodable shape).
     * @return array{correct: bool}
     */
    public static function evaluate(string $type, string $correct, $answer): array
    {
        $c = trim($correct);

        if ($type === 'essay') {
            return ['correct' => false];
        }

        if ($type === 'multiple_choice' || $type === 'true_false') {
            $a = self::normalizeScalarAnswer($answer);

            return ['correct' => strcasecmp($a, $c) === 0];
        }

        if ($type === 'short_answer' || $type === 'fill_blank') {
            $u = is_string($answer) ? trim($answer) : '';
            if ($u === '') {
                return ['correct' => false];
            }
            $opts = array_map('trim', explode('|', $c));
            $ul = strtolower($u);
            foreach ($opts as $o) {
                if ($o !== '' && strtolower($o) === $ul) {
                    return ['correct' => true];
                }
            }

            return ['correct' => false];
        }

        if ($type === 'multiple_response') {
            $exp = json_decode($c, true);
            if (!is_array($exp)) {
                return ['correct' => false];
            }
            $got = is_string($answer) ? json_decode($answer, true) : $answer;
            if (!is_array($got)) {
                return ['correct' => false];
            }
            $e = array_map('intval', $exp);
            $g = array_map('intval', $got);
            sort($e);
            sort($g);

            return ['correct' => $e === $g];
        }

        if ($type === 'ordering') {
            $exp = json_decode($c, true);
            if (!is_array($exp)) {
                return ['correct' => false];
            }
            $got = is_string($answer) ? json_decode($answer, true) : $answer;
            if (!is_array($got)) {
                return ['correct' => false];
            }
            $e = array_map('intval', $exp);
            $g = array_map('intval', $got);

            return ['correct' => $e === $g];
        }

        if ($type === 'matching') {
            $dec = json_decode($c, true);
            if (!is_array($dec) || empty($dec['matching']) || !is_array($dec['matching'])) {
                return ['correct' => false];
            }
            $exp_map = $dec['matching']['map'] ?? null;
            if (!is_array($exp_map)) {
                return ['correct' => false];
            }
            $exp_map = array_map('intval', $exp_map);
            $got = is_string($answer) ? json_decode($answer, true) : $answer;
            if (!is_array($got) || empty($got['map']) || !is_array($got['map'])) {
                return ['correct' => false];
            }
            $gmap = array_map('intval', $got['map']);

            return ['correct' => $exp_map === $gmap];
        }

        return ['correct' => false];
    }

    /**
     * Normalise a learner answer for the choice / true-false grader path. JSON transports may
     * deliver an option index as a string, int, or bool — coerce all three to a comparable
     * string. Returns an empty string for any other shape.
     *
     * @param mixed $answer
     */
    public static function normalizeScalarAnswer($answer): string
    {
        if (is_string($answer)) {
            return trim($answer);
        }
        if (is_bool($answer)) {
            return $answer ? 'true' : 'false';
        }
        if (is_int($answer) || is_float($answer)) {
            return (string) (int) $answer;
        }

        return '';
    }
}
