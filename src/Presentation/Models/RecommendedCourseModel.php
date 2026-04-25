<?php

/**
 * Recommended course tile on the learn hub empty state.
 *
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class RecommendedCourseModel
{
    private function __construct(
        private \WP_Post $course,
        private string $courseUrl,
        private string $thumb
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromServiceRow(array $row): self
    {
        $c = $row['course'] ?? null;
        if (!$c instanceof \WP_Post) {
            throw new \InvalidArgumentException('Expected course post.');
        }
        $cid = (int) $c->ID;

        return new self(
            $c,
            (string) ($row['course_url'] ?? get_permalink($cid) ?: ''),
            (string) ($row['thumb'] ?? '')
        );
    }

    public function getTitle(): string
    {
        return (string) get_the_title($this->course);
    }

    public function getCourseUrl(): string
    {
        return $this->courseUrl;
    }

    public function getThumbUrl(): string
    {
        return $this->thumb;
    }
}
