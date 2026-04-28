<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\ProgressRepository;
use Sikshya\Services\CourseService;
use Sikshya\Database\Repositories\QuizAttemptRepository;
use Sikshya\Frontend\Public\PublicPageUrls;
use Sikshya\Services\PublicCurriculumService;
use Sikshya\Presentation\Models\SingleQuizPageModel;
use Sikshya\Services\Frontend\QuizPageService;
use Sikshya\Services\Settings;

/**
 * @package Sikshya\Frontend\Public
 */
final class QuizTemplateData
{
    /**
     * Build template view-model for a quiz post (questions for UI; never exposes correct keys).
     *
     * @return array<string, mixed>
     */
    public static function legacyArrayForPost(\WP_Post $post): array
    {
        $quiz_id = (int) $post->ID;
        $course_id = (int) get_post_meta($quiz_id, '_sikshya_quiz_course', true);
        $uid = get_current_user_id();

        $error = '';
        $enrolled = false;
        $blocks = [];
        $is_preview = false;

        $track_progress = Settings::isTruthy(Settings::get('track_quiz_progress', true));
        $show_progress = Settings::isTruthy(Settings::get('students_can_see_progress', true));
        if ($uid <= 0) {
            // allow preview below
        }

        if ($course_id <= 0) {
            $error = __('This quiz is not linked to a course.', 'sikshya');
        } else {
            $courses  = new CourseService();
            $enrolled = $uid > 0 && $courses->isUserEnrolled($uid, $course_id);
            $raw    = PublicCurriculumService::getCourseCurriculum($course_id);

            // Quizzes can be previewed only when explicitly marked free.
            if (!$enrolled) {
                $is_preview = self::canPreviewQuiz($course_id, $quiz_id);
            }

            if (!$enrolled && !$is_preview) {
                $error = $uid <= 0
                    ? __('Please log in to access this quiz.', 'sikshya')
                    : __('You are not enrolled in this course.', 'sikshya');
            } else {
                if ($error === '' && $enrolled && !$is_preview) {
                    $access = apply_filters(
                        'sikshya_access_check',
                        ['ok' => true, 'message' => ''],
                        [
                            'type' => 'quiz',
                            'user_id' => $uid,
                            'course_id' => $course_id,
                            'content_id' => $quiz_id,
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
                    $quiz_id,
                    $track_progress && $show_progress
                );
            }
        }

        $attempts_used = 0;
        $attempts_max = self::quizAttemptsCap($quiz_id);
        $attempts_exhausted = false;
        $attempts_message = '';
        if ($uid > 0 && $quiz_id > 0) {
            $attempts_used = (new QuizAttemptRepository())->countAttemptsForUserQuiz($uid, $quiz_id);
        }
        if ($attempts_max <= 0) {
            $attempts_max = (int) Settings::get('quiz_attempts_limit', 1);
        }
        if ($attempts_max < 0) {
            $attempts_max = 0;
        }

        if ($error === '' && $enrolled && $attempts_max > 0 && $attempts_used >= $attempts_max) {
            $attempts_exhausted = true;
            $attempts_message = __('You have reached the maximum number of attempts for this quiz.', 'sikshya');
        }

        $course_post = $course_id > 0 ? get_post($course_id) : null;
        $stats       = ($track_progress && $show_progress) ? self::computeStats($blocks) : ['total_items' => 0, 'completed_items' => 0, 'percent' => 0];
        $nav         = self::computePrevNext($blocks, $quiz_id);
        $current_chapter = self::currentChapterFor($blocks);
        $questions = self::buildQuestionsForQuiz($quiz_id);
        $course_features = self::courseFeatures($course_id);

        return apply_filters(
            'sikshya_quiz_template_data',
            [
                'post' => $post,
                'course_id' => $course_id,
                'logged_in' => $uid > 0,
                'enrolled' => $enrolled,
                'error' => $error,
                'course' => $course_post,
                'current_chapter' => $current_chapter,
                'blocks' => $blocks,
                'stats' => $stats,
                'nav' => $nav,
                'questions' => $questions,
                'attempts_used' => $attempts_used,
                'attempts_max' => $attempts_max,
                'attempts_limited' => $attempts_max > 0,
                'attempts_remaining' => $attempts_max > 0 ? max(0, $attempts_max - $attempts_used) : null,
                'attempts_exhausted' => $attempts_exhausted,
                'attempts_message' => $attempts_message,
                'show_progress' => $track_progress && $show_progress,
                'course_features' => $course_features,
                'is_preview' => $is_preview,
                'urls' => [
                    'courses' => get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'),
                    'login' => PublicPageUrls::login(get_permalink($post) ?: ''),
                    'course' => $course_post ? (get_permalink($course_post) ?: '') : '',
                    'learn' => PublicPageUrls::learnForCourse($course_id),
                    'account' => PublicPageUrls::url('account'),
                ],
            ],
            $post
        );
    }

    public static function forPost(\WP_Post $post): SingleQuizPageModel
    {
        return QuizPageService::forPost($post);
    }

    private static function canPreviewQuiz(int $course_id, int $quiz_id): bool
    {
        // quizzes are previewable only if explicitly marked free
        return Settings::isTruthy(get_post_meta($quiz_id, '_sikshya_is_free', true));
    }

    /**
     * @return array{reviews: bool, ratings: bool, discussions: bool, qa: bool, certificate: bool}
     */
    private static function courseFeatures(int $course_id): array
    {
        $global_reviews = Settings::isTruthy(Settings::get('enable_reviews', true));
        $global_ratings = Settings::isTruthy(Settings::get('enable_ratings', true));

        // Learn-page tabs should only show when the UI exists.
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

    private static function quizAttemptsCap(int $quiz_id): int
    {
        $a = (int) get_post_meta($quiz_id, '_sikshya_quiz_attempts_allowed', true);
        $b = (int) get_post_meta($quiz_id, '_sikshya_quiz_attempts_limit', true);

        return max($a, $b);
    }

    /**
     * Build one learner-facing question row (types, options, matching/ordering data).
     * Exposes no correct-answer data to the template. Used by the learn quiz and by Pro
     * question-bank runtime so the UI and REST grading stay consistent.
     *
     * @return array<string, mixed>|null
     */
    public static function buildQuestionViewRowForId(int $qid): ?array
    {
        if ($qid <= 0) {
            return null;
        }

        $qp = get_post($qid);
        if (!$qp || $qp->post_type !== PostTypes::QUESTION) {
            return null;
        }

        $type = (string) get_post_meta($qid, '_sikshya_question_type', true);
        if ($type === '') {
            $type = 'multiple_choice';
        }

        $opts = get_post_meta($qid, '_sikshya_question_options', true);
        if (!is_array($opts)) {
            $opts = [];
        }
        $opts = array_values(array_map('strval', $opts));

        $row = [
            'id' => $qid,
            'type' => $type,
            'title' => wp_strip_all_tags((string) $qp->post_title),
            'options' => $opts,
        ];

        if ($type === 'matching') {
            $raw = (string) get_post_meta($qid, '_sikshya_question_correct_answer', true);
            $dec = json_decode($raw, true);
            $left = [];
            $right = [];
            if (is_array($dec) && !empty($dec['matching']) && is_array($dec['matching'])) {
                $m = $dec['matching'];
                if (!empty($m['left']) && is_array($m['left'])) {
                    $left = array_values(array_map('strval', $m['left']));
                }
                if (!empty($m['right']) && is_array($m['right'])) {
                    $right = array_values(array_map('strval', $m['right']));
                }
            }
            $row['matching_left'] = $left;
            $row['matching_right'] = $right;
        }

        if ($type === 'ordering' && $opts !== []) {
            $pairs = [];
            foreach ($opts as $i => $text) {
                $pairs[] = ['index' => (int) $i, 'text' => $text];
            }
            shuffle($pairs);
            $row['ordering_display'] = $pairs;
        }

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function buildQuestionsForQuiz(int $quiz_id): array
    {
        $ids = get_post_meta($quiz_id, '_sikshya_quiz_questions', true);
        if (!is_array($ids)) {
            return [];
        }

        $out = [];

        foreach ($ids as $qid) {
            $row = self::buildQuestionViewRowForId((int) $qid);
            if ($row !== null) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
