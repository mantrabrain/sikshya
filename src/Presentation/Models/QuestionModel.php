<?php

/**
 * Presentation model for a quiz question. Parent: {@see QuizModel}.
 *
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class QuestionModel
{
    private int $id;

    private string $title;

    private string $type;

    /** @var list<string> */
    private array $options;

    private int $points;

    /**
     * @param list<string> $options
     */
    public function __construct(
        int $id,
        string $title,
        string $type = 'multiple_choice',
        array $options = [],
        int $points = 1
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->type = $type;
        $this->options = $options;
        $this->points = $points;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getQuestionType(): string
    {
        return $this->type;
    }

    /**
     * @return list<string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getPoints(): int
    {
        return $this->points;
    }
}
