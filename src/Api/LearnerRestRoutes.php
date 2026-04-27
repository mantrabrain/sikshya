<?php

namespace Sikshya\Api;

use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Database\Repositories\ProgressRepository;
use Sikshya\Database\Repositories\QuizAttemptRepository;
use Sikshya\Constants\PostTypes;
use Sikshya\Addons\Addons;
use Sikshya\Services\CertificateIssuanceService;
use Sikshya\Services\CourseCompletionEvaluator;
use Sikshya\Services\CourseService;
use Sikshya\Services\LearnerCurriculumHelper;
use Sikshya\Services\AssignmentService;
use Sikshya\Services\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logged-in learner: progress, lesson completion, quiz attempts.
 *
 * @package Sikshya\Api
 */
class LearnerRestRoutes
{
    private Plugin $plugin;

    private ProgressRepository $progress;

    private EnrollmentRepository $enrollment;

    private QuizAttemptRepository $quizAttempts;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->progress = new ProgressRepository();
        $this->enrollment = new EnrollmentRepository();
        $this->quizAttempts = new QuizAttemptRepository();
    }

    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/me/progress', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getMyProgress'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => [
                    'course_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/me/lesson-complete', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'lessonComplete'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
            ],
        ]);

        register_rest_route($namespace, '/me/quiz-submit', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'quizSubmit'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
            ],
        ]);

        register_rest_route($namespace, '/me/unenroll', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'unenroll'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
            ],
        ]);

        register_rest_route($namespace, '/me/assignments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getMyAssignments'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => [
                    'course_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/me/assignment-submit', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submitAssignment'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
            ],
        ]);

        register_rest_route($namespace, '/me/assignment-feedback', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getMyAssignmentFeedback'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => [
                    'assignment_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        /**
         * Allow enabled add-ons to register learner REST routes.
         *
         * @param string            $namespace
         * @param LearnerRestRoutes $routes
         */
        do_action('sikshya_register_addon_learner_rest_routes', $namespace, $this);
    }

    /**
     * @return bool|\WP_Error
     */
    public function requireLoginOrJwt(WP_REST_Request $request)
    {
        $public = new PublicRestRoutes($this->plugin);

        return $public->requireLoginOrJwt($request);
    }

    public function getMyProgress(WP_REST_Request $request): WP_REST_Response
    {
        $uid = get_current_user_id();
        $course_id = (int) $request->get_param('course_id');

        $courseService = $this->getCourseService();
        if (!$courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        $lessons = LearnerCurriculumHelper::lessonIdsForCourse($course_id);
        $total = count($lessons);
        $completed = $this->progress->countCompletedLessons($uid, $course_id);
        $pct = $total > 0 ? round(100 * $completed / $total, 2) : 0.0;

        $rows = $this->progress->getCourseProgress($uid, $course_id);

        return new WP_REST_Response(
            [
                'ok' => true,
                'data' => [
                    'course_id' => $course_id,
                    'lesson_total' => $total,
                    'lessons_completed' => $completed,
                    'progress_percent' => $pct,
                    'rows' => array_map([$this, 'mapProgressRow'], $rows),
                ],
            ],
            200
        );
    }

    public function lessonComplete(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        $course_id = isset($params['course_id']) ? (int) $params['course_id'] : 0;
        $lesson_id = isset($params['lesson_id']) ? (int) $params['lesson_id'] : 0;

        if ($course_id <= 0 || $lesson_id <= 0) {
            return $this->error('invalid_params', __('Invalid course or lesson.', 'sikshya'), 400);
        }

        $uid = get_current_user_id();
        $courseService = $this->getCourseService();
        if (!$courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        if (get_post_type($lesson_id) !== PostTypes::LESSON) {
            return $this->error('invalid_lesson', __('Invalid lesson.', 'sikshya'), 400);
        }

        $allowed = LearnerCurriculumHelper::lessonIdsForCourse($course_id);
        if (!in_array($lesson_id, $allowed, true)) {
            return $this->error('lesson_not_in_course', __('Lesson is not part of this course.', 'sikshya'), 400);
        }

        /**
         * Allow Pro modules (drip, prerequisites) to block lesson completion.
         */
        $can_complete = apply_filters('sikshya_can_complete_lesson', true, $uid, $course_id, $lesson_id);
        if (!$can_complete) {
            return $this->error('lesson_locked', __('This lesson is not available yet.', 'sikshya'), 403);
        }

        $this->progress->markLessonComplete($uid, $course_id, $lesson_id);
        $this->syncEnrollmentProgress($uid, $course_id);

        return new WP_REST_Response(['ok' => true, 'message' => __('Lesson marked complete.', 'sikshya')], 200);
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
        $course_id = (int) get_post_meta($quiz_id, '_sikshya_quiz_course', true);
        if ($course_id <= 0) {
            return $this->error('quiz_no_course', __('Quiz is not linked to a course.', 'sikshya'), 400);
        }

        $courseService = $this->getCourseService();
        if (!$courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        $max = $this->getMaxQuizAttempts($quiz_id);
        $attempted = $this->quizAttempts->countAttemptsForUserQuiz($uid, $quiz_id);
        if ($max > 0 && $attempted >= $max) {
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
                'status' => 'completed',
                'answers_data' => $stored_answers,
            ]
        );

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

        if ($passed) {
            $this->progress->markQuizComplete($uid, $course_id, $quiz_id, $score_percent);
            $this->syncEnrollmentProgress($uid, $course_id);
        }

        return new WP_REST_Response(
            [
                'ok' => true,
                'data' => [
                    'attempt_id' => $attempt_id,
                    'score_percent' => $score_percent,
                    'passing_score' => $passing,
                    'passed' => $passed,
                ],
            ],
            200
        );
    }

    public function unenroll(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        $course_id = isset($params['course_id']) ? (int) $params['course_id'] : 0;
        if ($course_id <= 0) {
            return $this->error('invalid_course', __('Invalid course.', 'sikshya'), 400);
        }

        try {
            $this->getCourseService()->unenrollUser(get_current_user_id(), $course_id);
        } catch (\InvalidArgumentException $e) {
            return $this->error('unenroll_failed', $e->getMessage(), 400);
        }

        return new WP_REST_Response(['ok' => true, 'message' => __('Unenrolled.', 'sikshya')], 200);
    }

    public function getMyAssignments(WP_REST_Request $request): WP_REST_Response
    {
        $uid = get_current_user_id();
        $course_id = (int) $request->get_param('course_id');

        $courseService = $this->getCourseService();
        if (!$courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        $svc = $this->assignmentService();
        $rows = $svc->getUserAssignments($course_id, $uid);

        return new WP_REST_Response(['ok' => true, 'data' => ['assignments' => $rows]], 200);
    }

    public function submitAssignment(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        $assignment_id = (int) $request->get_param('assignment_id');
        if ($assignment_id <= 0 && is_array($params)) {
            $assignment_id = (int) ($params['assignment_id'] ?? 0);
        }

        $contentRaw = $request->get_param('content');
        if ($contentRaw === null && is_array($params)) {
            $content = (string) ($params['content'] ?? '');
        } else {
            $content = is_string($contentRaw) ? $contentRaw : (string) $contentRaw;
        }

        // File uploads for REST can come in $_FILES; keep parity with legacy controller.
        $files = $_FILES['attachments'] ?? [];

        $svc = $this->assignmentService();
        $result = $svc->submitAssignment($assignment_id, get_current_user_id(), $content, is_array($files) ? $files : []);
        if (empty($result['success'])) {
            return $this->error('assignment_submit_failed', (string) ($result['message'] ?? __('Could not submit assignment.', 'sikshya')), 400);
        }

        return new WP_REST_Response(['ok' => true, 'data' => $result['submission']], 200);
    }

    public function getMyAssignmentFeedback(WP_REST_Request $request): WP_REST_Response
    {
        $uid = get_current_user_id();
        $assignment_id = (int) $request->get_param('assignment_id');
        $svc = $this->assignmentService();
        $row = $svc->getAssignmentFeedback($assignment_id, $uid);

        return new WP_REST_Response(['ok' => true, 'data' => ['feedback' => $row]], 200);
    }

    private function getCourseService(): CourseService
    {
        $svc = $this->plugin->getService('course');
        if (!$svc instanceof CourseService) {
            throw new \RuntimeException('Course service unavailable');
        }

        return $svc;
    }

    private function assignmentService(): AssignmentService
    {
        $svc = $this->plugin->getService('assignment');
        if (!$svc instanceof AssignmentService) {
            throw new \RuntimeException('Assignment service unavailable');
        }

        return $svc;
    }

    /**
     * Chooses which question posts to grade. The learn UI may show a Pro “question bank”
     * draw; the client then sends the displayed ids in `question_ids` and Pro extends the
     * allow-list. When no client list is sent, the quiz’s saved `_sikshya_quiz_questions`
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

    private function syncEnrollmentProgress(int $user_id, int $course_id): void
    {
        $row = $this->enrollment->findByUserAndCourse($user_id, $course_id);
        if (!$row) {
            return;
        }

        $criteria = (string) Settings::get('course_completion_criteria', 'all_lessons');
        $pct = CourseCompletionEvaluator::computeProgressPercent($user_id, $course_id, $this->progress);

        $patch = ['progress' => $pct];

        if ($criteria === 'manual') {
            $this->enrollment->update((int) $row->id, $patch);

            return;
        }

        $was_completed = (string) $row->status === 'completed';
        if (
            ! $was_completed
            && CourseCompletionEvaluator::shouldMarkEnrollmentCompleted($pct, $criteria)
        ) {
            $patch['status'] = 'completed';
            $patch['completed_date'] = current_time('mysql');
        }

        $this->enrollment->update((int) $row->id, $patch);

        $now_completed = ! $was_completed && isset($patch['status']) && (string) $patch['status'] === 'completed';
        if ($now_completed) {
            $issue = new CertificateIssuanceService();
            $issued_id = $issue->issueIfEnabled($user_id, $course_id);
            do_action('sikshya_course_completed', $user_id, $course_id);
            if ($issued_id) {
                do_action('sikshya_certificate_issued', $user_id, $course_id, $issued_id);
            }
        }
    }

    /**
     * @param object $row
     */
    private function mapProgressRow($row): array
    {
        return [
            'id' => (int) $row->id,
            'lesson_id' => $row->lesson_id ? (int) $row->lesson_id : null,
            'quiz_id' => $row->quiz_id ? (int) $row->quiz_id : null,
            'status' => (string) $row->status,
            'percentage' => isset($row->percentage) ? (float) $row->percentage : 0.0,
            'completed_date' => $row->completed_date ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    private function error(string $code, string $message, int $status): WP_REST_Response
    {
        return new WP_REST_Response(
            [
                'ok' => false,
                'code' => $code,
                'message' => $message,
            ],
            $status
        );
    }
}
