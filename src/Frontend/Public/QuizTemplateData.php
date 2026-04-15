<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Database\Repositories\ProgressRepository;
use Sikshya\Database\Repositories\QuizAttemptRepository;
use Sikshya\Frontend\Public\PublicPageUrls;
use Sikshya\Services\PublicCurriculumService;
use Sikshya\Frontend\Public\SampleCatalog;

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
    public static function forPost(\WP_Post $post): array
    {
        $quiz_id = (int) $post->ID;
        $course_id = (int) get_post_meta($quiz_id, '_sikshya_quiz_course', true);
        $uid = get_current_user_id();

        $error = '';
        $enrolled = false;
        $blocks = [];
        if ($uid <= 0) {
            $error = __('Please log in to access your learning.', 'sikshya');
        } elseif ($course_id <= 0) {
            $error = __('This quiz is not linked to a course.', 'sikshya');
        } else {
            $repo     = new EnrollmentRepository();
            $enrolled = $repo->findByUserAndCourse($uid, $course_id) !== null;
            if (!$enrolled) {
                $error = __('You are not enrolled in this course.', 'sikshya');
            } else {
                $raw    = PublicCurriculumService::getCourseCurriculum($course_id);
                $blocks = self::enrichBlocks($uid, $course_id, $raw, $quiz_id);
            }
        }

        $attempts_used = 0;
        $attempts_max = self::quizAttemptsCap($quiz_id);
        if ($uid > 0 && $quiz_id > 0) {
            $attempts_used = (new QuizAttemptRepository())->countAttemptsForUserQuiz($uid, $quiz_id);
        }

        $course_post = $course_id > 0 ? get_post($course_id) : null;
        $stats       = self::computeStats($blocks);
        $nav         = self::computePrevNext($blocks, $quiz_id);
        $current_chapter = self::currentChapterFor($blocks);
        $questions = self::buildQuestionsForQuiz($quiz_id);
        $sample_course = $course_post ? SampleCatalog::findCourseByTitle((string) get_the_title($course_post)) : null;
        $sample_item = $sample_course ? SampleCatalog::findContentByTitleInCourse($sample_course, (string) get_the_title($post)) : null;
        $mock_ui = SampleCatalog::mockUiMeta(($course_post ? (string) get_the_title($course_post) : '') . '|' . (string) get_the_title($post));

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
                'sample_course' => $sample_course,
                'sample_item' => $sample_item,
                'mock_ui' => $mock_ui,
                'questions' => $questions,
                'attempts_used' => $attempts_used,
                'attempts_max' => $attempts_max,
                'attempts_limited' => $attempts_max > 0,
                'attempts_remaining' => $attempts_max > 0 ? max(0, $attempts_max - $attempts_used) : null,
                'urls' => [
                    'courses' => get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'),
                    'login' => wp_login_url(get_permalink($post) ?: ''),
                    'course' => $course_post ? (get_permalink($course_post) ?: '') : '',
                    'learn' => PublicPageUrls::learnForCourse($course_id),
                    'account' => PublicPageUrls::url('account'),
                ],
            ],
            $post
        );
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
    private static function enrichBlocks(int $user_id, int $course_id, array $raw, int $current_post_id): array
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
                    'completed' => self::isItemCompleted($progress, $user_id, $course_id, $p),
                    'current' => $is_current,
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
                'items' => $items,
                'item_count' => count($items),
                'completed_in_section' => $completed_in_section,
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
            $slug = $p->post_name ?: sanitize_title((string) $p->post_title);
            return PublicPageUrls::learnContent($type_key, $slug);
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
            $qid = (int) $qid;
            if ($qid <= 0) {
                continue;
            }

            $qp = get_post($qid);
            if (!$qp || $qp->post_type !== PostTypes::QUESTION) {
                continue;
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

            $out[] = $row;
        }

        return $out;
    }
}
