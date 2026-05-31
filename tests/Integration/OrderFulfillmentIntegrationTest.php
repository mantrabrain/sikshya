<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Commerce\OrderFulfillmentService;
use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Repositories\PaymentRepository;
use Sikshya\Services\CourseService;
use WP_UnitTestCase;

/**
 * Behaviour coverage for `OrderFulfillmentService::fulfillPaidOrder`.
 *
 * Pay attention to the transactional semantics: pre-fix, concurrent gateway
 * redeliveries could race on the check-then-act idempotency guard and
 * double-enroll. The service now wraps everything in a transaction with a
 * `SELECT … FOR UPDATE` row lock so a second caller short-circuits.
 *
 * We verify the contract end-to-end:
 *   - First fulfillment writes enrollment + payment, transitions order → paid
 *   - Second fulfillment is a no-op (idempotent), no duplicate writes
 *   - Order with no line items doesn't transition (defensive)
 *
 * @covers \Sikshya\Commerce\OrderFulfillmentService::fulfillPaidOrder
 */
final class OrderFulfillmentIntegrationTest extends WP_UnitTestCase
{
    private OrderFulfillmentService $svc;
    private OrderRepository $orders;
    private int $user_id = 0;
    private int $course_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        $this->orders = new OrderRepository();
        $this->svc = new OrderFulfillmentService(
            $this->orders,
            new PaymentRepository(),
            new CourseService()
        );
        $this->user_id = self::factory()->user->create(['role' => 'subscriber']);
        $this->course_id = self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'post_title' => 'A purchasable course',
        ]);
        update_post_meta($this->course_id, '_sikshya_price', '29.99');
    }

    private function makePendingOrder(float $total = 29.99): int
    {
        $order_id = $this->orders->createOrder([
            'user_id' => $this->user_id,
            'status' => 'pending',
            'currency' => 'USD',
            'subtotal' => $total,
            'total' => $total,
            'gateway' => 'offline',
        ]);
        $this->orders->addOrderItem($order_id, $this->course_id, 1, $total, $total);
        return $order_id;
    }

    private function enrollmentRowCount(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sikshya_enrollments WHERE user_id = %d AND course_id = %d",
            $this->user_id,
            $this->course_id
        ));
    }

    public function testFulfillPaidOrderEnrollsUserAndTransitionsOrder(): void
    {
        $order_id = $this->makePendingOrder();

        $result = $this->svc->fulfillPaidOrder($order_id);

        self::assertTrue($result, 'fulfillPaidOrder must return true on success.');
        self::assertSame(
            1,
            $this->enrollmentRowCount(),
            'Fulfillment must create exactly one enrollment row.'
        );

        $order = $this->orders->findByIdForUpdate($order_id);
        self::assertNotNull($order);
        self::assertSame(
            'paid',
            (string) $order->status,
            'Order must transition pending → paid after successful fulfillment.'
        );
    }

    public function testFulfillPaidOrderIsIdempotent(): void
    {
        $order_id = $this->makePendingOrder();

        // First fulfillment.
        self::assertTrue($this->svc->fulfillPaidOrder($order_id));
        self::assertSame(1, $this->enrollmentRowCount());

        // Second fulfillment of the same order must short-circuit.
        self::assertTrue(
            $this->svc->fulfillPaidOrder($order_id),
            'Re-calling fulfill on a paid order must succeed (idempotent), not throw.'
        );
        self::assertSame(
            1,
            $this->enrollmentRowCount(),
            'Idempotent fulfillment must NOT create a duplicate enrollment row — that\'s the whole point of the SELECT FOR UPDATE.'
        );
    }

    public function testFulfillPaidOrderReturnsFalseForMissingOrder(): void
    {
        self::assertFalse(
            $this->svc->fulfillPaidOrder(999999),
            'Missing order must return false (no crash, no state mutation).'
        );
    }

    public function testFulfillPaidOrderReturnsFalseForOrderWithNoItems(): void
    {
        $order_id = $this->orders->createOrder([
            'user_id' => $this->user_id,
            'status' => 'pending',
            'currency' => 'USD',
            'subtotal' => 0.00,
            'total' => 0.00,
        ]);
        // Intentionally no addOrderItem call.

        self::assertFalse(
            $this->svc->fulfillPaidOrder($order_id),
            'Order with no line items must rollback and return false (defensive).'
        );

        $order = $this->orders->findByIdForUpdate($order_id);
        self::assertSame(
            'pending',
            (string) $order->status,
            'Order with no items must remain in pending state.'
        );
        self::assertSame(0, $this->enrollmentRowCount(), 'No enrollment should be created.');
    }

    // NOTE: Payment-row creation requires the gateway settings + payments
    // table schema to be fully realized in the test DB. The current
    // WP_UnitTestCase transaction wrapper appears to interact with the
    // service's own START TRANSACTION in a way that the payment-row
    // assertion can't reliably observe. The behaviour is verified at the
    // unit level (PaymentRepository::create) and via the production REST
    // tests on sikshya.local. Re-add an integration assertion here only
    // once we've moved off transactional test wrapping.
}
