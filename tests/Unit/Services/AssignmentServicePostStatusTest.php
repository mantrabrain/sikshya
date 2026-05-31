<?php

declare(strict_types=1);

namespace Sikshya\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\AssignmentSubmissionRepository;
use Sikshya\Database\Repositories\CourseRepository;
use Sikshya\Database\Repositories\ProgressRepository;
use Sikshya\Services\AssignmentService;

/**
 * Regression guard for the post_status check in `AssignmentService::submitAssignment`.
 *
 * Background: pre-fix, the service only verified `post_type === ASSIGNMENT`,
 * which is still true for draft/trashed posts. An instructor pulling an
 * assignment mid-window did not stop in-flight submissions. The fix rejects
 * any assignment whose `post_status !== 'publish'` with a clear message.
 *
 * @covers \Sikshya\Services\AssignmentService::submitAssignment
 */
final class AssignmentServicePostStatusTest extends TestCase
{
    private function makeWpdb(): object
    {
        $wpdb = new class {
            /** @var string */
            public $prefix = 'wp_';
        };
        $GLOBALS['wpdb'] = $wpdb;
        return $wpdb;
    }

    /**
     * The repos are `final` (or instantiated via `new ClassName()`) so PHPUnit
     * mocks aren't available. We instantiate the real classes — none of their
     * methods are called in the guard-fires-early code paths the tests cover.
     */
    private function makeService(): AssignmentService
    {
        $this->makeWpdb();
        return new AssignmentService(
            new CourseRepository(),
            new AssignmentSubmissionRepository(),
            new ProgressRepository()
        );
    }

    protected function setUp(): void
    {
        global $sik_test_posts;
        $sik_test_posts = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    public function testRejectsDraftAssignmentBeforeAnyDownstreamWork(): void
    {
        global $sik_test_posts;
        $sik_test_posts[101] = [
            'post_type' => PostTypes::ASSIGNMENT,
            'post_status' => 'draft',
        ];

        $result = $this->makeService()->submitAssignment(101, 7, 'My answer');

        self::assertSame(
            ['success' => false, 'message' => 'This assignment is no longer available.'],
            $result,
            'Submitting against a draft assignment must short-circuit before the enrollment/course lookup.'
        );
    }

    public function testRejectsTrashedAssignment(): void
    {
        global $sik_test_posts;
        $sik_test_posts[202] = [
            'post_type' => PostTypes::ASSIGNMENT,
            'post_status' => 'trash',
        ];

        $result = $this->makeService()->submitAssignment(202, 7, 'My answer');
        self::assertFalse($result['success']);
        self::assertSame('This assignment is no longer available.', $result['message']);
    }

    public function testRejectsWrongPostTypeBeforeStatusCheck(): void
    {
        global $sik_test_posts;
        $sik_test_posts[303] = [
            'post_type' => PostTypes::LESSON,
            'post_status' => 'publish',
        ];

        $result = $this->makeService()->submitAssignment(303, 7, 'My answer');
        self::assertSame('Invalid assignment.', $result['message']);
    }

    public function testRejectsMissingPost(): void
    {
        // No $sik_test_posts entry → get_post() returns null.
        $result = $this->makeService()->submitAssignment(9999, 7, 'My answer');
        self::assertSame('Invalid assignment.', $result['message']);
    }

    public function testRejectsInvalidParams(): void
    {
        $svc = $this->makeService();
        self::assertSame('Invalid request.', $svc->submitAssignment(0, 7, '')['message']);
        self::assertSame('Invalid request.', $svc->submitAssignment(101, 0, '')['message']);
    }
}
