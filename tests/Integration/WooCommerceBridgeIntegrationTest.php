<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Integrations\WooCommerce\CourseAccessService;
use Sikshya\Integrations\WooCommerce\OrderSnapshot;
use Sikshya\Integrations\WooCommerce\ProductCourseLink;
use Sikshya\Integrations\WooCommerce\WooCommerceBridge;
use WP_UnitTestCase;

/**
 * Selling a course through WooCommerce.
 *
 * WooCommerce is not installed here, and that is the point of the design: every
 * rule about who gets access is decided from an {@see OrderSnapshot}, so it can
 * be tested without it. Only `WooOrderReader` touches WooCommerce's API, and it
 * does nothing but build one of these.
 *
 * The three behaviours worth protecting, in order of how much a mistake costs:
 * granting twice, revoking access the order never granted, and a paying guest
 * who ends up with nothing.
 *
 * @covers \Sikshya\Integrations\WooCommerce\CourseAccessService
 * @covers \Sikshya\Integrations\WooCommerce\ProductCourseLink
 * @covers \Sikshya\Integrations\WooCommerce\WooCommerceBridge
 *
 * @group woocommerce
 */
final class WooCommerceBridgeIntegrationTest extends WP_UnitTestCase
{
    private int $courseA = 0;
    private int $courseB = 0;
    private int $productId = 0;
    private int $buyer = 0;

    public function setUp(): void
    {
        parent::setUp();

        // The guest path sends WordPress's new-user email, and on this machine
        // the mail transport blocks when nothing is listening. Short-circuited
        // here so the suite measures the bridge, not SMTP.
        add_filter('pre_wp_mail', [self::class, 'swallowMail'], 10, 0);

        // WooCommerce's `product` post type, which exists on any shop and not
        // here. Registering it is only so `get_post_type()` behaves as it
        // would in production.
        if (!post_type_exists('product')) {
            register_post_type('product', ['public' => false]);
        }

        $this->courseA = self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'post_title' => 'Fire Safety',
        ]);
        update_post_meta($this->courseA, '_sikshya_price', 49);

        $this->courseB = self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'post_title' => 'First Aid',
        ]);
        update_post_meta($this->courseB, '_sikshya_price', 39);

        $this->productId = self::factory()->post->create([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Compliance bundle',
        ]);

        $this->buyer = self::factory()->user->create([
            'role' => 'subscriber',
            'user_email' => 'buyer@example.test',
        ]);
    }

    public function tearDown(): void
    {
        remove_filter('pre_wp_mail', [self::class, 'swallowMail'], 10);

        parent::tearDown();
    }

    public static function swallowMail(): bool
    {
        return true;
    }

    /**
     * @param array<int, int> $courseIds
     */
    private function snapshot(
        array $courseIds,
        int $userId = 0,
        string $email = '',
        int $orderId = 4242,
        string $status = 'completed',
        array $refunded = []
    ): OrderSnapshot {
        $lines = [];
        foreach ($courseIds as $index => $courseId) {
            $lines[] = [
                'course_id' => $courseId,
                'item_id' => 100 + $index,
                'product_id' => $this->productId,
                'refunded' => in_array($courseId, $refunded, true),
            ];
        }

        return new OrderSnapshot($orderId, $userId, $email, 'Ada', 'Buyer', $status, $lines);
    }

    // ----- the product link --------------------------------------------------

    public function testAProductCanSellSeveralCoursesAndFindsItsWayBack(): void
    {
        ProductCourseLink::link($this->productId, [$this->courseA, $this->courseB]);

        self::assertSame(
            [$this->courseA, $this->courseB],
            ProductCourseLink::coursesFor($this->productId),
            'Shops sell bundles; one course per product would be the wrong shape.'
        );
        self::assertSame($this->productId, ProductCourseLink::productFor($this->courseB));
    }

    public function testOnlyRealCoursesAreLinked(): void
    {
        $page = self::factory()->post->create(['post_type' => 'page']);

        ProductCourseLink::link($this->productId, [$this->courseA, $page, 999999, 0]);

        self::assertSame([$this->courseA], ProductCourseLink::coursesFor($this->productId));
        self::assertSame(
            [$this->courseA],
            (array) get_post_meta($this->productId, ProductCourseLink::META, true),
            'Filtered on the way in as well as on the way out: the stored value is what a site owner reads when something looks wrong.'
        );
    }

    public function testUnlinkingEverythingLeavesNoStaleMeta(): void
    {
        ProductCourseLink::link($this->productId, [$this->courseA]);
        ProductCourseLink::link($this->productId, []);

        self::assertSame([], ProductCourseLink::coursesFor($this->productId));
        self::assertSame('', (string) get_post_meta($this->productId, ProductCourseLink::META, true));
    }

    // ----- granting ----------------------------------------------------------

    public function testAPaidOrderEnrolsTheBuyerInEveryCourseOnIt(): void
    {
        $result = (new CourseAccessService())->grant(
            $this->snapshot([$this->courseA, $this->courseB], $this->buyer)
        );

        self::assertSame([$this->courseA, $this->courseB], $result['enrolled']);
        self::assertSame($this->buyer, $result['user_id']);

        $repo = new EnrollmentRepository();
        $row = $repo->findByUserAndCourse($this->buyer, $this->courseA);

        self::assertNotNull($row);
        self::assertSame('enrolled', (string) $row->status);
        self::assertSame('woocommerce', (string) $row->payment_method);
        self::assertSame(
            'wc:4242',
            (string) $row->transaction_id,
            'The order stamped on the enrolment is what lets a refund revoke exactly what it granted.'
        );
    }

    public function testGrantingTwiceEnrolsOnce(): void
    {
        $service = new CourseAccessService();
        $snapshot = $this->snapshot([$this->courseA], $this->buyer);

        $service->grant($snapshot);
        $second = $service->grant($snapshot);

        self::assertSame([], $second['enrolled']);
        self::assertSame([$this->courseA], $second['skipped']);

        global $wpdb;
        $rows = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'sikshya_enrollments WHERE user_id = %d AND course_id = %d',
            $this->buyer,
            $this->courseA
        ));

        self::assertSame(
            1,
            $rows,
            'WooCommerce fires on several statuses and gateways retry; enrolling twice has to be impossible, not unlikely.'
        );
    }

    public function testAPaidCourseIsGrantedEvenThoughItCostsMoney(): void
    {
        // `CourseService::enrollUser()` refuses to enrol into a paid course
        // unless the caller says it has been paid for. A bridge that did not
        // would silently grant nothing for every paid course — which is all of
        // them, in a shop.
        $result = (new CourseAccessService())->grant($this->snapshot([$this->courseA], $this->buyer));

        self::assertSame([$this->courseA], $result['enrolled']);
    }

    public function testProgressIsInitialisedAndTheEnrolledEventFires(): void
    {
        $seen = [];
        $listener = static function ($userId, $courseId) use (&$seen): void {
            $seen[] = [(int) $userId, (int) $courseId];
        };
        add_action('sikshya_user_enrolled', $listener, 10, 2);

        (new CourseAccessService())->grant($this->snapshot([$this->courseA], $this->buyer));

        remove_action('sikshya_user_enrolled', $listener, 10);

        self::assertSame(
            [[$this->buyer, $this->courseA]],
            $seen,
            'Everything else that reacts to an enrolment — xAPI, webhooks, the activity log — hangs off this one event.'
        );
    }

    // ----- guests ------------------------------------------------------------

    public function testAGuestOrderCreatesTheAccountItNeeds(): void
    {
        $result = (new CourseAccessService())->grant(
            $this->snapshot([$this->courseA], 0, 'guest@example.test')
        );

        self::assertGreaterThan(
            0,
            $result['user_id'],
            'WooCommerce will happily take money from a guest; course access belongs to a person.'
        );
        self::assertSame([$this->courseA], $result['enrolled']);

        $user = get_user_by('email', 'guest@example.test');
        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame('Ada', $user->first_name);
        self::assertContains('sikshya_student', (array) $user->roles);
    }

    public function testAGuestWhoseLoginIsTakenStillGetsAnAccount(): void
    {
        // Somebody already owns the login this email would produce, with a
        // different address — so a new account is needed under a new login.
        self::factory()->user->create(['user_login' => 'guest', 'user_email' => 'someone@else.test']);

        $result = (new CourseAccessService())->grant(
            $this->snapshot([$this->courseA], 0, 'guest@example.test')
        );

        self::assertGreaterThan(0, $result['user_id']);

        $user = get_userdata($result['user_id']);
        self::assertInstanceOf(\WP_User::class, $user);
        self::assertNotSame('guest', $user->user_login);
        self::assertStringStartsWith(
            'guest-',
            $user->user_login,
            'The first draft of this looped for ever here, because username_exists() returns false and not null.'
        );
        self::assertSame([$this->courseA], $result['enrolled']);
    }

    public function testAGuestOrderWithTheEmailOfAnExistingAccountUsesThatAccount(): void
    {
        $result = (new CourseAccessService())->grant(
            $this->snapshot([$this->courseA], 0, 'buyer@example.test')
        );

        self::assertSame(
            $this->buyer,
            $result['user_id'],
            'Buying as a guest with your own address should give the course to your account.'
        );
    }

    public function testGuestAccountCreationCanBeRefused(): void
    {
        add_filter('sikshya_woocommerce_create_account_for_guest', '__return_false');

        $result = (new CourseAccessService())->grant(
            $this->snapshot([$this->courseA], 0, 'nobody@example.test')
        );

        remove_filter('sikshya_woocommerce_create_account_for_guest', '__return_false');

        self::assertSame(0, $result['user_id']);
        self::assertSame([], $result['enrolled']);
        self::assertNotSame([], $result['notes'], 'A purchase that granted nothing has to say why.');
        self::assertFalse(get_user_by('email', 'nobody@example.test'));
    }

    public function testAnOrderWithNoCustomerAndNoEmailGrantsNothingAndSaysSo(): void
    {
        $result = (new CourseAccessService())->grant($this->snapshot([$this->courseA], 0, ''));

        self::assertSame(0, $result['user_id']);
        self::assertNotSame([], $result['notes']);
    }

    // ----- revoking ----------------------------------------------------------

    public function testARefundClosesTheAccessThatOrderGave(): void
    {
        $service = new CourseAccessService();
        $snapshot = $this->snapshot([$this->courseA], $this->buyer);
        $service->grant($snapshot);

        $result = $service->revoke($snapshot);

        self::assertSame([$this->courseA], $result['revoked']);

        $row = (new EnrollmentRepository())->findByUserAndCourse($this->buyer, $this->courseA);
        self::assertNotNull($row, 'Nothing is deleted: the record that they once had access survives.');
        self::assertSame('cancelled', (string) $row->status);
    }

    public function testARefundDoesNotTouchAccessItNeverGranted(): void
    {
        // Enrolled by an administrator, by hand, for their own reasons.
        $enrolments = new EnrollmentRepository();
        $enrolments->create([
            'user_id' => $this->buyer,
            'course_id' => $this->courseA,
            'status' => 'enrolled',
            'payment_method' => 'manual',
            'transaction_id' => '',
        ]);

        $result = (new CourseAccessService())->revoke($this->snapshot([$this->courseA], $this->buyer));

        self::assertSame([], $result['revoked']);
        self::assertSame([$this->courseA], $result['kept']);
        self::assertSame(
            'enrolled',
            (string) $enrolments->findByUserAndCourse($this->buyer, $this->courseA)->status,
            'Taking away access given for an unrelated reason is a mistake the customer notices from outside.'
        );
    }

    public function testARefundOfADifferentOrderLeavesThisOrdersAccessAlone(): void
    {
        $service = new CourseAccessService();
        $service->grant($this->snapshot([$this->courseA], $this->buyer, '', 1001));

        $result = $service->revoke($this->snapshot([$this->courseA], $this->buyer, '', 2002));

        self::assertSame([], $result['revoked']);
        self::assertSame('enrolled', (string) (new EnrollmentRepository())
            ->findByUserAndCourse($this->buyer, $this->courseA)->status);
    }

    public function testOnlyFullyRefundedLinesLoseAccessOnAPartialRefund(): void
    {
        $service = new CourseAccessService();
        $snapshot = $this->snapshot([$this->courseA, $this->courseB], $this->buyer);
        $service->grant($snapshot);

        $partly = $this->snapshot(
            [$this->courseA, $this->courseB],
            $this->buyer,
            '',
            4242,
            'completed',
            [$this->courseB]
        );

        $result = $service->revoke($partly, true);

        self::assertSame([$this->courseB], $result['revoked']);

        $repo = new EnrollmentRepository();
        self::assertSame('enrolled', (string) $repo->findByUserAndCourse($this->buyer, $this->courseA)->status);
        self::assertSame('cancelled', (string) $repo->findByUserAndCourse($this->buyer, $this->courseB)->status);
    }

    public function testBuyingAgainAfterARefundReopensTheSameEnrolment(): void
    {
        $service = new CourseAccessService();
        $snapshot = $this->snapshot([$this->courseA], $this->buyer);
        $service->grant($snapshot);
        $service->revoke($snapshot);

        $again = $this->snapshot([$this->courseA], $this->buyer, '', 5005);
        $result = $service->grant($again);

        self::assertSame([$this->courseA], $result['enrolled']);

        global $wpdb;
        $rows = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'sikshya_enrollments WHERE user_id = %d AND course_id = %d',
            $this->buyer,
            $this->courseA
        ));

        self::assertSame(1, $rows, 'Reopened, not duplicated — their progress against that row is still theirs.');
        self::assertSame('enrolled', (string) (new EnrollmentRepository())
            ->findByUserAndCourse($this->buyer, $this->courseA)->status);
    }

    public function testARefundedLineIsNotGrantedInTheFirstPlace(): void
    {
        $result = (new CourseAccessService())->grant(
            $this->snapshot([$this->courseA, $this->courseB], $this->buyer, '', 4242, 'completed', [$this->courseB])
        );

        self::assertSame([$this->courseA], $result['enrolled']);
        self::assertNull((new EnrollmentRepository())->findByUserAndCourse($this->buyer, $this->courseB));
    }

    // ----- the storefront ----------------------------------------------------

    public function testTheCoursePageHandsOverToWooCommerceOnlyWhenItSellsTheCourse(): void
    {
        ProductCourseLink::link($this->productId, [$this->courseA]);

        // `sellsCourse()` also requires WooCommerce itself, which is absent
        // here, so the claim filter must answer false — a page that handed over
        // to a shop that is not installed would show no way to buy at all.
        self::assertFalse(
            WooCommerceBridge::sellsCourse($this->courseA),
            'Without WooCommerce loaded there is nothing to hand over to.'
        );
        self::assertFalse((bool) apply_filters('sikshya_course_purchase_handled', false, $this->courseA));
        self::assertFalse((bool) apply_filters('sikshya_course_purchase_handled', false, $this->courseB));
    }

    public function testAlreadyEnrolledLearnersAreNotSoldTheCourseAgain(): void
    {
        ProductCourseLink::link($this->productId, [$this->courseA]);
        (new CourseAccessService())->grant($this->snapshot([$this->courseA], $this->buyer));
        wp_set_current_user($this->buyer);

        self::assertFalse(
            WooCommerceBridge::blockDuplicatePurchase(true, $this->productId, 1),
            'Selling somebody a course they are already in is a refund and a support ticket.'
        );
    }

    public function testALearnerWhoseAccessWasRevokedCanBuyAgain(): void
    {
        ProductCourseLink::link($this->productId, [$this->courseA]);
        $service = new CourseAccessService();
        $snapshot = $this->snapshot([$this->courseA], $this->buyer);
        $service->grant($snapshot);
        $service->revoke($snapshot);
        wp_set_current_user($this->buyer);

        self::assertTrue(
            (bool) WooCommerceBridge::blockDuplicatePurchase(true, $this->productId, 1),
            'A refunded learner buying again has to stay possible.'
        );
    }

    public function testAProductThatSellsNoCoursesIsLeftAlone(): void
    {
        wp_set_current_user($this->buyer);

        self::assertTrue((bool) WooCommerceBridge::blockDuplicatePurchase(true, $this->productId, 1));
    }
}
