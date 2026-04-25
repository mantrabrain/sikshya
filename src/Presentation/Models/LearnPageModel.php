<?php

/**
 * Model for the /learn/ shell: hub, single course, or bundle. Templates use getters only.
 *
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class LearnPageModel
{
    /** @var list<HubCourseRowModel> */
    private array $hubRows = [];

    /** @var list<RecommendedCourseModel> */
    private array $recommended = [];

    /**
     * @param array<string, mixed> $vm
     */
    private function __construct(
        private array $vm
    ) {
        foreach ((array) ($this->vm['hub_courses'] ?? []) as $row) {
            if (is_array($row)) {
                $this->hubRows[] = HubCourseRowModel::fromServiceRow($row);
            }
        }
        foreach ((array) ($this->vm['hub_recommended'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            try {
                $this->recommended[] = RecommendedCourseModel::fromServiceRow($row);
            } catch (\InvalidArgumentException $e) {
                continue;
            }
        }
    }

    /**
     * @param array<string, mixed> $vm
     */
    public static function fromViewData(array $vm): self
    {
        return new self($vm);
    }

    public function getMode(): string
    {
        return (string) ($this->vm['mode'] ?? 'course');
    }

    public function getCourseId(): int
    {
        return (int) ($this->vm['course_id'] ?? 0);
    }

    public function getErrorMessage(): string
    {
        return (string) ($this->vm['error'] ?? '');
    }

    public function hasError(): bool
    {
        return $this->getErrorMessage() !== '';
    }

    public function isEnrolled(): bool
    {
        return !empty($this->vm['enrolled']);
    }

    public function isPreview(): bool
    {
        return !empty($this->vm['is_preview']);
    }

    public function isShowProgress(): bool
    {
        return !empty($this->vm['show_progress']);
    }

    public function getCoursePost(): ?\WP_Post
    {
        $c = $this->vm['course'] ?? null;

        return $c instanceof \WP_Post ? $c : null;
    }

    public function getCourseModel(): ?CourseModel
    {
        $p = $this->getCoursePost();

        return $p ? CourseModel::fromPost($p) : null;
    }

    public function getLearnTopbarTitle(): string
    {
        $m = $this->getCourseModel();

        return $m ? $m->getTitle() : '';
    }

    public function getLearnTopbarLabel(): string
    {
        return $this->getLearnTopbarTitle();
    }

    public function getCourseHeroThumbnailUrl(): string
    {
        return (string) ($this->vm['course_thumb'] ?? '');
    }

    /**
     * @return array<int, array<string, mixed>> Block graph for sidebar (internal shape; outline partial may iterate)
     */
    public function getCurriculumBlocks(): array
    {
        $b = $this->vm['blocks'] ?? null;

        return is_array($b) ? $b : [];
    }

    public function getUrls(): LearnPageUrlsModel
    {
        $u = $this->vm['urls'] ?? null;

        return is_array($u) ? LearnPageUrlsModel::fromUrlRow($u) : new LearnPageUrlsModel();
    }

    /**
     * @return list<HubCourseRowModel> Hub rows or bundle member rows
     */
    public function getHubOrBundleRows(): array
    {
        return $this->hubRows;
    }

    /**
     * @return list<RecommendedCourseModel>
     */
    public function getRecommendedCourses(): array
    {
        return $this->recommended;
    }

    /**
     * @return array{total_items: int, completed_items: int, percent: int}
     */
    public function getProgressStats(): array
    {
        $s = $this->vm['stats'] ?? null;
        if (!is_array($s)) {
            return ['total_items' => 0, 'completed_items' => 0, 'percent' => 0];
        }

        return [
            'total_items' => (int) ($s['total_items'] ?? 0),
            'completed_items' => (int) ($s['completed_items'] ?? 0),
            'percent' => (int) ($s['percent'] ?? 0),
        ];
    }

    public function getBundleHeadlineTitle(): string
    {
        $m = $this->getCourseModel();
        if ($m !== null) {
            return $m->getTitle();
        }
        if ($this->getCourseId() > 0) {
            $p = get_post($this->getCourseId());

            return $p instanceof \WP_Post ? (string) get_the_title($p) : (string) __('Bundle', 'sikshya');
        }

        return (string) __('Bundle', 'sikshya');
    }

    /**
     * @return array{total:int, done:int, average:int}|null
     */
    public function getBundleProgressCounts(): ?array
    {
        if ($this->getMode() !== 'bundle') {
            return null;
        }
        $rows = $this->getHubOrBundleRows();
        $total = count($rows);
        if ($total === 0) {
            return ['total' => 0, 'done' => 0, 'average' => 0];
        }
        $done = 0;
        $sum = 0;
        foreach ($rows as $r) {
            $p = $r->getProgressPercent();
            $sum += $p;
            if ($p >= 100) {
                ++$done;
            }
        }

        return [
            'total' => $total,
            'done' => $done,
            'average' => (int) round($sum / $total),
        ];
    }

    public function getBundlePermalinkForActions(): string
    {
        $m = $this->getCourseModel();
        if ($m !== null) {
            return $m->getPermalink();
        }
        if ($this->getCourseId() > 0) {
            return (string) (get_permalink($this->getCourseId()) ?: '');
        }

        return '';
    }

    public function getContinueLearnUrlForHero(): string
    {
        $u = $this->getUrls()->getLearnUrl();

        return $u !== '' ? $u : '#';
    }

    /**
     * Same key layout as the legacy `sikshya_learn_template_data` filter (for add-ons that still read an array).
     *
     * @return array<string, mixed>
     */
    public function toLegacyViewArray(): array
    {
        return $this->vm;
    }
}
