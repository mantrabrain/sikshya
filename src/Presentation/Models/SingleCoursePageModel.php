<?php

/**
 * Single-course landing page model (Udemy-style).
 *
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class SingleCoursePageModel
{
    /**
     * @param array<string, mixed> $vm
     */
    private function __construct(private array $vm)
    {
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

    public function getCourseId(): int
    {
        return (int) ($this->vm['course_id'] ?? 0);
    }

    public function getCoursePost(): ?\WP_Post
    {
        $p = $this->vm['post'] ?? null;

        return $p instanceof \WP_Post ? $p : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPricing(): array
    {
        $p = $this->vm['pricing'] ?? null;

        return is_array($p) ? $p : [];
    }

    public function isPaid(): bool
    {
        return !empty($this->vm['is_paid']);
    }

    public function isEnrolled(): bool
    {
        return !empty($this->vm['is_enrolled']);
    }

    public function canAdminEnrollWithoutPurchase(): bool
    {
        return !empty($this->vm['can_admin_enroll_without_purchase']);
    }

    public function getSubtitle(): string
    {
        return (string) ($this->vm['subtitle'] ?? '');
    }

    public function getInstructorUser(): ?\WP_User
    {
        $u = $this->vm['instructor'] ?? null;

        return $u instanceof \WP_User ? $u : null;
    }

    public function getLastUpdatedLabel(): string
    {
        return (string) ($this->vm['last_updated'] ?? '');
    }

    public function getLanguageLabel(): string
    {
        return (string) ($this->vm['language_label'] ?? '');
    }

    public function getDifficultyKey(): string
    {
        $d = (string) ($this->vm['difficulty'] ?? '');
        $d = sanitize_key($d);

        return $d;
    }

    public function getDurationLabel(): string
    {
        return (string) ($this->vm['duration'] ?? '');
    }

    public function getTargetAudienceHtml(): string
    {
        return (string) ($this->vm['target_audience_html'] ?? '');
    }

    /**
     * @return array<int, mixed>
     */
    public function getCurriculum(): array
    {
        $c = $this->vm['curriculum'] ?? null;

        return is_array($c) ? $c : [];
    }

    /**
     * @return array{chapters: int, items: int, lessons: int}
     */
    public function getCurriculumStats(): array
    {
        $s = $this->vm['curriculum_stats'] ?? null;
        if (!is_array($s)) {
            return ['chapters' => 0, 'items' => 0, 'lessons' => 0];
        }

        return [
            'chapters' => (int) ($s['chapters'] ?? 0),
            'items' => (int) ($s['items'] ?? 0),
            'lessons' => (int) ($s['lessons'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    public function getCategoryTrail(): array
    {
        $t = $this->vm['category_trail'] ?? null;

        return is_array($t) ? $t : [];
    }

    /**
     * @return string[]
     */
    public function getTagPills(): array
    {
        $t = $this->vm['tag_pills'] ?? null;

        return is_array($t) ? array_values(array_map('strval', $t)) : [];
    }

    /**
     * @return string[]
     */
    public function getLearningOutcomes(): array
    {
        $t = $this->vm['learning_outcomes'] ?? null;

        return is_array($t) ? array_values(array_map('strval', $t)) : [];
    }

    /**
     * @return string[]
     */
    public function getIncludesLines(): array
    {
        $t = $this->vm['includes_lines'] ?? null;

        return is_array($t) ? array_values(array_map('strval', $t)) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCartFlash(): ?array
    {
        $f = $this->vm['cart_flash'] ?? null;

        return is_array($f) ? $f : null;
    }

    public function isBundle(): bool
    {
        return !empty($this->vm['is_bundle']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBundleCourses(): array
    {
        $b = $this->vm['bundle_courses'] ?? null;

        return is_array($b) ? $b : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getVideoPreview(): ?array
    {
        $v = $this->vm['video_preview'] ?? null;

        return is_array($v) ? $v : null;
    }

    public function getFeaturedImageUrl(): string
    {
        return (string) ($this->vm['featured_image_url'] ?? '');
    }

    public function getDiscountPercent(): int
    {
        return (int) ($this->vm['discount_percent'] ?? 0);
    }

    public function getMoneyBackText(): string
    {
        return (string) ($this->vm['money_back_text'] ?? '');
    }

    public function getUrls(): SingleCourseUrlsModel
    {
        $u = $this->vm['urls'] ?? null;

        return is_array($u) ? SingleCourseUrlsModel::fromRow($u) : new SingleCourseUrlsModel();
    }

    /**
     * Pro populates this with aggregate/count and feature flags.
     *
     * @return array<string, mixed>
     */
    public function getReviewsVm(): array
    {
        $r = $this->vm['reviews_vm'] ?? null;

        return is_array($r) ? $r : ['enabled' => false];
    }
}

