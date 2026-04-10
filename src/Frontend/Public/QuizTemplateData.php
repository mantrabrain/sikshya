<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Database\Repositories\QuizAttemptRepository;

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

        $enrolled = false;
        if ($uid > 0 && $course_id > 0) {
            $repo = new EnrollmentRepository();
            $enrolled = $repo->findByUserAndCourse($uid, $course_id) !== null;
        }

        $attempts_used = 0;
        $attempts_max = self::quizAttemptsCap($quiz_id);
        if ($uid > 0 && $quiz_id > 0) {
            $attempts_used = (new QuizAttemptRepository())->countAttemptsForUserQuiz($uid, $quiz_id);
        }

        $questions = self::buildQuestionsForQuiz($quiz_id);

        return apply_filters(
            'sikshya_quiz_template_data',
            [
                'post' => $post,
                'course_id' => $course_id,
                'logged_in' => $uid > 0,
                'enrolled' => $enrolled,
                'questions' => $questions,
                'attempts_used' => $attempts_used,
                'attempts_max' => $attempts_max,
                'attempts_limited' => $attempts_max > 0,
                'attempts_remaining' => $attempts_max > 0 ? max(0, $attempts_max - $attempts_used) : null,
                'urls' => [
                    'courses' => get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'),
                    'login' => wp_login_url(get_permalink($post) ?: ''),
                ],
            ],
            $post
        );
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
