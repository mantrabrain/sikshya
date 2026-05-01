<?php

/**
 * Presentation model for a quiz view. Build from a quiz service; do not query in templates.
 *
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class QuizModel
{
    private int $id;

    private string $title;

    /** @var list<QuestionModel> */
    private array $questions;

    private float $passingScore;

    private int $timeLimitMinutes;

    /**
     * @param list<QuestionModel> $questions
     */
    public function __construct(
        int $id,
        string $title,
        array $questions = [],
        float $passingScore = 0.0,
        int $timeLimitMinutes = 0
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->questions = $questions;
        $this->passingScore = $passingScore;
        $this->timeLimitMinutes = $timeLimitMinutes;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return list<QuestionModel>
     */
    public function getQuestions(): array
    {
        return $this->questions;
    }

    public function getPassingScore(): float
    {
        return $this->passingScore;
    }

    public function getTimeLimitMinutes(): int
    {
        return $this->timeLimitMinutes;
    }
}
