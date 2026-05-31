<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Real-WP regression coverage for the server-side bundle gate added to
 * `AdminRestRoutes::setCourseType`.
 *
 * Pre-fix, only `course_type=subscription` was gated; `course_type=bundle`
 * was accepted unconditionally. A direct API call (bypassing the React UI)
 * could mark any course as a bundle without the licensing tier + addon
 * being active. The fix mirrors the existing subscription gate for bundle.
 *
 * The Free integration suite runs without the `course_bundles` addon
 * enabled, so the bundle path MUST be refused.
 *
 * @covers \Sikshya\Api\AdminRestRoutes::setCourseType
 */
final class SetCourseTypeBundleGateIntegrationTest extends WP_UnitTestCase
{
    private ?WP_REST_Server $server;
    private int $admin_id = 0;
    private int $course_id = 0;

    public function setUp(): void
    {
        parent::setUp();

        global $wp_rest_server;
        $this->server = new WP_REST_Server();
        $wp_rest_server = $this->server;
        do_action('rest_api_init', $this->server);

        $this->admin_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);
        wp_set_current_user($this->admin_id);

        $this->course_id = self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'draft',
            'post_title' => 'Test course',
        ]);
    }

    public function tearDown(): void
    {
        global $wp_rest_server;
        $this->server = null;
        $wp_rest_server = null;
        parent::tearDown();
    }

    private function setTypeRequest(string $type): \WP_REST_Response
    {
        $request = new WP_REST_Request('POST', '/sikshya/v1/course-builder/set-type');
        $request->set_param('course_id', $this->course_id);
        $request->set_param('course_type', $type);
        return $this->server->dispatch($request);
    }

    public function testRejectsBundleTypeWithoutLicensedAddon(): void
    {
        $response = $this->setTypeRequest('bundle');

        self::assertSame(
            400,
            $response->get_status(),
            'Bundle type must be refused on Free tier (no course_bundles addon licensed/enabled).'
        );

        $data = $response->get_data();
        self::assertFalse($data['success'] ?? null);
        self::assertStringContainsString(
            'Course Bundles',
            (string) ($data['message'] ?? ''),
            'Error message must mention the Course Bundles addon so the admin knows what to enable.'
        );

        // CRITICAL: the meta MUST NOT have been written despite the gate
        // returning an error response. Without the early-return, a partial
        // write could leave the post in a half-configured "bundle" state.
        self::assertSame(
            '',
            (string) get_post_meta($this->course_id, '_sikshya_course_type', true),
            'Bundle gate must short-circuit BEFORE update_post_meta — otherwise a denied request still mutates state.'
        );
    }

    public function testRejectsSubscriptionTypeWithoutLicensedAddon(): void
    {
        // Sanity-check the original gate didn't regress.
        $response = $this->setTypeRequest('subscription');
        self::assertSame(400, $response->get_status());
        self::assertSame(
            '',
            (string) get_post_meta($this->course_id, '_sikshya_course_type', true)
        );
    }

    /**
     * @dataProvider providerHappyPathTypes
     */
    public function testAcceptsNonGatedTypes(string $type): void
    {
        $response = $this->setTypeRequest($type);
        $data = $response->get_data();

        self::assertSame(
            200,
            $response->get_status(),
            "Type '{$type}' must succeed. Got response: " . json_encode($data)
        );
        self::assertTrue($data['success'] ?? false);
        self::assertSame(
            $type,
            (string) get_post_meta($this->course_id, '_sikshya_course_type', true),
            "Type '{$type}' must persist — gate must not over-fire on free/paid types."
        );
    }

    public function providerHappyPathTypes(): array
    {
        return [
            'free' => ['free'],
            'paid' => ['paid'],
        ];
    }
}
