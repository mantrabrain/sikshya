<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Database\Repositories\CouponRepository;
use WP_UnitTestCase;

/**
 * Real-DB behaviour coverage for the coupon lifecycle: create, find,
 * increment, expire, cap-hit.
 *
 * Built on top of the regression test `CouponRepositoryExpiryTest` (unit-level
 * wpdb mock) — this suite exercises the same paths against a live MySQL DB
 * so any SQL-dialect or migration breakage shows up here too.
 *
 * @covers \Sikshya\Database\Repositories\CouponRepository
 */
final class CouponLifecycleIntegrationTest extends WP_UnitTestCase
{
    private CouponRepository $repo;

    public function setUp(): void
    {
        parent::setUp();
        $this->repo = new CouponRepository();
    }

    private function makeCoupon(array $overrides = []): int
    {
        return $this->repo->createAdminCoupon(array_merge([
            'code' => 'TEST' . uniqid('', false),
            'discount_type' => 'percent',
            'discount_value' => 20.0,
            'max_uses' => 0,
            'expires_at' => null,
            'status' => 'active',
        ], $overrides));
    }

    public function testCreateAndFindActiveByCode(): void
    {
        $id = $this->makeCoupon(['code' => 'WELCOME20', 'discount_value' => 20.0]);
        self::assertGreaterThan(0, $id);

        $row = $this->repo->findActiveByCode('welcome20'); // case-insensitive
        self::assertNotNull(
            $row,
            'findActiveByCode must be case-insensitive — the code is uppercased before lookup.'
        );
        self::assertSame('WELCOME20', $row->code);
        self::assertSame(20.0, (float) $row->discount_value);
    }

    public function testFindActiveByCodeReturnsNullForExpiredCoupon(): void
    {
        $expired_yesterday = date('Y-m-d H:i:s', time() - 86400);
        $this->makeCoupon(['code' => 'EXPIRED', 'expires_at' => $expired_yesterday]);

        $row = $this->repo->findActiveByCode('EXPIRED');
        self::assertNull(
            $row,
            'A coupon past its expires_at must not be returned to checkout.'
        );
    }

    public function testFindActiveByCodeReturnsNullWhenCapHit(): void
    {
        $id = $this->makeCoupon(['code' => 'ONESHOT', 'max_uses' => 1]);

        // Bump used_count to the cap.
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'sikshya_coupons', ['used_count' => 1], ['id' => $id]);

        self::assertNull(
            $this->repo->findActiveByCode('ONESHOT'),
            'A coupon at its max_uses cap must not be returned to checkout.'
        );
    }

    public function testFindActiveByCodeIgnoresDisabledCoupons(): void
    {
        $this->makeCoupon(['code' => 'DISABLED', 'status' => 'disabled']);
        self::assertNull(
            $this->repo->findActiveByCode('DISABLED'),
            'Non-active coupons must not be returned to checkout.'
        );
    }

    public function testIncrementUsedCountAtomicallyAcceptsFirstUseHittingCap(): void
    {
        $id = $this->makeCoupon(['code' => 'ATOMIC', 'max_uses' => 1]);

        self::assertTrue(
            $this->repo->incrementUsedCount($id),
            'First use of a max_uses=1 coupon must succeed atomically.'
        );

        // Second concurrent use must fail at the SQL level.
        self::assertFalse(
            $this->repo->incrementUsedCount($id),
            'Second use of a max_uses=1 coupon must return false because the atomic WHERE clause no longer matches.'
        );

        global $wpdb;
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT used_count FROM {$wpdb->prefix}sikshya_coupons WHERE id = %d",
            $id
        ));
        self::assertSame(
            1,
            $count,
            'used_count must not exceed max_uses — the atomic conditional UPDATE is what prevents the race.'
        );
    }

    public function testIncrementUsedCountAtomicallyRefusesExpiredCoupon(): void
    {
        // This is the explicit TOCTOU regression: a coupon validated seconds
        // before expiry must not still be redeemable seconds after expiry.
        // The fix added `(expires_at IS NULL OR expires_at >= NOW())` to the
        // UPDATE so the entire decision is one row-level operation.
        $expired = date('Y-m-d H:i:s', time() - 60);
        $id = $this->makeCoupon(['code' => 'TOCTOU', 'expires_at' => $expired]);

        self::assertFalse(
            $this->repo->incrementUsedCount($id),
            'An expired coupon must not be redeemable even when findActiveByCode was bypassed — the SQL UPDATE guard is the last line of defense.'
        );

        global $wpdb;
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT used_count FROM {$wpdb->prefix}sikshya_coupons WHERE id = %d",
            $id
        ));
        self::assertSame(0, $count, 'used_count must NOT increment for an expired coupon.');
    }

    public function testIncrementUsedCountAcceptsUnlimitedCoupon(): void
    {
        $id = $this->makeCoupon(['code' => 'UNLIMITED', 'max_uses' => 0]);

        for ($i = 0; $i < 3; $i++) {
            self::assertTrue(
                $this->repo->incrementUsedCount($id),
                "Use #{$i} of an unlimited coupon must succeed."
            );
        }

        global $wpdb;
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT used_count FROM {$wpdb->prefix}sikshya_coupons WHERE id = %d",
            $id
        ));
        self::assertSame(3, $count);
    }

    public function testRecordRedemptionPersistsRow(): void
    {
        $coupon_id = $this->makeCoupon();
        $user_id = self::factory()->user->create();
        $order_id = 9001;

        $this->repo->recordRedemption($coupon_id, $user_id, $order_id);

        global $wpdb;
        $rows = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sikshya_coupon_redemptions WHERE coupon_id = %d AND user_id = %d AND order_id = %d",
            $coupon_id,
            $user_id,
            $order_id
        ));
        self::assertSame(1, $rows);
    }
}
