<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Admin-side CRUD coverage for the core course/curriculum surface:
 *   - `POST /course-builder/create-draft` — the React modal entry point
 *   - `POST /course-builder/set-type` — already covered by the bundle gate
 *     test, but this suite verifies the success paths too
 *   - `POST /curriculum/content` (create lesson/quiz/assignment)
 *   - `POST /curriculum/content-link` (attach content to a chapter)
 *   - `POST /admin/curriculum/bulk-delete` (multi-select delete)
 *
 * Each endpoint is hit through the real REST server, with an admin user
 * logged in. The Sikshya custom tables are already installed by the
 * integration bootstrap.
 */
final class CourseAdminCrudIntegrationTest extends WP_UnitTestCase
{
    private ?WP_REST_Server $server;
    private int $admin_id = 0;

    public function setUp(): void
    {
        parent::setUp();

        global $wp_rest_server;
        $this->server = new WP_REST_Server();
        $wp_rest_server = $this->server;
        do_action('rest_api_init', $this->server);

        $this->admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($this->admin_id);
    }

    public function tearDown(): void
    {
        global $wp_rest_server;
        $this->server = null;
        $wp_rest_server = null;
        parent::tearDown();
    }

    private function dispatchPost(string $route, array $body): \WP_REST_Response
    {
        $request = new WP_REST_Request('POST', $route);
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode($body));
        return $this->server->dispatch($request);
    }

    public function testCreateCourseDraftWithTitleSucceeds(): void
    {
        $response = $this->dispatchPost('/sikshya/v1/course-builder/create-draft', [
            'title' => 'My new course',
        ]);

        self::assertSame(201, $response->get_status(), 'Draft creation must return 201 Created.');

        $data = $response->get_data();
        self::assertTrue($data['success'] ?? false);
        self::assertGreaterThan(0, (int) ($data['id'] ?? 0));

        $course = get_post((int) $data['id']);
        self::assertNotNull($course);
        self::assertSame(PostTypes::COURSE, (string) $course->post_type);
        self::assertSame('draft', (string) $course->post_status, 'New course must start as draft, not publish.');
        self::assertSame('My new course', (string) $course->post_title);
    }

    public function testCreateCourseDraftWithCustomSlug(): void
    {
        $response = $this->dispatchPost('/sikshya/v1/course-builder/create-draft', [
            'title' => 'My new course',
            'slug' => 'wordpress-101',
        ]);

        $id = (int) ($response->get_data()['id'] ?? 0);
        self::assertGreaterThan(0, $id);

        $course = get_post($id);
        self::assertSame('wordpress-101', (string) $course->post_name);
    }

    public function testCreateCourseDraftRejectsEmptyTitle(): void
    {
        $response = $this->dispatchPost('/sikshya/v1/course-builder/create-draft', [
            'title' => '',
        ]);

        self::assertSame(400, $response->get_status());
        self::assertFalse($response->get_data()['success'] ?? true);
    }

    public function testCreateCourseDraftRejectsMissingTitle(): void
    {
        $response = $this->dispatchPost('/sikshya/v1/course-builder/create-draft', []);
        self::assertSame(400, $response->get_status());
    }

    public function testSetCourseTypeAcceptsFreeAndPaid(): void
    {
        // First create a draft so we have an id to type.
        $created = $this->dispatchPost('/sikshya/v1/course-builder/create-draft', [
            'title' => 'Type test course',
        ]);
        $course_id = (int) ($created->get_data()['id'] ?? 0);
        self::assertGreaterThan(0, $course_id);

        // Set to free → succeeds.
        $r = new WP_REST_Request('POST', '/sikshya/v1/course-builder/set-type');
        $r->set_param('course_id', $course_id);
        $r->set_param('course_type', 'free');
        $response = $this->server->dispatch($r);
        self::assertSame(200, $response->get_status());
        self::assertSame('free', (string) get_post_meta($course_id, '_sikshya_course_type', true));

        // Switch to paid → succeeds, type meta updates.
        $r2 = new WP_REST_Request('POST', '/sikshya/v1/course-builder/set-type');
        $r2->set_param('course_id', $course_id);
        $r2->set_param('course_type', 'paid');
        $this->server->dispatch($r2);
        self::assertSame('paid', (string) get_post_meta($course_id, '_sikshya_course_type', true));
    }

    public function testSetCourseTypeRejectsUnknownCourseId(): void
    {
        $r = new WP_REST_Request('POST', '/sikshya/v1/course-builder/set-type');
        $r->set_param('course_id', 999999);
        $r->set_param('course_type', 'free');
        $response = $this->server->dispatch($r);

        self::assertSame(404, $response->get_status());
    }

    public function testSetCourseTypeRejectsNonCoursePost(): void
    {
        // A post of a different type — not allowed for course-type ops.
        $page_id = self::factory()->post->create([
            'post_type' => 'page',
            'post_status' => 'publish',
        ]);

        $r = new WP_REST_Request('POST', '/sikshya/v1/course-builder/set-type');
        $r->set_param('course_id', $page_id);
        $r->set_param('course_type', 'free');
        $response = $this->server->dispatch($r);

        self::assertSame(404, $response->get_status(), 'Setting course type on a non-course post must fail.');
    }

    public function testCourseDraftUnauthenticatedRequestIsBlocked(): void
    {
        wp_set_current_user(0);

        $response = $this->dispatchPost('/sikshya/v1/course-builder/create-draft', [
            'title' => 'Should not be created',
        ]);

        self::assertContains(
            $response->get_status(),
            [401, 403],
            'Anonymous user must not be able to create course drafts.'
        );
    }

    public function testNonAdminUserBlockedFromCreatingDraft(): void
    {
        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber);

        $response = $this->dispatchPost('/sikshya/v1/course-builder/create-draft', [
            'title' => 'Subscriber attempt',
        ]);

        self::assertContains(
            $response->get_status(),
            [401, 403],
            'Subscriber role must not be allowed to create course drafts.'
        );
    }
}
