<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Database\Repositories\ProgressRepository;
use Sikshya\Services\PublicCurriculumService;

/**
 * View-model builder for the course learn / curriculum page (presentation layer).
 *
 * Data is assembled here (controller/presenter); templates stay markup-only.
 *
 * @package Sikshya\Frontend\Public
 */
final class LearnTemplateData
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(): array
    {
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        $uid       = get_current_user_id();
        $error     = '';
        $blocks    = [];
        $enrolled  = false;

        if ($uid <= 0) {
            $error = __('Please log in to access your learning.', 'sikshya');
        } elseif ($course_id <= 0) {
            $error = __('Choose a course from your account to continue.', 'sikshya');
        } else {
            $repo = new EnrollmentRepository();
            $enrolled = $repo->findByUserAndCourse($uid, $course_id) !== null;
            if (! $enrolled) {
                $error = __('You are not enrolled in this course.', 'sikshya');
            } else {
                $raw = PublicCurriculumService::getCourseCurriculum($course_id);
                $blocks = self::enrichBlocks($uid, $course_id, $raw);
            }
        }

        $course_post = $course_id > 0 ? get_post($course_id) : null;
        $stats       = self::computeStats($blocks);

        $vm = [
            'course_id'   => $course_id,
            'course'      => $course_post,
            'enrolled'    => $enrolled,
            'curriculum'  => $blocks,
            'blocks'      => $blocks,
            'stats'       => $stats,
            'error'       => $error,
            'course_thumb'=> $course_post ? get_the_post_thumbnail_url($course_post->ID, 'large') : '',
            'urls'        => [
                'account' => PublicPageUrls::url('account'),
                'course'  => $course_post ? (get_permalink($course_post) ?: '') : '',
                'learn'   => PublicPageUrls::learnForCourse($course_id),
            ],
        ];

        return apply_filters('sikshya_learn_template_data', $vm);
    }

    /**
     * @param array<int, array{chapter: \WP_Post, contents: array<int, \WP_Post>}> $raw
     * @return array<int, array{chapter: \WP_Post, items: array<int, array<string, mixed>>}>
     */
    private static function enrichBlocks(int $user_id, int $course_id, array $raw): array
    {
        $progress = new ProgressRepository();
        $out      = [];

        foreach ($raw as $row) {
            $chapter = $row['chapter'];
            $items   = [];

            foreach ($row['contents'] as $p) {
                if (! $p instanceof \WP_Post) {
                    continue;
                }

                $type_key   = self::contentTypeKey($p->post_type);
                $pto        = get_post_type_object($p->post_type);
                $type_label = $pto && isset($pto->labels->singular_name)
                    ? (string) $pto->labels->singular_name
                    : $p->post_type;

                $items[] = [
                    'post'        => $p,
                    'permalink'   => get_permalink($p) ?: '',
                    'title'       => get_the_title($p),
                    'type_key'    => $type_key,
                    'type_label'  => $type_label,
                    'completed'   => self::isItemCompleted($progress, $user_id, $course_id, $p),
                ];
            }

            $out[] = [
                'chapter' => $chapter,
                'items'   => $items,
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
}
