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

        // Mid-attempt answer snapshot. Lets the form persist work so a
        // refresh / accidental tab close doesn't wipe progress.
        register_rest_route($namespace, '/me/quiz-save', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'quizSave'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => [
                    'quiz_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'minimum' => 1,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => 'rest_validate_request_arg',
                    ],
                    'attempt_id' => [
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

    /**
     * Save the learner's in-progress answers for an attempt without grading.
     *
     * Fires from the client every ~30s + after each answer change (debounced).
     * Only updates rows that belong to the caller AND are still `in_progress`;
     * silently no-ops if the attempt was already submitted (a late save from a
     * stale tab shouldn't overwrite a finalized record).
     */
    public function quizSave(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        $quiz_id = isset($params['quiz_id']) ? (int) $params['quiz_id'] : 0;
        $attempt_id = isset($params['attempt_id']) ? (int) $params['attempt_id'] : 0;
        $answers = isset($params['answers']) && is_array($params['answers']) ? $params['answers'] : [];

        if ($quiz_id <= 0 || $attempt_id <= 0) {
            return $this->error('invalid_quiz_save', __('Invalid auto-save payload.', 'sikshya'), 400);
        }
        if (get_post_type($quiz_id) !== PostTypes::QUIZ) {
            return $this->error('invalid_quiz', __('Invalid quiz.', 'sikshya'), 400);
        }

        $uid = get_current_user_id();
        $attempt_row = $this->quizAttempts->getAttemptForUser($attempt_id, $uid);
        if (!$attempt_row || (int) $attempt_row->quiz_id !== $quiz_id) {
            return $this->error('attempt_not_found', __('Attempt not found.', 'sikshya'), 404);
        }
        if ((string) $attempt_row->status !== 'in_progress') {
            // Already finalized — treat as a polite no-op, not an error.
            return new WP_REST_Response(['ok' => true, 'data' => ['saved' => false, 'reason' => 'finalized']], 200);
        }

        $payload = wp_json_encode($answers);
        if ($payload === false) {
            return $this->error('encode_failed', __('Could not encode answers.', 'sikshya'), 400);
        }

        $this->quizAttempts->savePartialAnswers($attempt_id, $uid, $payload);

        return new WP_REST_Response(
            [
                'ok' => true,
                'data' => [
                    'saved' => true,
                    'saved_at' => current_time('mysql'),
                    'serverTime' => time(),
                ],
            ],
            200
        );
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
        // Reject submissions against unpublished/trashed quizzes so an admin
        // unpublishing a quiz mid-attempt blocks further grading.
        if (get_post_status($quiz_id) !== 'publish') {
            return $this->error('quiz_unavailable', __('This quiz is no longer available.', 'sikshya'), 400);
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

        // Prime the postmeta cache for every question in this attempt in a
        // single SQL query. Each grading loop below reads 3 meta fields per
        // question (`_sikshya_question_type`, `_sikshya_question_points`,
        // `_sikshya_question_correct_answer`); the second loop reads the
        // same fields again to populate per-question attempt rows. Without
        // priming, that's 6 individual `SELECT meta_value FROM postmeta`
        // queries per question per submit (cold cache) — 60 queries for a
        // 10-question quiz, 600 for a 100-question one. After priming,
        // every `get_post_meta` is an in-memory hash lookup. WP's caches
        // are request-scoped so we only pay the SELECT once per submit.
        $primable_qids = array_values(array_filter(array_map('intval', $question_ids), static fn (int $q): bool => $q > 0));
        if ($primable_qids !== []) {
            update_meta_cache('post', $primable_qids);
        }

        // Per-question result map for the post-submit breakdown. Captured
        // during the grading loop so we don't re-evaluate downstream.
        $per_question_results = [];
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

            $per_question_results[(string) $qid] = [
                'type' => $type,
                // Essay isn't auto-graded, so `correct` is meaningless there.
                'correct' => $type !== 'essay' ? (bool) $eval['correct'] : null,
                'earned' => $earned,
                'possible' => $points,
            ];

            $stored_answers[$qid] = is_string($raw_answer) ? $raw_answer : wp_json_encode($raw_answer);
        }

        $score_percent = $total_points > 0 ? round($earned_points / $total_points * 100, 2) : 0.0;
        $passing = (float) get_post_meta($quiz_id, '_sikshya_quiz_passing_score', true);
        if ($passing <= 0) {
            $passing = 70.0;
        }

        $passed = $score_percent >= $passing;

        // ── Server-side time-limit enforcement ─────────────────────────────
        // The client renders a countdown timer, but a malicious or curious
        // learner can stop it (devtools, throttled tab, refresh) and submit
        // hours later. Trust the server: compute elapsed seconds from the
        // attempt's `started_at`, compare against the quiz's configured
        // limit, and override the client-supplied `time_taken` + force
        // failure when overtime is real. A 30-second grace window absorbs
        // network jitter and clock skew.
        $time_limit_minutes = (int) get_post_meta($quiz_id, '_sikshya_quiz_time_limit', true);
        $server_time_taken = isset($params['time_taken']) ? (int) $params['time_taken'] : 0;
        $time_expired = false;
        if ($time_limit_minutes > 0 && $attempt_row && !empty($attempt_row->started_at)) {
            $started_ts = strtotime((string) $attempt_row->started_at);
            if ($started_ts > 0) {
                $elapsed = max(0, time() - $started_ts);
                $server_time_taken = $elapsed;
                if ($elapsed > $time_limit_minutes * 60 + 30) {
                    $time_expired = true;
                    $passed = false;
                }
            }
        }

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
                        // Server-measured elapsed seconds, not client-supplied.
                        'time_taken' => $server_time_taken,
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
                    // No prior started_at exists for this synthetic attempt
                    // (no `attempt/start` call was made), so honour the
                    // server-elapsed if we computed one, else fall back to
                    // the client-claimed value capped at the time limit.
                    'time_taken' => $server_time_taken,
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

        // Per-question explanations for the result view. Only included for
        // questions the learner actually saw (the resolved grading list), so
        // we never leak hints for skipped/unrendered questions.
        $per_question_explanations = [];
        foreach ($question_ids as $qid) {
            if ($qid <= 0) {
                continue;
            }
            $exp = (string) get_post_meta($qid, '_sikshya_question_explanation', true);
            if ($exp === '') {
                // Back-compat: older builds stored the explanation in post_content.
                $post = get_post($qid);
                if ($post && trim((string) $post->post_content) !== '') {
                    $exp = (string) $post->post_content;
                }
            }
            if ($exp !== '') {
                $per_question_explanations[(string) $qid] = sikshya_render_rich_text($exp);
            }
        }

        return new WP_REST_Response(
            [
                'ok' => true,
                'data' => [
                    'attempt_id' => $attempt_id,
                    'score_percent' => $score_percent,
                    'passing_score' => $passing,
                    'passed' => $passed,
                    'status' => $attempt_status,
                    // True when the server detected the learner blew past the
                    // configured time limit. The UI can surface "Time expired"
                    // alongside the (now necessarily failing) result.
                    'time_expired' => $time_expired,
                    'time_taken' => $server_time_taken,
                    'per_question_explanations' => (object) $per_question_explanations,
                    // Per-question grading detail powers the "Score breakdown
                    // by question type" panel in the results card. Cast to an
                    // object so an empty list serializes as `{}` (not `[]`).
                    'per_question_results' => (object) $per_question_results,
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
        if (get_post_status($quiz_id) !== 'publish') {
            return $this->error('quiz_unavailable', __('This quiz is no longer available.', 'sikshya'), 400);
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

        $auto_save_data = null;
        if ($row && !empty($row->auto_save_data)) {
            $decoded = json_decode((string) $row->auto_save_data, true);
            if (is_array($decoded)) {
                $auto_save_data = $decoded;
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
                            'auto_save_data' => $auto_save_data,
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
        if (get_post_status($quiz_id) !== 'publish') {
            return $this->error('quiz_unavailable', __('This quiz is no longer available.', 'sikshya'), 400);
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

        // Enforce the attempt cap before creating a new row so a learner can't
        // open a stale UI and accumulate attempts they can never submit.
        $attempted = $this->quizAttempts->countAttemptsForUserQuiz($uid, $quiz_id);
        $max = $this->getMaxQuizAttempts($quiz_id);
        if ($max > 0 && $attempted >= $max) {
            return $this->error('attempts_exhausted', __('No quiz attempts remaining.', 'sikshya'), 400);
        }
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
     * Thin delegating shim. Real grading logic lives in {@see \Sikshya\Services\QuizGrader}
     * so this class and {@see \Sikshya\Services\QuizService} share a single implementation.
     *
     * @param mixed $answer
     * @return array{correct: bool}
     */
    private function evaluateAnswer(string $type, string $correct, $answer): array
    {
        return \Sikshya\Services\QuizGrader::evaluate($type, $correct, $answer);
    }

    /**
     * Thin delegating shim. See {@see \Sikshya\Services\QuizGrader::normalizeScalarAnswer()}.
     *
     * @param mixed $answer
     */
    private function normalizeScalarAnswer($answer): string
    {
        return \Sikshya\Services\QuizGrader::normalizeScalarAnswer($answer);
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
