<?php

declare(strict_types=1);

namespace Sikshya\Api\Learner;

use Sikshya\Constants\PostTypes;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\QuizAttemptRepository;
use Sikshya\Services\LessonCourseLink;
use Sikshya\Services\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Learner quiz routes — start attempt, view current attempt, submit answers.
 *
 * Extracted from {@see \Sikshya\Api\LearnerRestRoutes} as the third domain. Owns
 * `/sikshya/v1/me/quiz-attempt` (GET + POST) and `/sikshya/v1/me/quiz-submit`. The grading
 * helpers ({@see self::resolveGradingQuestionIds()}, {@see self::evaluateAnswer()}) live here
 * because they're not shared by any other learner controller.
 *
 * Route paths, response shapes, and grading rules are preserved 1:1 with the original
 * implementation — external clients see no change.
 *
 * @package Sikshya\Api\Learner
 */
final class QuizRoutes extends AbstractLearnerRestController
{
    private QuizAttemptRepository $quizAttempts;

    public function __construct(Plugin $plugin)
    {
        parent::__construct($plugin);

        $this->quizAttempts = new QuizAttemptRepository();
    }

    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/me/quiz-submit', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'quizSubmit'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => [
                    'quiz_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'minimum' => 1,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => 'rest_validate_request_arg',
                    ],
                    // `answers` payload is free-form (question_id => answer) so we don't enforce a
                    // schema here — the callback walks the structure with its own type checks.
                    'attempt_id' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => 'rest_validate_request_arg',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/me/quiz-attempt', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getMyQuizAttempt'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => [
                    'quiz_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'minimum' => 1,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => 'rest_validate_request_arg',
                    ],
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'startMyQuizAttempt'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => [
                    'quiz_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'minimum' => 1,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => 'rest_validate_request_arg',
                    ],
                ],
            ],
        ]);
    }

    public function quizSubmit(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        $quiz_id = isset($params['quiz_id']) ? (int) $params['quiz_id'] : 0;
        $answers = isset($params['answers']) && is_array($params['answers']) ? $params['answers'] : [];

        if ($quiz_id <= 0) {
            return $this->error('invalid_quiz', __('Invalid quiz.', 'sikshya'), 400);
        }

        if (get_post_type($quiz_id) !== PostTypes::QUIZ) {
            return $this->error('invalid_quiz', __('Invalid quiz.', 'sikshya'), 400);
        }

        $uid = get_current_user_id();
        $course_id = LessonCourseLink::resolvedCourseIdForQuiz($quiz_id);
        if ($course_id <= 0) {
            return $this->error('quiz_no_course', __('Quiz is not linked to a course.', 'sikshya'), 400);
        }

        $courseService = $this->getCourseService();
        if (!$courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        $provided_attempt_id = isset($params['attempt_id']) ? (int) $params['attempt_id'] : 0;
        $attempt_row = null;
        if ($provided_attempt_id > 0) {
            $attempt_row = $this->quizAttempts->getAttemptForUser($provided_attempt_id, $uid);
            // If the learner is finishing an already-started attempt, allow submission even when the max is reached.
            if ($attempt_row && (int) $attempt_row->quiz_id !== $quiz_id) {
                $attempt_row = null;
            }
            if ($attempt_row && (string) $attempt_row->status !== 'in_progress') {
                $attempt_row = null;
            }
        }

        $max = $this->getMaxQuizAttempts($quiz_id);
        $attempted = $this->quizAttempts->countAttemptsForUserQuiz($uid, $quiz_id);
        if (!$attempt_row && $max > 0 && $attempted >= $max) {
            return $this->error('attempts_exhausted', __('No quiz attempts remaining.', 'sikshya'), 400);
        }

        $meta_ids = get_post_meta($quiz_id, '_sikshya_quiz_questions', true);
        if (!is_array($meta_ids)) {
            $meta_ids = [];
        }
        $meta_ids = array_map('intval', $meta_ids);

        $raw_client = isset($params['question_ids']) && is_array($params['question_ids']) ? $params['question_ids'] : [];
        $question_ids = $this->resolveGradingQuestionIds($quiz_id, $meta_ids, $raw_client);
        if ($question_ids === null) {
            return $this->error(
                'invalid_questions',
                __('This quiz could not be graded. Please reload the page and try again.', 'sikshya'),
                400
            );
        }
        if ($question_ids === []) {
            return $this->error('no_questions', __('This quiz has no questions.', 'sikshya'), 400);
        }

        $total_points = 0.0;
        $earned_points = 0.0;
        $correct_count = 0;
        $stored_answers = [];

        foreach ($question_ids as $qid) {
            if ($qid <= 0) {
                continue;
            }
            $type = (string) get_post_meta($qid, '_sikshya_question_type', true);
            $points = (float) get_post_meta($qid, '_sikshya_question_points', true);
            if ($points < 0) {
                $points = 0;
            }
            $correct = (string) get_post_meta($qid, '_sikshya_question_correct_answer', true);
            $raw_answer = $answers[(string) $qid] ?? $answers[$qid] ?? '';

            $total_points += $points;
            $eval = $this->evaluateAnswer($type, $correct, $raw_answer);
            $earned = ($eval['correct'] && $type !== 'essay') ? $points : 0.0;
            if ($type !== 'essay') {
                $earned_points += $earned;
                if ($eval['correct']) {
                    ++$correct_count;
                }
            }

            $stored_answers[$qid] = is_string($raw_answer) ? $raw_answer : wp_json_encode($raw_answer);
        }

        $score_percent = $total_points > 0 ? round($earned_points / $total_points * 100, 2) : 0.0;
        $passing = (float) get_post_meta($quiz_id, '_sikshya_quiz_passing_score', true);
        if ($passing <= 0) {
            $passing = 70.0;
        }

        $passed = $score_percent >= $passing;

        $attempt_status = $passed ? 'passed' : 'completed';
        $attempt_id = 0;
        if ($provided_attempt_id > 0) {
            if ($attempt_row && (int) $attempt_row->quiz_id === $quiz_id && (string) $attempt_row->status === 'in_progress') {
                $attempt_id = (int) $attempt_row->id;
                $this->quizAttempts->updateAttempt(
                    $attempt_id,
                    $uid,
                    [
                        'score' => $score_percent,
                        'total_questions' => count($question_ids),
                        'correct_answers' => $correct_count,
                        'time_taken' => isset($params['time_taken']) ? (int) $params['time_taken'] : 0,
                        'status' => $attempt_status,
                        'completed_at' => current_time('mysql'),
                        'answers_data' => wp_json_encode($stored_answers),
                    ]
                );
            }
        }
        if ($attempt_id <= 0) {
            $attempt_no = $attempted + 1;
            $attempt_id = $this->quizAttempts->createAttempt(
                [
                    'user_id' => $uid,
                    'quiz_id' => $quiz_id,
                    'course_id' => $course_id,
                    'attempt_number' => $attempt_no,
                    'score' => $score_percent,
                    'total_questions' => count($question_ids),
                    'correct_answers' => $correct_count,
                    'time_taken' => isset($params['time_taken']) ? (int) $params['time_taken'] : 0,
                    'status' => $attempt_status,
                    'started_at' => current_time('mysql'),
                    'completed_at' => current_time('mysql'),
                    'answers_data' => $stored_answers,
                ]
            );
        }

        foreach ($question_ids as $qid) {
            if ($qid <= 0) {
                continue;
            }
            $type = (string) get_post_meta($qid, '_sikshya_question_type', true);
            $points = (float) get_post_meta($qid, '_sikshya_question_points', true);
            $correct = (string) get_post_meta($qid, '_sikshya_question_correct_answer', true);
            $raw_answer = $answers[(string) $qid] ?? $answers[$qid] ?? '';
            $eval = $this->evaluateAnswer($type, $correct, $raw_answer);
            $earned = ($type !== 'essay' && $eval['correct']) ? $points : 0.0;
            $ans_str = is_string($raw_answer) ? $raw_answer : wp_json_encode($raw_answer);
            $this->quizAttempts->addItem($attempt_id, $qid, $ans_str, (bool) $eval['correct'], $earned);
        }

        // A quiz is "completed" when it is submitted (pass/fail is separate).
        $this->progress->markQuizComplete($uid, $course_id, $quiz_id, $score_percent);
        $this->syncEnrollmentProgress($uid, $course_id);

        return new WP_REST_Response(
            [
                'ok' => true,
                'data' => [
                    'attempt_id' => $attempt_id,
                    'score_percent' => $score_percent,
                    'passing_score' => $passing,
                    'passed' => $passed,
                    'status' => $attempt_status,
                ],
            ],
            200
        );
    }

    public function getMyQuizAttempt(WP_REST_Request $request): WP_REST_Response
    {
        $quiz_id = (int) $request->get_param('quiz_id');
        if ($quiz_id <= 0 || get_post_type($quiz_id) !== PostTypes::QUIZ) {
            return $this->error('invalid_quiz', __('Invalid quiz.', 'sikshya'), 400);
        }

        $uid = get_current_user_id();
        $course_id = LessonCourseLink::resolvedCourseIdForQuiz($quiz_id);
        if ($course_id <= 0) {
            return $this->error('quiz_no_course', __('Quiz is not linked to a course.', 'sikshya'), 400);
        }
        $courseService = $this->getCourseService();
        if (!$courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        $row = $this->quizAttempts->getLatestInProgressAttemptForUserQuiz($uid, $quiz_id);

        $duration_mins = (new \Sikshya\Models\Quiz())->getDuration($quiz_id);
        $duration_seconds = $duration_mins > 0 ? $duration_mins * 60 : 0;
        $started_at_ts = null;
        if ($row && isset($row->started_at)) {
            try {
                $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $row->started_at, wp_timezone());
                if ($dt instanceof \DateTimeImmutable) {
                    $started_at_ts = $dt->getTimestamp();
                }
            } catch (\Throwable $e) {
                $started_at_ts = null;
            }
        }

        return new WP_REST_Response(
            [
                'ok' => true,
                'data' => [
                    'attempt' => $row
                        ? [
                            'id' => (int) $row->id,
                            'started_at' => (string) $row->started_at,
                            'started_at_ts' => $started_at_ts,
                            'status' => (string) $row->status,
                            'attempt_number' => (int) $row->attempt_number,
                        ]
                        : null,
                    'durationSeconds' => $duration_seconds,
                    'serverTime' => time(),
                ],
            ],
            200
        );
    }

    public function startMyQuizAttempt(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        $quiz_id = isset($params['quiz_id']) ? (int) $params['quiz_id'] : 0;
        if ($quiz_id <= 0 || get_post_type($quiz_id) !== PostTypes::QUIZ) {
            return $this->error('invalid_quiz', __('Invalid quiz.', 'sikshya'), 400);
        }

        $uid = get_current_user_id();
        $course_id = LessonCourseLink::resolvedCourseIdForQuiz($quiz_id);
        if ($course_id <= 0) {
            return $this->error('quiz_no_course', __('Quiz is not linked to a course.', 'sikshya'), 400);
        }
        $courseService = $this->getCourseService();
        if (!$courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        // Reuse existing in-progress attempt if present.
        $existing = $this->quizAttempts->getLatestInProgressAttemptForUserQuiz($uid, $quiz_id);
        if ($existing) {
            return new WP_REST_Response(
                [
                    'ok' => true,
                    'data' => [
                        'attempt_id' => (int) $existing->id,
                        'started_at' => (string) $existing->started_at,
                        'serverTime' => time(),
                    ],
                ],
                200
            );
        }

        $attempted = $this->quizAttempts->countAttemptsForUserQuiz($uid, $quiz_id);
        $attempt_id = $this->quizAttempts->createAttempt(
            [
                'user_id' => $uid,
                'quiz_id' => $quiz_id,
                'course_id' => $course_id,
                'attempt_number' => $attempted + 1,
                'status' => 'in_progress',
                'started_at' => current_time('mysql'),
                'completed_at' => null,
                'answers_data' => null,
            ]
        );

        $row = $this->quizAttempts->getAttemptForUser($attempt_id, $uid);
        $started_at_ts = null;
        if ($row && isset($row->started_at)) {
            try {
                $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $row->started_at, wp_timezone());
                if ($dt instanceof \DateTimeImmutable) {
                    $started_at_ts = $dt->getTimestamp();
                }
            } catch (\Throwable $e) {
                $started_at_ts = null;
            }
        }

        return new WP_REST_Response(
            [
                'ok' => true,
                'data' => [
                    'attempt_id' => $attempt_id,
                    'started_at' => $row ? (string) $row->started_at : current_time('mysql'),
                    'started_at_ts' => $started_at_ts,
                    'serverTime' => time(),
                ],
            ],
            200
        );
    }

    /**
     * Chooses which question posts to grade. The learn UI may show a Pro "question bank"
     * draw; the client then sends the displayed ids in `question_ids` and Pro extends the
     * allow-list. When no client list is sent, the quiz's saved `_sikshya_quiz_questions`
     * list is used (backwards compatible).
     *
     * @param int[] $meta_ids   Question ids stored on the quiz.
     * @param mixed $raw_client `question_ids` from the JSON body.
     * @return int[]|null      Resolved ids, or null if the client list is not allowed.
     */
    private function resolveGradingQuestionIds(int $quiz_id, array $meta_ids, $raw_client): ?array
    {
        if (!is_array($raw_client)) {
            $raw_client = [];
        }
        $client_ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', $raw_client),
                    static function (int $i): bool {
                        return $i > 0;
                    }
                )
            )
        );

        $require_client = (bool) apply_filters('sikshya_quiz_require_client_question_ids', false, $quiz_id);
        if ($require_client && $client_ids === []) {
            return null;
        }

        if ($client_ids === []) {
            return array_values(
                array_filter(
                    array_map('absint', $meta_ids),
                    static function (int $i): bool {
                        return $i > 0;
                    }
                )
            );
        }

        $allowed = apply_filters('sikshya_quiz_allowed_qids', $meta_ids, $quiz_id);
        if (!is_array($allowed) || $allowed === []) {
            $allowed = $meta_ids;
        }
        $allowed = array_map('absint', $allowed);
        $allowed = array_values(array_unique(array_filter($allowed, static fn (int $i) => $i > 0)));

        foreach ($client_ids as $q) {
            if (!in_array($q, $allowed, true)) {
                return null;
            }
        }

        return $client_ids;
    }

    /**
     * @return array{correct: bool}
     */
    private function evaluateAnswer(string $type, string $correct, mixed $answer): array
    {
        $c = trim($correct);

        if ($type === 'essay') {
            return ['correct' => false];
        }

        if ($type === 'multiple_choice' || $type === 'true_false') {
            $a = $this->normalizeScalarAnswer($answer);

            return ['correct' => strcasecmp($a, $c) === 0];
        }

        if ($type === 'short_answer' || $type === 'fill_blank') {
            $u = is_string($answer) ? trim($answer) : '';
            if ($u === '') {
                return ['correct' => false];
            }
            $opts = array_map('trim', explode('|', $c));
            $ul = strtolower($u);
            foreach ($opts as $o) {
                if ($o !== '' && strtolower($o) === $ul) {
                    return ['correct' => true];
                }
            }

            return ['correct' => false];
        }

        if ($type === 'multiple_response') {
            $exp = json_decode($c, true);
            if (!is_array($exp)) {
                return ['correct' => false];
            }
            $got = is_string($answer) ? json_decode($answer, true) : $answer;
            if (!is_array($got)) {
                return ['correct' => false];
            }
            $e = array_map('intval', $exp);
            $g = array_map('intval', $got);
            sort($e);
            sort($g);

            return ['correct' => $e === $g];
        }

        if ($type === 'ordering') {
            $exp = json_decode($c, true);
            if (!is_array($exp)) {
                return ['correct' => false];
            }
            $got = is_string($answer) ? json_decode($answer, true) : $answer;
            if (!is_array($got)) {
                return ['correct' => false];
            }
            $e = array_map('intval', $exp);
            $g = array_map('intval', $got);

            return ['correct' => $e === $g];
        }

        if ($type === 'matching') {
            $dec = json_decode($c, true);
            if (!is_array($dec) || empty($dec['matching']) || !is_array($dec['matching'])) {
                return ['correct' => false];
            }
            $exp_map = $dec['matching']['map'] ?? null;
            if (!is_array($exp_map)) {
                return ['correct' => false];
            }
            $exp_map = array_map('intval', $exp_map);
            $got = is_string($answer) ? json_decode($answer, true) : $answer;
            if (!is_array($got) || empty($got['map']) || !is_array($got['map'])) {
                return ['correct' => false];
            }
            $gmap = array_map('intval', $got['map']);

            return ['correct' => $exp_map === $gmap];
        }

        return ['correct' => false];
    }

    /**
     * Normalize learner answer for choice / true-false (JSON may send int/bool).
     */
    private function normalizeScalarAnswer(mixed $answer): string
    {
        if (is_string($answer)) {
            return trim($answer);
        }
        if (is_bool($answer)) {
            return $answer ? 'true' : 'false';
        }
        if (is_int($answer) || is_float($answer)) {
            return (string) (int) $answer;
        }

        return '';
    }

    private function getMaxQuizAttempts(int $quiz_id): int
    {
        $a = (int) get_post_meta($quiz_id, '_sikshya_quiz_attempts_allowed', true);
        $b = (int) get_post_meta($quiz_id, '_sikshya_quiz_attempts_limit', true);
        $m = max($a, $b);

        if ($m > 0) {
            return $m;
        }

        // Fall back to the global default (0 means unlimited).
        $global = (int) Settings::get('quiz_attempts_limit', 1);
        if ($global < 0) {
            $global = 0;
        }

        return $global > 0 ? $global : 0;
    }
}
