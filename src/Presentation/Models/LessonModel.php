<?php

/**
 * Presentation model for a single lesson view. Populate from a lesson service; templates use getters only.
 * (Wiring to {@see \Sikshya\Frontend\Controllers\LessonController} is incremental.)
 *
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class LessonModel
{
    public function __construct(
        private int $id,
        private string $title,
        private string $contentHtml = '',
        private string $type = 'text',
        private string $videoUrl = '',
        private int $courseId = 0
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContentHtml(): string
    {
        return $this->contentHtml;
    }

    public function getLessonType(): string
    {
        return $this->type;
    }

    public function getVideoUrl(): string
    {
        return $this->videoUrl;
    }

    public function getCourseId(): int
    {
        return $this->courseId;
    }
}
