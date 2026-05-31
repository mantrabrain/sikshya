<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Services\AssignmentService;
use WP_UnitTestCase;

/**
 * Real-WP regression coverage for the post_status check in
 * `AssignmentService::submitAssignment`.
 *
 * Replaces (and supplements) the source-grep guard in
 * tests/Unit/Services/AssignmentServicePostStatusTest.php with assertions
 * against a real WordPress kernel: posts are inserted via the WP_UnitTestCase
 * factory, users are real WP_User instances, and the service exercises the
 * real CourseRepository / SubmissionRepository against a live DB.
 *
 * @covers \Sikshya\Services\AssignmentService::submitAssignment
 */
final class AssignmentSubmissionIntegrationTest extends WP_UnitTestCase
{
    private function makeAssignment(string $status, int $courseId = 0): int
    {
        $assignment_id = self::factory()->post->create([
            'post_type' => PostTypes::ASSIGNMENT,
            'post_status' => $status,
            'post_title' => 'Sample Assignment',
        ]);
        if ($courseId > 0) {
            update_post_meta($assignment_id, '_sikshya_assignment_course', $courseId);
        }
        return $assignment_id;
    }

    private function makeCourse(string $status = 'publish'): int
    {
        return self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => $status,
            'post_title' => 'Sample Course',
        ]);
    }

    public function testRejectsDraftAssignment(): void
    {
        $course_id = $this->makeCourse();
        $assignment_id = $this->makeAssignment('draft', $course_id);
        $user_id = self::factory()->user->create();

        $svc = new AssignmentService();
        $result = $svc->submitAssignment($assignment_id, $user_id, 'My answer');

        self::assertFalse($result['success']);
        self::assertSame(
            'This assignment is no longer available.',
            $result['message'],
            'Draft assignments must be rejected by the post_status === publish guard.'
        );
    }

    public function testRejectsTrashedAssignment(): void
    {
        $course_id = $this->makeCourse();
        $assignment_id = $this->makeAssignment('trash', $course_id);
        $user_id = self::factory()->user->create();

        $svc = new AssignmentService();
        $result = $svc->submitAssignment($assignment_id, $user_id, 'My answer');

        self::assertFalse($result['success']);
        self::assertSame('This assignment is no longer available.', $result['message']);
    }

    public function testRejectsPendingAssignment(): void
    {
        $course_id = $this->makeCourse();
        $assignment_id = $this->makeAssignment('pending', $course_id);
        $user_id = self::factory()->user->create();

        $svc = new AssignmentService();
        $result = $svc->submitAssignment($assignment_id, $user_id, 'My answer');

        self::assertFalse($result['success'], 'Pending (awaiting review) status must also be rejected.');
    }

    public function testRejectsWrongPostType(): void
    {
        $course_id = $this->makeCourse();
        $not_assignment_id = self::factory()->post->create([
            'post_type' => PostTypes::LESSON,
            'post_status' => 'publish',
        ]);
        $user_id = self::factory()->user->create();

        $svc = new AssignmentService();
        $result = $svc->submitAssignment($not_assignment_id, $user_id, 'My answer');

        self::assertSame('Invalid assignment.', $result['message']);
    }

    public function testRejectsMissingPost(): void
    {
        $user_id = self::factory()->user->create();
        $svc = new AssignmentService();
        $result = $svc->submitAssignment(999999, $user_id, 'My answer');
        self::assertSame('Invalid assignment.', $result['message']);
    }

    public function testRejectsZeroIds(): void
    {
        $svc = new AssignmentService();
        self::assertSame('Invalid request.', $svc->submitAssignment(0, 1, 'x')['message']);
        self::assertSame('Invalid request.', $svc->submitAssignment(1, 0, 'x')['message']);
    }

    public function testPublishedAssignmentDoesNotShortCircuitOnStatus(): void
    {
        $course_id = $this->makeCourse();
        $assignment_id = $this->makeAssignment('publish', $course_id);
        $user_id = self::factory()->user->create();

        $svc = new AssignmentService();
        $result = $svc->submitAssignment($assignment_id, $user_id, 'My answer');

        // We don't expect a SUCCESS here — the user isn't enrolled, and the
        // assignment has no course-link metadata set up — but the failure
        // must NOT be the post_status one. Otherwise we'd have broken the
        // happy path with an over-broad guard.
        self::assertNotSame(
            'This assignment is no longer available.',
            $result['message'] ?? '',
            'Published assignment must pass the post_status guard and reach downstream validation.'
        );
    }
}
