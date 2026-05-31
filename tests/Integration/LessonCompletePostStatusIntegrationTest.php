<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Real-WP regression coverage for the lesson post_status guard in
 * `ProgressRoutes::lessonComplete`.
 *
 * Background: `LearnerCurriculumHelper::lessonIdsForCourse` returns lesson
 * IDs filtered by post_type only, NOT post_status. Pre-fix, a learner could
 * mark a draft/trashed lesson complete by passing its ID to the
 * `/me/lesson-complete` REST endpoint.
 *
 * These tests exercise the endpoint via `rest_do_request`, so the test
 * verifies the actual route registration AND the new post_status guard at
 * once. If either regresses, a test fails.
 *
 * @covers \Sikshya\Api\Learner\ProgressRoutes::lessonComplete
 */
final class LessonCompletePostStatusIntegrationTest extends WP_UnitTestCase
{
    /** @var \WP_REST_Server|null */
    private ?WP_REST_Server $server;
    private int $course_id = 0;
    private int $user_id = 0;

    public function setUp(): void
    {
        parent::setUp();

        // Spin up a fresh REST server so the Sikshya routes get registered.
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

        // Enroll the learner directly so the endpoint passes the enrollment
        // gate and we can specifically test the post_status check that comes
        // after.
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

    private function attachLessonToCourse(int $lesson_id): void
    {
        // Mirror what the curriculum builder does so
        // LearnerCurriculumHelper::lessonIdsForCourse(...) returns this lesson.
        update_post_meta($lesson_id, '_sikshya_lesson_course', $this->course_id);

        $chapter_id = self::factory()->post->create([
            'post_type' => 'sik_chapter',
            'post_status' => 'publish',
        ]);
        update_post_meta($chapter_id, '_sikshya_chapter_course_id', $this->course_id);
        update_post_meta($chapter_id, '_sikshya_contents', [$lesson_id]);
        update_post_meta($this->course_id, '_sikshya_chapters', [$chapter_id]);
    }

    private function makeLesson(string $status): int
    {
        $lesson_id = self::factory()->post->create([
            'post_type' => PostTypes::LESSON,
            'post_status' => $status,
        ]);
        $this->attachLessonToCourse($lesson_id);
        return $lesson_id;
    }

    private function postLessonComplete(int $lesson_id): \WP_REST_Response
    {
        $request = new WP_REST_Request('POST', '/sikshya/v1/me/lesson-complete');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'course_id' => $this->course_id,
            'lesson_id' => $lesson_id,
        ]));
        return $this->server->dispatch($request);
    }

    public function testRejectsDraftLesson(): void
    {
        $lesson_id = $this->makeLesson('draft');
        $response = $this->postLessonComplete($lesson_id);

        self::assertSame(400, $response->get_status());
        $data = $response->get_data();
        self::assertIsArray($data);
        self::assertSame(
            'lesson_unavailable',
            $data['code'] ?? null,
            'Draft lessons must surface the lesson_unavailable error code so the React client can distinguish from generic "invalid lesson".'
        );
    }

    public function testRejectsTrashedLesson(): void
    {
        $lesson_id = $this->makeLesson('trash');
        $response = $this->postLessonComplete($lesson_id);

        self::assertSame(400, $response->get_status());
        self::assertSame('lesson_unavailable', $response->get_data()['code'] ?? null);
    }

    public function testPublishedLessonPassesPostStatusGuard(): void
    {
        $lesson_id = $this->makeLesson('publish');
        $response = $this->postLessonComplete($lesson_id);

        $data = $response->get_data();
        $code = is_array($data) ? ($data['code'] ?? null) : null;

        self::assertNotSame(
            'lesson_unavailable',
            $code,
            'Published lesson must NOT trip the post_status guard — otherwise the happy path is broken.'
        );
        // We expect either a 200 success or a downstream error, but never the
        // post_status-specific 400.
    }
}
