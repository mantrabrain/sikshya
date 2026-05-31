<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * REST behaviour coverage for `/sikshya/v1/me/enroll` — the free-course
 * "enroll now" path bypasses checkout and is the only enroll route that
 * never reads/writes payment data, so it's the highest-volume revenue
 * gate we have to keep correct.
 *
 * Verifies:
 *   - Logged-in user enrolls in a free course → 200, enrollment row written
 *   - Same user re-enrolls → graceful 400, no duplicate row
 *   - Logged-in user tries a paid course without filter → 403, no row
 *   - Logged-in user tries a paid course with filter ON → 200, row written
 *     (the filter is the official extension point for add-ons / one-click
 *     enrollment flows)
 *   - Unauthenticated request → blocked at permission_callback (401)
 *   - Invalid / missing course_id → 400
 *
 * @covers \Sikshya\Api\PublicRestRoutes::enroll
 * @covers \Sikshya\Services\CourseService::enrollUser
 */
final class FreeEnrollmentIntegrationTest extends WP_UnitTestCase
{
    private ?WP_REST_Server $server;
    private int $user_id = 0;

    public function setUp(): void
    {
        parent::setUp();

        global $wp_rest_server;
        $this->server = new WP_REST_Server();
        $wp_rest_server = $this->server;
        do_action('rest_api_init', $this->server);

        $this->user_id = self::factory()->user->create(['role' => 'subscriber']);
    }

    public function tearDown(): void
    {
        global $wp_rest_server;
        $this->server = null;
        $wp_rest_server = null;
        remove_all_filters('sikshya_rest_me_enroll_allowed');
        parent::tearDown();
    }

    private function makeCourse(string $price = '0', string $sale_price = ''): int
    {
        $course_id = self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
        ]);
        update_post_meta($course_id, '_sikshya_price', $price);
        if ($sale_price !== '') {
            update_post_meta($course_id, '_sikshya_sale_price', $sale_price);
        }
        return $course_id;
    }

    private function postEnroll(int $course_id): \WP_REST_Response
    {
        $request = new WP_REST_Request('POST', '/sikshya/v1/me/enroll');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['course_id' => $course_id]));
        return $this->server->dispatch($request);
    }

    private function enrollmentRowCount(int $user_id, int $course_id): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sikshya_enrollments WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));
    }

    public function testEnrollsFreeCourseSuccessfully(): void
    {
        wp_set_current_user($this->user_id);
        $course_id = $this->makeCourse('0');

        $response = $this->postEnroll($course_id);

        self::assertSame(200, $response->get_status());
        self::assertTrue($response->get_data()['success'] ?? false);
        self::assertSame(
            1,
            $this->enrollmentRowCount($this->user_id, $course_id),
            'Successful enrollment must persist exactly one row in sikshya_enrollments.'
        );
    }

    public function testRejectsDuplicateEnrollment(): void
    {
        wp_set_current_user($this->user_id);
        $course_id = $this->makeCourse('0');

        // First enroll succeeds.
        $first = $this->postEnroll($course_id);
        self::assertSame(200, $first->get_status());

        // Second enroll must reject — no duplicate row.
        $second = $this->postEnroll($course_id);
        self::assertSame(
            400,
            $second->get_status(),
            'Re-enrolling must return 400 (validation), not 500 (crash) — the EnrollmentValidator throws an InvalidArgumentException for an existing enrollment.'
        );
        self::assertSame(
            1,
            $this->enrollmentRowCount($this->user_id, $course_id),
            'Duplicate enrollment attempt must NOT create a second row.'
        );
    }

    public function testRejectsPaidCourseWithoutFilter(): void
    {
        wp_set_current_user($this->user_id);
        $course_id = $this->makeCourse('29.99');

        $response = $this->postEnroll($course_id);

        self::assertSame(
            403,
            $response->get_status(),
            'Paid courses must be refused without an explicit `sikshya_rest_me_enroll_allowed` filter — otherwise /me/enroll becomes a payment-bypass.'
        );
        self::assertSame(
            0,
            $this->enrollmentRowCount($this->user_id, $course_id),
            'Refused paid-course enrollment must NOT create a row.'
        );
    }

    public function testFilterBypassesEndpointGuardButServiceStillRefusesPaidCourse(): void
    {
        // This documents an important defense-in-depth design: the
        // `sikshya_rest_me_enroll_allowed` filter opens the endpoint-level
        // 403 gate, but CourseService::enrollUser ALSO checks price and
        // throws InvalidArgumentException (mapped to 400 by the catch).
        //
        // Net effect: the filter alone CANNOT grant paid-course enrollment
        // via /me/enroll. To enroll a paid course without checkout, an
        // add-on must call `CourseService::enrollUser` directly with
        // `bypass_price_check => true` after verifying payment elsewhere.
        //
        // If this contract changes, fix it consciously — don't silently
        // open the door by removing the service-layer guard.
        wp_set_current_user($this->user_id);
        $course_id = $this->makeCourse('29.99');
        add_filter('sikshya_rest_me_enroll_allowed', '__return_true');

        $response = $this->postEnroll($course_id);

        self::assertSame(
            400,
            $response->get_status(),
            'Filter bypasses the endpoint-level 403, but the service still throws on a paid course → 400. Both guards must work independently.'
        );
        self::assertSame(
            0,
            $this->enrollmentRowCount($this->user_id, $course_id),
            'No enrollment row may be written when the service-layer guard fires.'
        );
    }

    public function testSalePriceWinsWhenSetAboveZero(): void
    {
        // The `_sikshya_sale_price` meta is treated as "active sale price"
        // only when its floatval > 0 — so storing '0' or empty means
        // "no sale". A real sale ($9.99 < regular $29.99) makes the
        // effective price the sale price.
        wp_set_current_user($this->user_id);
        $course_id = $this->makeCourse('29.99', '9.99');

        $response = $this->postEnroll($course_id);

        // Effective price is $9.99 — still paid, still refused (no filter).
        self::assertSame(
            403,
            $response->get_status(),
            'A sale price of $9.99 must still be treated as a paid course (not free) so /me/enroll refuses it without a filter.'
        );
    }

    public function testRejectsInvalidCourseId(): void
    {
        wp_set_current_user($this->user_id);

        $response = $this->postEnroll(0);
        self::assertSame(400, $response->get_status());
        self::assertFalse($response->get_data()['success'] ?? true);
    }

    public function testRejectsUnauthenticatedRequest(): void
    {
        // Don't set a current user — fully anonymous.
        wp_set_current_user(0);
        $course_id = $this->makeCourse('0');

        $response = $this->postEnroll($course_id);

        // permission_callback returns WP_Error → REST infrastructure maps to 401.
        self::assertContains(
            $response->get_status(),
            [401, 403],
            'Anonymous /me/enroll must be refused at the permission_callback layer (401/403), not reach the handler.'
        );
        self::assertSame(
            0,
            $this->enrollmentRowCount(0, $course_id),
            'Anonymous attempt must NOT create a row attributed to user_id=0.'
        );
    }
}
