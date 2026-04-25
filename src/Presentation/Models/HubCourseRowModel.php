<?php

/**
 * One “continue learning” or bundle card row on the learn hub / bundle view.
 *
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class HubCourseRowModel
{
    private function __construct(
        private ?\WP_Post $course,
        private int $progress,
        private string $continueUrl,
        private string $courseUrl,
        private string $thumb,
        private bool $enrolled
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromServiceRow(array $row): self
    {
        $c = $row['course'] ?? null;

        return new self(
            $c instanceof \WP_Post ? $c : null,
            (int) max(0, min(100, (int) ($row['progress'] ?? 0))),
            (string) ($row['continue_url'] ?? ''),
            (string) ($row['course_url'] ?? ''),
            (string) ($row['thumb'] ?? ''),
            (bool) ($row['enrolled'] ?? true)
        );
    }

    public function getCoursePost(): ?\WP_Post
    {
        return $this->course;
    }

    public function getTitle(): string
    {
        if (!$this->course instanceof \WP_Post) {
            return '';
        }

        return (string) get_the_title($this->course);
    }

    public function getProgressPercent(): int
    {
        return $this->progress;
    }

    public function getContinueUrl(): string
    {
        return $this->continueUrl;
    }

    public function getViewCourseUrl(): string
    {
        return $this->courseUrl;
    }

    public function getThumbUrl(): string
    {
        return $this->thumb;
    }

    public function isEnrolled(): bool
    {
        return $this->enrolled;
    }
}
