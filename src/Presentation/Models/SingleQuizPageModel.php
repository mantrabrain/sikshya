<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only view model for {@see templates/single-quiz.php}.
 */
final class SingleQuizPageModel
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromViewData(array $data): self
    {
        return new self($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toLegacyViewArray(): array
    {
        return $this->data;
    }

    public function getPost(): \WP_Post
    {
        $p = $this->data['post'] ?? null;

        return $p instanceof \WP_Post ? $p : new \WP_Post((object) ['ID' => 0, 'post_title' => '']);
    }

    public function getCourse(): ?\WP_Post
    {
        $c = $this->data['course'] ?? null;

        return $c instanceof \WP_Post ? $c : null;
    }

    public function getError(): string
    {
        return (string) ($this->data['error'] ?? '');
    }

    public function hasError(): bool
    {
        return $this->getError() !== '';
    }

    public function isLoggedIn(): bool
    {
        return !empty($this->data['logged_in']);
    }

    public function isEnrolled(): bool
    {
        return !empty($this->data['enrolled']);
    }

    public function getShowProgress(): bool
    {
        return !empty($this->data['show_progress']);
    }

    public function getStatsPercent(): int
    {
        $s = $this->data['stats'] ?? [];

        return is_array($s) ? (int) ($s['percent'] ?? 0) : 0;
    }

    public function getStatsCompletedItems(): int
    {
        $s = $this->data['stats'] ?? [];

        return is_array($s) ? (int) ($s['completed_items'] ?? 0) : 0;
    }

    public function getStatsTotalItems(): int
    {
        $s = $this->data['stats'] ?? [];

        return is_array($s) ? (int) ($s['total_items'] ?? 0) : 0;
    }

    public function getUrlAccount(): string
    {
        $u = $this->data['urls'] ?? [];

        return is_array($u) ? (string) ($u['account'] ?? '') : '';
    }

    /**
     * @return array<string, mixed>|null Pro / addons may add quiz JS options.
     */
    public function getAdvanced(): ?array
    {
        $a = $this->data['advanced'] ?? null;

        return is_array($a) ? $a : null;
    }

    /**
     * @return array<int, mixed>
     */
    public function getBlocks(): array
    {
        $b = $this->data['blocks'] ?? [];

        return is_array($b) ? $b : [];
    }

    /**
     * @return array<int, mixed>
     */
    public function getQuestions(): array
    {
        $q = $this->data['questions'] ?? [];

        return is_array($q) ? $q : [];
    }

    public function getCurrentChapter(): ?\WP_Post
    {
        $c = $this->data['current_chapter'] ?? null;

        return $c instanceof \WP_Post ? $c : null;
    }

    public function getAttemptsMax(): int
    {
        return (int) ($this->data['attempts_max'] ?? 0);
    }

    public function isAttemptsExhausted(): bool
    {
        return !empty($this->data['attempts_exhausted']);
    }

    public function getAttemptsMessage(): string
    {
        return (string) ($this->data['attempts_message'] ?? '');
    }

    public function isCourseFeatureDiscussions(): bool
    {
        $cf = $this->data['course_features'] ?? [];

        return is_array($cf) && !empty($cf['discussions']);
    }

    public function isCourseFeatureReviews(): bool
    {
        $cf = $this->data['course_features'] ?? [];

        return is_array($cf) && !empty($cf['reviews']);
    }

    public function getPostContentRaw(): string
    {
        $p = $this->getPost();

        return (string) $p->post_content;
    }
}
