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
     * @var array<string, mixed>
     */
    private array $vm;

    /**
     * @param array<string, mixed> $vm
     */
    private function __construct(array $vm)
    {
        $this->vm = $vm;
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
     * Aggregate active-student count for this course (cached 15 min in a transient).
     *
     * Returns 0 when the enrollments table is missing or the count is zero. Sites
     * that want to hide the stat entirely can return 0 via the
     * `sikshya_single_course_student_count` filter.
     */
    public function getStudentCount(): int
    {
        $course_id = $this->getCourseId();
        if ($course_id <= 0) {
            return 0;
        }

        $cache_key = 'sikshya_course_student_count_' . $course_id;
        $cached = get_transient($cache_key);
        if (is_numeric($cached)) {
            $count = (int) $cached;
        } else {
            $repo = new \Sikshya\Database\Repositories\EnrollmentRepository();
            if (!$repo->tableExists()) {
                return 0;
            }
            $count = $repo->countByCourse($course_id);
            set_transient($cache_key, $count, 15 * MINUTE_IN_SECONDS);
        }

        /**
         * Filter the displayed student count for the single-course hero stat.
         *
         * Return 0 to hide the stat entirely; return a different number to
         * surface a curated value (e.g. enrolled + waitlist).
         *
         * @param int $count    Computed enrollment count.
         * @param int $course_id Course post ID.
         */
        $count = (int) apply_filters('sikshya_single_course_student_count', $count, $course_id);
        return $count > 0 ? $count : 0;
    }

    /**
     * Walk the curriculum once and return the learn-shell URL for the first item
     * flagged `_sikshya_is_free` (preview). Used by the single-course hero to
     * surface a "Watch a free preview" CTA when at least one such item exists.
     *
     * Returns an empty string when no previewable item is found.
     */
    public function getFirstPreviewableUrl(): string
    {
        foreach ($this->getCurriculum() as $chapter) {
            if (!is_array($chapter)) {
                continue;
            }
            $contents = $chapter['contents'] ?? null;
            if (!is_array($contents)) {
                continue;
            }
            foreach ($contents as $item) {
                if (!($item instanceof \WP_Post)) {
                    continue;
                }
                $is_free = \Sikshya\Services\Settings::isTruthy(
                    get_post_meta((int) $item->ID, '_sikshya_is_free', true)
                );
                if (!$is_free) {
                    continue;
                }
                return \Sikshya\Frontend\Site\PublicPageUrls::learnContentForPost($item);
            }
        }
        return '';
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

