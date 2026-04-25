<?php

namespace Sikshya\Services\Frontend;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\CourseRepository;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Database\Repositories\ProgressRepository;
use Sikshya\Presentation\Models\LearnPageModel;
use Sikshya\Services\PublicCurriculumService;
use Sikshya\Services\Settings;
use Sikshya\Frontend\Public\CurriculumOutlineMeta;
use Sikshya\Frontend\Public\PublicPageUrls;

/**
 * Application service: learn hub / course curriculum / bundle views.
 * Produces a {@see LearnPageModel} for model-only templates. No HTML.
 *
 * @package Sikshya\Services\Frontend
 */
final class LearnPageService
{
    public static function fromRequest(): LearnPageModel
    {
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        $uid       = get_current_user_id();
        $error     = '';
        $blocks    = [];
        $enrolled  = false;
        $mode      = 'course';
        $is_preview = false;
        $show_progress = false;
        $hub_courses = [];
        $hub_recommended = [];

        if ($uid <= 0) {
            $error = __('Please log in to access your learning.', 'sikshya');
        } elseif ($course_id <= 0) {
            // Learn hub (/learn/ without course_id): show enrolled courses + continue actions.
            $mode = 'hub';
            $hub_courses = self::buildHubCourses($uid);
            $hub_recommended = self::buildRecommendedCourses();
        } elseif (sanitize_key((string) get_post_meta($course_id, '_sikshya_course_type', true)) === 'bundle') {
            // Bundle learn page: progress dashboard across all courses in the bundle.
            $mode = 'bundle';
            $hub_courses = self::buildBundleCourses($uid, $course_id);
            if ($hub_courses === [] && !current_user_can('edit_posts')) {
                $error = __('You have not purchased this bundle yet.', 'sikshya');
            }
        } else {
            $repo = new EnrollmentRepository();
            $enrolled = $repo->findByUserAndCourse($uid, $course_id) !== null;
            $raw = PublicCurriculumService::getCourseCurriculum($course_id);

            $track = Settings::isTruthy(Settings::get('track_lesson_progress', true));
            $can_see = Settings::isTruthy(Settings::get('students_can_see_progress', true));
            $show_progress = $enrolled && $track && $can_see;

            if (!$enrolled) {
                $is_preview = self::canPreviewCourse($course_id);
                if ($is_preview) {
                    $blocks = self::enrichBlocksPreview($raw, $course_id);
                } else {
                    $error = __('You are not enrolled in this course.', 'sikshya');
                }
            } else {
                $blocks = self::enrichBlocks($uid, $course_id, $raw);
            }
        }

        $course_post = $course_id > 0 ? get_post($course_id) : null;
        $stats       = $show_progress ? self::computeStats($blocks) : ['total_items' => 0, 'completed_items' => 0, 'percent' => 0];

        $vm = [
            'mode'        => $mode,
            'course_id'   => $course_id,
            'course'      => $course_post,
            'enrolled'    => $enrolled,
            'is_preview'  => $is_preview,
            'curriculum'  => $blocks,
            'blocks'      => $blocks,
            'hub_courses' => $hub_courses,
            'hub_recommended' => $hub_recommended,
            'stats'       => $stats,
            'error'       => $error,
            'show_progress' => $show_progress,
            'course_thumb'=> $course_post ? get_the_post_thumbnail_url($course_post->ID, 'large') : '',
            'urls'        => [
                'account' => PublicPageUrls::url('account'),
                'course'  => $course_post ? (get_permalink($course_post) ?: '') : '',
                'learn'   => PublicPageUrls::learnForCourse($course_id),
                'courses_archive' => get_post_type_archive_link(PostTypes::COURSE) ?: '',
                'login' => wp_login_url(PublicPageUrls::url('learn')),
            ],
        ];

        $vm = apply_filters('sikshya_learn_template_data', $vm);

        return LearnPageModel::fromViewData($vm);
    }

    private static function canPreviewCourse(int $course_id): bool
    {
        if ($course_id <= 0) {
            return false;
        }
        if (!Settings::isTruthy(Settings::get('enable_course_preview', true))) {
            return false;
        }
        $course_preview = get_post_meta($course_id, '_sikshya_enable_course_preview', true);
        return Settings::isTruthy($course_preview);
    }

    /**
     * Preview-capable blocks: keep curriculum visible but lock non-preview items.
     *
     * @param array<int, array{chapter: \WP_Post, contents: array<int, \WP_Post>}> $raw
     * @return array<int, array{chapter: \WP_Post, items: array<int, array<string, mixed>>, item_count: int, completed_in_section: int, section_duration_minutes: int}>
     */
    private static function enrichBlocksPreview(array $raw, int $course_id): array
    {
        $out = [];

        $preview_n = (int) Settings::get('preview_lessons_count', 0);
        if ($preview_n < 0) {
            $preview_n = 0;
        }
        $lesson_budget = $preview_n;

        $course_url = $course_id > 0 ? (get_permalink($course_id) ?: '') : '';

        foreach ($raw as $row) {
            $chapter = $row['chapter'];
            $items = [];
            $idx = 0;
            $section_mins = 0;

            foreach ((array) ($row['contents'] ?? []) as $p) {
                if (!$p instanceof \WP_Post) {
                    continue;
                }
                ++$idx;

                $type_key = self::contentTypeKey($p->post_type);
                $pto = get_post_type_object($p->post_type);
                $type_label = $pto && isset($pto->labels->singular_name) ? (string) $pto->labels->singular_name : $p->post_type;

                $is_free = Settings::isTruthy(get_post_meta((int) $p->ID, '_sikshya_is_free', true));
                $preview_allowed = false;

                if ($type_key === 'lesson') {
                    if ($is_free) {
                        $preview_allowed = true;
                    } elseif ($lesson_budget > 0) {
                        $preview_allowed = true;
                        --$lesson_budget;
                    }
                } elseif ($type_key === 'quiz') {
                    // Quizzes are previewable only if explicitly free.
                    $preview_allowed = $is_free;
                } else {
                    // Other types default to locked unless explicitly free.
                    $preview_allowed = $is_free;
                }

                $item_url = $preview_allowed ? self::learnPermalinkFor($p, $type_key) : $course_url;
                $mins = CurriculumOutlineMeta::itemDurationMinutes($p, $type_key);
                $section_mins += (int) $mins;

                $items[] = [
                    'post' => $p,
                    'permalink' => $item_url,
                    'title' => get_the_title($p),
                    'type_key' => $type_key,
                    'type_label' => $type_label,
                    'lesson_type' => $type_key === 'lesson' ? sanitize_key((string) get_post_meta((int) $p->ID, '_sikshya_lesson_type', true)) : '',
                    'meta_line' => CurriculumOutlineMeta::itemMetaLine($p, $type_key),
                    'duration_minutes' => $mins,
                    'subtitle_compact' => CurriculumOutlineMeta::itemSubtitleCompact($p, $type_key),
                    'index_in_section' => $idx,
                    'completed' => false,
                    'preview_allowed' => $preview_allowed,
                ];
            }

            $out[] = [
                'chapter' => $chapter,
                'items' => $items,
                'item_count' => count($items),
                'completed_in_section' => 0,
                'section_duration_minutes' => $section_mins,
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array{chapter: \WP_Post, contents: array<int, \WP_Post>}> $raw
     * @return array<int, array{chapter: \WP_Post, items: array<int, array<string, mixed>>, item_count: int}>
     */
    private static function enrichBlocks(int $user_id, int $course_id, array $raw): array
    {
        $progress = new ProgressRepository();
        $out      = [];

        foreach ($raw as $row) {
            $chapter = $row['chapter'];
            $items   = [];
            $idx     = 0;

            foreach ($row['contents'] as $p) {
                if (! $p instanceof \WP_Post) {
                    continue;
                }

                ++$idx;

                $type_key   = self::contentTypeKey($p->post_type);
                $pto        = get_post_type_object($p->post_type);
                $type_label = $pto && isset($pto->labels->singular_name)
                    ? (string) $pto->labels->singular_name
                    : $p->post_type;

                $item = [
                    'post'        => $p,
                    'permalink'   => self::learnPermalinkFor($p, $type_key),
                    'title'       => get_the_title($p),
                    'type_key'    => $type_key,
                    'type_label'  => $type_label,
                    'lesson_type' => $type_key === 'lesson' ? sanitize_key((string) get_post_meta((int) $p->ID, '_sikshya_lesson_type', true)) : '',
                    'meta_line'   => CurriculumOutlineMeta::itemMetaLine($p, $type_key),
                    'duration_minutes' => CurriculumOutlineMeta::itemDurationMinutes($p, $type_key),
                    'subtitle_compact' => CurriculumOutlineMeta::itemSubtitleCompact($p, $type_key),
                    'index_in_section' => $idx,
                    'completed'   => self::isItemCompleted($progress, $user_id, $course_id, $p),
                    'locked'      => false,
                    'lock_reason' => '',
                ];

                $item = apply_filters(
                    'sikshya_learn_outline_item',
                    $item,
                    [
                        'user_id' => $user_id,
                        'course_id' => $course_id,
                        'content_id' => (int) $p->ID,
                        'type' => $type_key,
                    ]
                );

                $items[] = is_array($item) ? $item : [
                    'post' => $p,
                    'permalink' => self::learnPermalinkFor($p, $type_key),
                    'title' => get_the_title($p),
                    'type_key' => $type_key,
                    'type_label' => $type_label,
                    'lesson_type' => $type_key === 'lesson' ? sanitize_key((string) get_post_meta((int) $p->ID, '_sikshya_lesson_type', true)) : '',
                    'meta_line' => CurriculumOutlineMeta::itemMetaLine($p, $type_key),
                    'duration_minutes' => CurriculumOutlineMeta::itemDurationMinutes($p, $type_key),
                    'subtitle_compact' => CurriculumOutlineMeta::itemSubtitleCompact($p, $type_key),
                    'index_in_section' => $idx,
                    'completed' => self::isItemCompleted($progress, $user_id, $course_id, $p),
                    'locked' => false,
                    'lock_reason' => '',
                ];
            }

            $completed_in_section = 0;
            $section_mins         = 0;
            foreach ($items as $it) {
                if (!empty($it['completed'])) {
                    ++$completed_in_section;
                }
                $section_mins += (int) ($it['duration_minutes'] ?? 0);
            }

            $out[] = [
                'chapter' => $chapter,
                'items'   => $items,
                'item_count' => count($items),
                'completed_in_section' => $completed_in_section,
                'section_duration_minutes' => $section_mins,
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
            return $progress->hasLessonCompletion($user_id, $course_id, $p->ID);
        }

        if ($p->post_type === PostTypes::QUIZ) {
            return $progress->hasQuizCompletion($user_id, $course_id, $p->ID);
        }

        return false;
    }

    /**
     * @param array<int, array{chapter: \WP_Post, items: array<int, array<string, mixed>>}> $blocks
     * @return array{total_items: int, completed_items: int, percent: int}
     */
    private static function computeStats(array $blocks): array
    {
        $total     = 0;
        $completed = 0;

        foreach ($blocks as $block) {
            foreach ($block['items'] as $item) {
                ++$total;
                if (! empty($item['completed'])) {
                    ++$completed;
                }
            }
        }

        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return [
            'total_items'     => $total,
            'completed_items' => $completed,
            'percent'         => $percent,
        ];
    }

    /**
     * Learn hub courses list for current user.
     *
     * @return array<int, array{course:\WP_Post,progress:int,continue_url:string,course_url:string,thumb:string}>
     */
    private static function buildHubCourses(int $user_id): array
    {
        $repo = new EnrollmentRepository();
        $rows = $repo->findByUser($user_id, ['limit' => 50, 'offset' => 0]);
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $cid = isset($row->course_id) ? (int) $row->course_id : 0;
            if ($cid <= 0) {
                continue;
            }
            $course = get_post($cid);
            if (!$course instanceof \WP_Post || $course->post_status !== 'publish') {
                continue;
            }

            $progress = 0;
            if (isset($row->progress) && is_numeric($row->progress)) {
                $progress = (int) max(0, min(100, round((float) $row->progress)));
            }

            $continue_url = self::firstItemUrlForCourse($cid);

            $out[] = [
                'course' => $course,
                'progress' => $progress,
                'continue_url' => $continue_url,
                'course_url' => get_permalink($cid) ?: '',
                'thumb' => get_the_post_thumbnail_url($cid, 'medium') ?: '',
            ];
        }

        return $out;
    }

    private static function firstItemUrlForCourse(int $course_id): string
    {
        if ($course_id <= 0) {
            return PublicPageUrls::url('learn');
        }

        $raw = PublicCurriculumService::getCourseCurriculum($course_id);
        foreach ($raw as $block) {
            foreach ((array) ($block['contents'] ?? []) as $p) {
                if (!$p instanceof \WP_Post) {
                    continue;
                }
                $type_key = self::contentTypeKey((string) $p->post_type);
                if (!in_array($type_key, ['lesson', 'quiz', 'assignment'], true)) {
                    continue;
                }
                return PublicPageUrls::learnContentForPost($p);
            }
        }

        return PublicPageUrls::learnForCourse($course_id);
    }

    /**
     * Bundle learn page: one card per course in the bundle, with per-course progress.
     * Admins/instructors see all courses; learners only see courses they are enrolled in.
     *
     * @return array<int, array{course:\WP_Post,progress:int,continue_url:string,course_url:string,thumb:string}>
     */
    private static function buildBundleCourses(int $user_id, int $bundle_id): array
    {
        $raw = get_post_meta($bundle_id, '_sikshya_bundle_course_ids', true);

        if (empty($raw)) {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $repo = new EnrollmentRepository();
        $out  = [];

        foreach ($raw as $cid) {
            $cid = (int) $cid;
            if ($cid <= 0) {
                continue;
            }
            $course = get_post($cid);
            if (!$course instanceof \WP_Post || $course->post_status !== 'publish') {
                continue;
            }

            $enrollment = $repo->findByUserAndCourse($user_id, $cid);
            $progress   = 0;
            if ($enrollment !== null && isset($enrollment->progress) && is_numeric($enrollment->progress)) {
                $progress = (int) max(0, min(100, round((float) $enrollment->progress)));
            }

            // Allow admins / instructors to preview the bundle learn page even without enrollment.
            if ($enrollment === null && !current_user_can('edit_posts')) {
                continue;
            }

            $out[] = [
                'course'       => $course,
                'progress'     => $progress,
                'continue_url' => self::firstItemUrlForCourse($cid),
                'course_url'   => get_permalink($cid) ?: '',
                'thumb'        => get_the_post_thumbnail_url($cid, 'medium') ?: '',
                'enrolled'     => $enrollment !== null,
            ];
        }

        return $out;
    }

    /**
     * Recommended courses for the hub empty state (logged-in, no enrollments).
     *
     * @return array<int, array{course:\WP_Post,course_url:string,thumb:string}>
     */
    private static function buildRecommendedCourses(): array
    {
        $repo = new CourseRepository();
        $posts = $repo->findAll(
            [
                'posts_per_page' => 6,
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
            ]
        );

        $out = [];
        foreach ($posts as $p) {
            if (!$p instanceof \WP_Post) {
                continue;
            }
            $cid = (int) $p->ID;
            $out[] = [
                'course' => $p,
                'course_url' => get_permalink($cid) ?: '',
                'thumb' => get_the_post_thumbnail_url($cid, 'medium') ?: '',
            ];
        }

        return $out;
    }
}
