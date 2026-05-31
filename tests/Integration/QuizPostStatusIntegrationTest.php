<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Real-WP regression coverage for the post_status guard added to all three
 * learner-facing quiz endpoints (`quizSubmit`, `startMyQuizAttempt`,
 * `getMyQuizAttempt`).
 *
 * @covers \Sikshya\Api\Learner\QuizRoutes::quizSubmit
 * @covers \Sikshya\Api\Learner\QuizRoutes::startMyQuizAttempt
 * @covers \Sikshya\Api\Learner\QuizRoutes::getMyQuizAttempt
 */
final class QuizPostStatusIntegrationTest extends WP_UnitTestCase
{
    private ?WP_REST_Server $server;
    private int $course_id = 0;
    private int $user_id = 0;

    public function setUp(): void
    {
        parent::setUp();

        global $wp_rest_server;
        $this->server = new WP_REST_Server();
        $wp_rest_server = $this->server;
        do_action('rest_api_init', $this->server);

        $this->course_id = self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
        ]);
        $this->user_id = self::factory()->user->create([
            'role' => 'subscriber',
        ]);
        wp_set_current_user($this->user_id);

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'sikshya_enrollments', [
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'status' => 'enrolled',
            'enrolled_date' => current_time('mysql'),
        ]);
    }

    public function tearDown(): void
    {
        global $wp_rest_server;
        $this->server = null;
        $wp_rest_server = null;
        parent::tearDown();
    }

    private function makeQuiz(string $status): int
    {
        $quiz_id = self::factory()->post->create([
            'post_type' => PostTypes::QUIZ,
            'post_status' => $status,
        ]);
        // Link the quiz to the course so LessonCourseLink::resolvedCourseIdForQuiz
        // can find it (otherwise the test fails on `quiz_no_course` before
        // the post_status check is reached).
        update_post_meta($quiz_id, '_sikshya_quiz_course', $this->course_id);
        return $quiz_id;
    }

    private function dispatchPost(string $route, array $body): \WP_REST_Response
    {
        $request = new WP_REST_Request('POST', $route);
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode($body));
        return $this->server->dispatch($request);
    }

    private function dispatchGet(string $route, array $params): \WP_REST_Response
    {
        $request = new WP_REST_Request('GET', $route);
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        return $this->server->dispatch($request);
    }

    public function testQuizSubmitRejectsDraftQuiz(): void
    {
        $quiz_id = $this->makeQuiz('draft');
        $response = $this->dispatchPost('/sikshya/v1/me/quiz-submit', [
            'quiz_id' => $quiz_id,
            'answers' => [],
        ]);

        self::assertSame(400, $response->get_status());
        self::assertSame(
            'quiz_unavailable',
            $response->get_data()['code'] ?? null,
            'quizSubmit must reject any quiz whose post_status is not publish.'
        );
    }

    public function testQuizSubmitRejectsTrashedQuiz(): void
    {
        $quiz_id = $this->makeQuiz('trash');
        $response = $this->dispatchPost('/sikshya/v1/me/quiz-submit', [
            'quiz_id' => $quiz_id,
            'answers' => [],
        ]);

        self::assertSame(400, $response->get_status());
        self::assertSame('quiz_unavailable', $response->get_data()['code'] ?? null);
    }

    public function testStartMyQuizAttemptRejectsDraftQuiz(): void
    {
        $quiz_id = $this->makeQuiz('draft');
        $response = $this->dispatchPost('/sikshya/v1/me/quiz-attempt', [
            'quiz_id' => $quiz_id,
        ]);

        self::assertSame(400, $response->get_status());
        self::assertSame(
            'quiz_unavailable',
            $response->get_data()['code'] ?? null,
            'startMyQuizAttempt must reject any quiz whose post_status is not publish.'
        );
    }

    public function testGetMyQuizAttemptRejectsDraftQuiz(): void
    {
        $quiz_id = $this->makeQuiz('draft');
        $response = $this->dispatchGet('/sikshya/v1/me/quiz-attempt', [
            'quiz_id' => $quiz_id,
        ]);

        self::assertSame(400, $response->get_status());
        self::assertSame(
            'quiz_unavailable',
            $response->get_data()['code'] ?? null,
            'getMyQuizAttempt must reject any quiz whose post_status is not publish (consistency with submit/start).'
        );
    }

    public function testStartMyQuizAttemptEnforcesAttemptCapBeforeCreate(): void
    {
        $quiz_id = $this->makeQuiz('publish');

        // Set the per-quiz attempt cap to 1.
        update_post_meta($quiz_id, '_sikshya_max_attempts', 1);

        // Pre-create one completed attempt — at the cap.
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'sikshya_quiz_attempts', [
            'user_id' => $this->user_id,
            'quiz_id' => $quiz_id,
            'course_id' => $this->course_id,
            'attempt_number' => 1,
            'status' => 'completed',
            'started_at' => current_time('mysql'),
            'completed_at' => current_time('mysql'),
        ]);

        // Starting a new attempt should now fail with attempts_exhausted
        // BEFORE inserting a stale in_progress row that the learner could
        // never submit.
        $response = $this->dispatchPost('/sikshya/v1/me/quiz-attempt', [
            'quiz_id' => $quiz_id,
        ]);

        self::assertSame(400, $response->get_status());
        self::assertSame(
            'attempts_exhausted',
            $response->get_data()['code'] ?? null,
            'startMyQuizAttempt must enforce the attempt cap before createAttempt — otherwise the learner accrues stale in_progress rows.'
        );

        // Verify no NEW attempt row was created (still just the original
        // completed one).
        $rows = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sikshya_quiz_attempts WHERE user_id = %d AND quiz_id = %d",
            $this->user_id,
            $quiz_id
        ));
        self::assertSame(
            1,
            $rows,
            'No new attempt row may be created when the cap check fires — the original count of 1 must remain unchanged.'
        );
    }
}
