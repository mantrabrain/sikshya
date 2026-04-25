<?php

/**
 * Single-lesson learning shell (sidebar curriculum + tabs). Built by {@see \Sikshya\Services\Frontend\LessonPageService}.
 *
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class SingleLessonPageModel
{
    /**
     * @param array<string, mixed> $vm
     */
    private function __construct(
        private array $vm
    ) {
    }

    /**
     * @param array<string, mixed> $vm
     */
    public static function fromViewData(array $vm): self
    {
        return new self($vm);
    }

    /**
     * @return array<string, mixed>
     */
    public function toLegacyViewArray(): array
    {
        return $this->vm;
    }

    public function getLessonPost(): \WP_Post
    {
        $p = $this->vm['post'] ?? null;
        if (!$p instanceof \WP_Post) {
            throw new \RuntimeException('Invalid lesson shell data.');
        }

        return $p;
    }

    public function getLessonId(): int
    {
        return (int) ($this->vm['lesson_id'] ?? 0);
    }

    public function getCourseId(): int
    {
        return (int) ($this->vm['course_id'] ?? 0);
    }

    public function getCoursePost(): ?\WP_Post
    {
        $c = $this->vm['course'] ?? null;

        return $c instanceof \WP_Post ? $c : null;
    }

    public function getCourseTitleForTopbar(): string
    {
        $c = $this->getCoursePost();

        return $c ? (string) get_the_title($c) : (string) __('Learn', 'sikshya');
    }

    public function hasError(): bool
    {
        return $this->getErrorMessage() !== '';
    }

    public function getErrorMessage(): string
    {
        return (string) ($this->vm['error'] ?? '');
    }

    public function isShowProgress(): bool
    {
        return !empty($this->vm['show_progress']);
    }

    public function getProgressPercent(): int
    {
        $s = $this->vm['stats'] ?? null;

        return is_array($s) ? (int) ($s['percent'] ?? 0) : 0;
    }

    public function getProgressCompleted(): int
    {
        $s = $this->vm['stats'] ?? null;

        return is_array($s) ? (int) ($s['completed_items'] ?? 0) : 0;
    }

    public function getProgressTotal(): int
    {
        $s = $this->vm['stats'] ?? null;

        return is_array($s) ? (int) ($s['total_items'] ?? 0) : 0;
    }

    public function isEnrolled(): bool
    {
        return !empty($this->vm['enrolled']);
    }

    public function isPreview(): bool
    {
        return !empty($this->vm['is_preview']);
    }

    public function isCurrentCompleted(): bool
    {
        return !empty($this->vm['current_completed']);
    }

    public function getCurrentChapter(): ?\WP_Post
    {
        $c = $this->vm['current_chapter'] ?? null;

        return $c instanceof \WP_Post ? $c : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCurriculumBlocks(): array
    {
        $b = $this->vm['blocks'] ?? null;

        return is_array($b) ? $b : [];
    }

    public function hasDiscussionsTab(): bool
    {
        $f = $this->vm['course_features'] ?? null;

        return is_array($f) && !empty($f['discussions']);
    }

    public function hasReviewsTab(): bool
    {
        $f = $this->vm['course_features'] ?? null;

        return is_array($f) && !empty($f['reviews']);
    }

    public function getUrls(): LessonShellUrlsModel
    {
        $u = $this->vm['urls'] ?? null;

        return is_array($u) ? LessonShellUrlsModel::fromRow($u) : new LessonShellUrlsModel();
    }

    public function getRest(): LessonShellRestModel
    {
        $r = $this->vm['rest'] ?? null;

        return is_array($r) ? LessonShellRestModel::fromRow($r) : new LessonShellRestModel('', '');
    }

    public function getLessonTypeKey(): string
    {
        return sanitize_key((string) ($this->vm['lesson_type'] ?? ''));
    }

    public function getLessonIconForHeader(): string
    {
        $lesson_type = $this->getLessonTypeKey();
        switch ($lesson_type) {
            case 'video':
            case 'live':
                return 'play-video';
            case 'audio':
                return 'audio';
            case 'scorm':
            case 'h5p':
                return 'doc';
            default:
                return 'doc';
        }
    }

    public function getLessonH1Title(): string
    {
        return (string) get_the_title($this->getLessonPost());
    }

    public function getLessonContentHtml(): string
    {
        return (string) apply_filters('the_content', (string) $this->getLessonPost()->post_content);
    }

    public function hasRenderableLessonBody(): bool
    {
        return trim((string) $this->getLessonPost()->post_content) !== '';
    }

    public function getDockPrevious(): ?LessonShellNavLinkModel
    {
        $flat = $this->flattenNavItems();
        $idx = $this->findCurrentFlatIndex($flat);
        if ($idx <= 0) {
            return null;
        }
        $prev = $flat[$idx - 1] ?? null;

        return is_array($prev) ? new LessonShellNavLinkModel(
            (string) ($prev['title'] ?? ''),
            (string) ($prev['permalink'] ?? '')
        ) : null;
    }

    public function getDockNext(): ?LessonShellNavLinkModel
    {
        $flat = $this->flattenNavItems();
        $idx = $this->findCurrentFlatIndex($flat);
        if ($idx < 0 || $idx >= count($flat) - 1) {
            return null;
        }
        $next = $flat[$idx + 1] ?? null;

        return is_array($next) ? new LessonShellNavLinkModel(
            (string) ($next['title'] ?? ''),
            (string) ($next['permalink'] ?? '')
        ) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function flattenNavItems(): array
    {
        $flat = [];
        foreach ($this->getCurriculumBlocks() as $block) {
            foreach ((array) ($block['items'] ?? []) as $it) {
                if (is_array($it)) {
                    $flat[] = $it;
                }
            }
        }

        return $flat;
    }

    /**
     * @param list<array<string, mixed>> $flat
     */
    private function findCurrentFlatIndex(array $flat): int
    {
        foreach ($flat as $i => $it) {
            if (!empty($it['current'])) {
                return (int) $i;
            }
        }
        $lid = $this->getLessonId();
        foreach ($flat as $i => $it) {
            if ((int) ($it['id'] ?? 0) === $lid) {
                return (int) $i;
            }
        }

        return -1;
    }
}
