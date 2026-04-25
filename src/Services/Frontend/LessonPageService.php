<?php

namespace Sikshya\Services\Frontend;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Database\Repositories\ProgressRepository;
use Sikshya\Frontend\Public\CurriculumOutlineMeta;
use Sikshya\Frontend\Public\PublicPageUrls;
use Sikshya\Presentation\Models\SingleLessonPageModel;
use Sikshya\Services\PublicCurriculumService;
use Sikshya\Services\Settings;

/**
 * Builds the single-lesson shell view model for enrolled / preview learners.
 *
 * @package Sikshya\Services\Frontend
 */
final class LessonPageService
{
    public static function forPost(\WP_Post $post): SingleLessonPageModel
    {
        $lesson_id = (int) $post->ID;
        $course_id = self::lessonCourseId($lesson_id);
        $uid       = get_current_user_id();

        $error    = '';
        $enrolled = false;
        $blocks   = [];
        $is_preview = false;

        $track_progress = Settings::isTruthy(Settings::get('track_lesson_progress', true));
        $show_progress = Settings::isTruthy(Settings::get('students_can_see_progress', true));

        if ($course_id <= 0) {
            $error = __('This lesson is not linked to a course.', 'sikshya');
        } else {
            $repo = new EnrollmentRepository();
            $enrolled = $uid > 0 && $repo->findByUserAndCourse($uid, $course_id) !== null;

            $raw = PublicCurriculumService::getCourseCurriculum($course_id);

            // Allow preview when enabled globally and for the course.
            if (!$enrolled) {
                $is_preview = self::canPreviewContent($course_id, $raw, $lesson_id);
            }

            if (!$enrolled && !$is_preview) {
                $error = $uid <= 0
                    ? __('Please log in to access this lesson.', 'sikshya')
                    : __('You are not enrolled in this course.', 'sikshya');
            } else {
                if ($error === '' && $enrolled && !$is_preview) {
                    $access = apply_filters(
                        'sikshya_access_check',
                        ['ok' => true, 'message' => ''],
                        [
                            'type' => 'lesson',
                            'user_id' => $uid,
                            'course_id' => $course_id,
                            'content_id' => $lesson_id,
                        ]
                    );
                    if (is_array($access) && isset($access['ok']) && $access['ok'] === false) {
                        $msg = isset($access['message']) ? (string) $access['message'] : '';
                        $error = $msg !== '' ? $msg : __('This content is not available yet.', 'sikshya');
                    }
                }

                $blocks = self::enrichBlocks(
                    $uid,
                    $course_id,
                    $raw,
                    $lesson_id,
                    $track_progress && $show_progress
                );
            }
        }

        $course_post = $course_id > 0 ? get_post($course_id) : null;
        $stats       = ($track_progress && $show_progress) ? self::computeStats($blocks) : ['total_items' => 0, 'completed_items' => 0, 'percent' => 0];
        $nav         = self::computePrevNext($blocks, $lesson_id);
        $current_chapter = self::currentChapterFor($blocks);
        $current_completed = self::isCurrentCompleted($blocks);

        $course_features = self::courseFeatures($course_id);

        $lesson_type = sanitize_key((string) get_post_meta($lesson_id, '_sikshya_lesson_type', true));

        $vm = apply_filters(
            'sikshya_lesson_template_data',
            [
                'post' => $post,
                'lesson_id' => $lesson_id,
                'course_id' => $course_id,
                'course' => $course_post,
                'current_chapter' => $current_chapter,
                'logged_in' => $uid > 0,
                'enrolled' => $enrolled,
                'is_preview' => $is_preview,
                'error' => $error,
                'blocks' => $blocks,
                'stats' => $stats,
                'nav' => $nav,
                'show_progress' => $track_progress && $show_progress,
                'current_completed' => $current_completed,
                'course_features' => $course_features,
                'lesson_type' => $lesson_type,
                'urls' => [
                    'courses' => get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'),
                    'login' => wp_login_url(get_permalink($post) ?: ''),
                    'course' => $course_post ? (get_permalink($course_post) ?: '') : '',
                    'learn' => PublicPageUrls::learnForCourse($course_id),
                    'account' => PublicPageUrls::url('account'),
                ],
                'rest' => [
                    'url' => esc_url_raw(rest_url('sikshya/v1/')),
                    'nonce' => wp_create_nonce('wp_rest'),
                ],
            ],
            $post
        );

        return SingleLessonPageModel::fromViewData(is_array($vm) ? $vm : []);
    }

    /**
     * @param array<int, array{chapter: \WP_Post, items: array<int, array<string, mixed>>}> $blocks
     */
    private static function isCurrentCompleted(array $blocks): bool
    {
        foreach ($blocks as $block) {
            foreach ((array) ($block['items'] ?? []) as $item) {
                if (!empty($item['current'])) {
                    return !empty($item['completed']);
                }
            }
        }

        return false;
    }

    /**
     * Whether the current content is preview-accessible (guest or non-enrolled).
     *
     * Rules:
     * - Global setting: enable_course_preview (default true)
     * - Per-course meta: _sikshya_enable_course_preview (truthy)
     * - If lesson meta _sikshya_is_free is truthy → allow
     * - Else allow when within first N items (preview_lessons_count) in curriculum (lessons only)
     *
     * @param array<int, array{chapter:\WP_Post, contents: array<int, \WP_Post>}> $raw_curriculum
     */
    private static function canPreviewContent(int $course_id, array $raw_curriculum, int $lesson_id): bool
    {
        if (!Settings::isTruthy(Settings::get('enable_course_preview', true))) {
            return false;
        }

        $course_preview = get_post_meta($course_id, '_sikshya_enable_course_preview', true);
        if (!Settings::isTruthy($course_preview)) {
            return false;
        }

        $is_free = get_post_meta($lesson_id, '_sikshya_is_free', true);
        if (Settings::isTruthy($is_free)) {
            return true;
        }

        $cap = (int) Settings::get('preview_lessons_count', 3);
        if ($cap <= 0) {
            return false;
        }

        $seen = 0;
        foreach ($raw_curriculum as $row) {
            foreach ((array) ($row['contents'] ?? []) as $p) {
                if (!$p instanceof \WP_Post) {
                    continue;
                }
                if ($p->post_type !== PostTypes::LESSON) {
                    continue;
                }
                ++$seen;
                if ((int) $p->ID === $lesson_id) {
                    return $seen <= $cap;
                }
                if ($seen >= $cap && $cap > 0) {
                    // early exit if we've passed the preview window.
                    continue;
                }
            }
        }

        return false;
    }

    /**
     * Effective course features for Learn UI (global + per-course).
     *
     * @return array{reviews: bool, ratings: bool, discussions: bool, qa: bool, certificate: bool}
     */
    private static function courseFeatures(int $course_id): array
    {
        $global_reviews = Settings::isTruthy(Settings::get('enable_reviews', true));
        $global_ratings = Settings::isTruthy(Settings::get('enable_ratings', true));

        // These features may be enabled in settings/meta, but the actual Learn-page UI may not exist
        // unless a Pro/add-on provides it. Default to "not available" to avoid dead tabs.
        $reviews_available = (bool) apply_filters('sikshya_feature_reviews_available', false, $course_id, 'learn');
        $discussions_available = (bool) apply_filters('sikshya_feature_discussions_available', false, $course_id, 'learn');

        return [
            'reviews' => $reviews_available
                && $global_reviews
                && Settings::isTruthy(get_post_meta($course_id, '_sikshya_enable_reviews', true)),
            'ratings' => $global_ratings,
            'discussions' => $discussions_available
                && Settings::isTruthy(get_post_meta($course_id, '_sikshya_enable_discussions', true)),
            'qa' => Settings::isTruthy(get_post_meta($course_id, '_sikshya_enable_qa', true)),
            'certificate' => Settings::isTruthy(get_post_meta($course_id, '_sikshya_enable_certificate', true)),
        ];
    }

    /**
     * @param array<int, array{chapter: \WP_Post, items: array<int, array<string, mixed>>}> $blocks
     * @return \WP_Post|null
     */
    private static function currentChapterFor(array $blocks): ?\WP_Post
    {
        foreach ($blocks as $block) {
            foreach ((array) ($block['items'] ?? []) as $item) {
                if (!empty($item['current']) && isset($block['chapter']) && $block['chapter'] instanceof \WP_Post) {
                    return $block['chapter'];
                }
            }
        }

        return null;
    }

    private static function lessonCourseId(int $lesson_id): int
    {
        $a = (int) get_post_meta($lesson_id, '_sikshya_lesson_course', true);
        if ($a > 0) {
            return $a;
        }

        return (int) get_post_meta($lesson_id, 'sikshya_lesson_course', true);
    }

    /**
     * @param array<int, array{chapter: \WP_Post, contents: array<int, \WP_Post>}> $raw
     * @return array<int, array{chapter: \WP_Post, items: array<int, array<string, mixed>>, item_count: int, open: bool}>
     */
    private static function enrichBlocks(int $user_id, int $course_id, array $raw, int $current_post_id, bool $with_progress): array
    {
        $progress = new ProgressRepository();
        $out      = [];

        foreach ($raw as $row) {
            $chapter = $row['chapter'];
            $items   = [];
            $idx     = 0;

            foreach ($row['contents'] as $p) {
                if (!$p instanceof \WP_Post) {
                    continue;
                }

                ++$idx;

                $is_current = (int) $p->ID === $current_post_id;

                $type_key   = self::contentTypeKey($p->post_type);
                $pto        = get_post_type_object($p->post_type);
                $type_label = $pto && isset($pto->labels->singular_name)
                    ? (string) $pto->labels->singular_name
                    : $p->post_type;

                $items[] = [
                    'post' => $p,
                    'id' => (int) $p->ID,
                    'permalink' => self::learnPermalinkFor($p, $type_key),
                    'title' => get_the_title($p),
                    'type_key' => $type_key,
                    'type_label' => $type_label,
                    'lesson_type' => $type_key === 'lesson' ? sanitize_key((string) get_post_meta((int) $p->ID, '_sikshya_lesson_type', true)) : '',
                    'meta_line' => CurriculumOutlineMeta::itemMetaLine($p, $type_key),
                    'duration_minutes' => CurriculumOutlineMeta::itemDurationMinutes($p, $type_key),
                    'subtitle_compact' => CurriculumOutlineMeta::itemSubtitleCompact($p, $type_key),
                    'index_in_section' => $idx,
                    'completed' => $with_progress ? self::isItemCompleted($progress, $user_id, $course_id, $p) : false,
                    'current' => $is_current,
                ];
            }

            $completed_in_section = 0;
            $section_mins         = 0;
            foreach ($items as $it) {
                if ($with_progress && !empty($it['completed'])) {
                    ++$completed_in_section;
                }
                $section_mins += (int) ($it['duration_minutes'] ?? 0);
            }

            $out[] = [
                'chapter' => $chapter,
                'items' => $items,
                'item_count' => count($items),
                'completed_in_section' => $with_progress ? $completed_in_section : 0,
                'section_duration_minutes' => $section_mins,
                // Outline UI: all chapters expanded by default (see curriculum partial).
                'open' => true,
            ];
        }

        return $out;
    }

    private static function contentTypeKey(string $post_type): string
    {
        switch ($post_type) {
            case PostTypes::LESSON:
                return 'lesson';
            case PostTypes::QUIZ:
                return 'quiz';
            case PostTypes::ASSIGNMENT:
                return 'assignment';
            default:
                return 'content';
        }
    }

    private static function learnPermalinkFor(\WP_Post $p, string $type_key): string
    {
        if (in_array($type_key, ['lesson', 'quiz', 'assignment'], true)) {
            return PublicPageUrls::learnContentForPost($p);
        }

        return get_permalink($p) ?: '';
    }

    private static function isItemCompleted(ProgressRepository $progress, int $user_id, int $course_id, \WP_Post $p): bool
    {
        if ($p->post_type === PostTypes::LESSON) {
            return $progress->hasLessonCompletion($user_id, $course_id, (int) $p->ID);
        }

        if ($p->post_type === PostTypes::QUIZ) {
            return $progress->hasQuizCompletion($user_id, $course_id, (int) $p->ID);
        }

        return false;
    }

    /**
     * @param array<int, array{chapter: \WP_Post, items: array<int, array<string, mixed>>, open: bool}> $blocks
     * @return array{total_items: int, completed_items: int, percent: int}
     */
    private static function computeStats(array $blocks): array
    {
        $total     = 0;
        $completed = 0;

        foreach ($blocks as $block) {
            foreach ($block['items'] as $item) {
                ++$total;
                if (!empty($item['completed'])) {
                    ++$completed;
                }
            }
        }

        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return [
            'total_items' => $total,
            'completed_items' => $completed,
            'percent' => $percent,
        ];
    }

    /**
     * @param array<int, array{chapter: \WP_Post, items: array<int, array<string, mixed>>, open: bool}> $blocks
     * @return array{prev: string, next: string}
     */
    private static function computePrevNext(array $blocks, int $current_post_id): array
    {
        $flat = [];
        foreach ($blocks as $block) {
            foreach ($block['items'] as $item) {
                if (!empty($item['permalink'])) {
                    $flat[] = [
                        'id' => (int) ($item['id'] ?? 0),
                        'permalink' => (string) $item['permalink'],
                    ];
                }
            }
        }

        $prev = '';
        $next = '';
        foreach ($flat as $i => $row) {
            if ($row['id'] !== $current_post_id) {
                continue;
            }
            if ($i > 0) {
                $prev = $flat[$i - 1]['permalink'];
            }
            if ($i < count($flat) - 1) {
                $next = $flat[$i + 1]['permalink'];
            }
            break;
        }

        return [
            'prev' => $prev,
            'next' => $next,
        ];
    }
}
