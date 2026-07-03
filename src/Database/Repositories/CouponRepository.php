<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Tables\CouponsTable;
use Sikshya\Database\Tables\CouponRedemptionsTable;

/**
 * Coupons + redemptions.
 *
 * @package Sikshya\Database\Repositories
 */
class CouponRepository
{
    private string $coupons;

    private string $redemptions;

    public function __construct()
    {
        $this->coupons = CouponsTable::getTableName();
        $this->redemptions = CouponRedemptionsTable::getTableName();
    }

    public function findActiveByCode(string $code): ?object
    {
        global $wpdb;

        $code = strtoupper(sanitize_text_field($code));
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->coupons} WHERE code = %s AND status = %s LIMIT 1",
                $code,
                'active'
            )
        );

        if (!$row) {
            return null;
        }

        if (!empty($row->expires_at) && strtotime((string) $row->expires_at) < time()) {
            return null;
        }

        if ((int) $row->max_uses > 0 && (int) $row->used_count >= (int) $row->max_uses) {
            return null;
        }

        return $row;
    }

    /**
     * Atomically increment the coupon's redemption counter, refusing the
     * increment when `max_uses` has already been reached. Returns `true` when
     * the row was bumped, `false` when the cap was hit (i.e. `affected_rows`
     * is 0).
     *
     * Why this isn't a bare `SET used_count = used_count + 1`: the previous
     * implementation TOCTOU-raced under concurrent checkouts. Two parallel
     * uses of a `max_uses = 1` coupon would both pass the pre-check in
     * {@see findActiveByCode()}, then both bump the counter past the cap.
     * The condition `(max_uses = 0 OR used_count < max_uses)` makes the
     * check + bump a single row-level operation MySQL serialises for us.
     */
    public function incrementUsedCount(int $coupon_id): bool
    {
        global $wpdb;
        // The expires_at guard closes the same TOCTOU window as the max_uses
        // check: a coupon validated seconds before expiry must not still be
        // bumpable a few seconds later when checkout actually completes.
        $rows = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->coupons} SET used_count = used_count + 1
                 WHERE id = %d
                   AND (max_uses = 0 OR used_count < max_uses)
                   AND (expires_at IS NULL OR expires_at >= NOW())",
                $coupon_id
            )
        );

        return (int) $rows === 1;
    }

    public function recordRedemption(int $coupon_id, int $user_id, int $order_id): void
    {
        global $wpdb;

        $wpdb->insert(
            $this->redemptions,
            [
                'coupon_id' => $coupon_id,
                'user_id' => $user_id,
                'order_id' => $order_id,
                'redeemed_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Atomically enforce a per-user redemption cap and insert the row.
     *
     * SECURITY: {@see recordRedemption} is an unconditional INSERT — pairing
     * it with a preceding SELECT (`countRedemptionsByUser`) is a classic
     * TOCTOU: a user who kicks off two parallel checkouts of a
     * `per_user_limit = 1` "first-order 100% off" coupon can pass the
     * count check twice and land two redemption rows before either
     * checkout commits, so both orders get discounted for a coupon they
     * were allowed to use once.
     *
     * This method wraps the count-then-insert in a transaction with
     * `SELECT ... FOR UPDATE`, taking a row lock on the user's existing
     * redemption rows for this coupon. Any concurrent transaction hits
     * the same lock and serialises behind us — one of the parallel
     * checkouts sees `count >= limit` after the winner commits and
     * returns false. The caller (see `CheckoutService::createOrder`)
     * treats a false return as a hard failure and rolls back the
     * `incrementUsedCount` bump.
     *
     * `$per_user_limit <= 0` means unlimited — plain insert, no lock.
     */
    public function tryRecordRedemption(int $coupon_id, int $user_id, int $order_id, int $per_user_limit): bool
    {
        global $wpdb;

        if ($coupon_id <= 0 || $user_id <= 0) {
            return false;
        }

        if ($per_user_limit <= 0) {
            // No per-user cap — fall back to the plain insert.
            $ok = $wpdb->insert(
                $this->redemptions,
                [
                    'coupon_id' => $coupon_id,
                    'user_id' => $user_id,
                    'order_id' => $order_id,
                    'redeemed_at' => current_time('mysql'),
                ]
            );
            return $ok !== false;
        }

        $wpdb->query('START TRANSACTION');

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->redemptions}
                 WHERE coupon_id = %d AND user_id = %d FOR UPDATE",
                $coupon_id,
                $user_id
            )
        );

        if ($count >= $per_user_limit) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $ok = $wpdb->insert(
            $this->redemptions,
            [
                'coupon_id' => $coupon_id,
                'user_id' => $user_id,
                'order_id' => $order_id,
                'redeemed_at' => current_time('mysql'),
            ]
        );

        if ($ok === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $wpdb->query('COMMIT');
        return true;
    }

    /**
     * Rollback of {@see incrementUsedCount}. Used when a downstream step
     * (e.g. per-user redemption cap enforcement) fails after we already
     * bumped the global used_count. `GREATEST(0, ...)` prevents any drift
     * from decrementing below zero on repeated rollback attempts.
     */
    public function decrementUsedCount(int $coupon_id): void
    {
        global $wpdb;

        if ($coupon_id <= 0) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->coupons} SET used_count = GREATEST(0, used_count - 1) WHERE id = %d",
                $coupon_id
            )
        );
    }

    public function countRedemptionsByUser(int $coupon_id, int $user_id): int
    {
        global $wpdb;

        if ($coupon_id <= 0 || $user_id <= 0 || !$this->tableExists()) {
            return 0;
        }

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->redemptions)) !== $this->redemptions) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->redemptions} WHERE coupon_id = %d AND user_id = %d",
                $coupon_id,
                $user_id
            )
        );
    }

    /**
     * Apply discount to a subtotal. Returns [discount, total].
     *
     * @return array{0: float, 1: float}
     */
    public function applyToAmount(object $coupon, float $subtotal): array
    {
        $discount = 0.00;
        $type = (string) $coupon->discount_type;
        $value = (float) $coupon->discount_value;

        if ($type === 'percent') {
            $discount = round($subtotal * min(100, max(0, $value)) / 100, 2);
        } else {
            $discount = min($subtotal, max(0, $value));
        }

        $total = max(0, round($subtotal - $discount, 2));

        return [$discount, $total];
    }

    public function tableExists(): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->coupons)) === $this->coupons;
    }

    /**
     * @return array<int, object>
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        global $wpdb;

        if (!$this->tableExists()) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->coupons} ORDER BY id DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createAdminCoupon(array $data): int
    {
        global $wpdb;

        $code = strtoupper(sanitize_text_field((string) ($data['code'] ?? '')));
        $type = sanitize_key((string) ($data['discount_type'] ?? 'percent'));
        if (!in_array($type, ['percent', 'fixed'], true)) {
            $type = 'percent';
        }

        $wpdb->insert(
            $this->coupons,
            [
                'code' => $code,
                'discount_type' => $type,
                'discount_value' => (float) ($data['discount_value'] ?? 0),
                'max_uses' => isset($data['max_uses']) ? (int) $data['max_uses'] : 0,
                'used_count' => 0,
                'expires_at' => !empty($data['expires_at']) ? sanitize_text_field((string) $data['expires_at']) : null,
                'status' => sanitize_key((string) ($data['status'] ?? 'active')),
            ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an existing coupon row (admin UI).
     *
     * @param array<string, mixed> $data
     */
    public function updateAdminCoupon(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0 || !$this->tableExists()) {
            return false;
        }

        $patch = [];
        if (array_key_exists('code', $data)) {
            $patch['code'] = strtoupper(sanitize_text_field((string) $data['code']));
        }
        if (array_key_exists('discount_type', $data)) {
            $t = sanitize_key((string) $data['discount_type']);
            $patch['discount_type'] = in_array($t, ['percent', 'fixed'], true) ? $t : 'percent';
        }
        if (array_key_exists('discount_value', $data)) {
            $patch['discount_value'] = (float) $data['discount_value'];
        }
        if (array_key_exists('max_uses', $data)) {
            $patch['max_uses'] = (int) $data['max_uses'];
        }
        if (array_key_exists('expires_at', $data)) {
            $exp = $data['expires_at'];
            $patch['expires_at'] = ($exp === null || $exp === '') ? null : sanitize_text_field((string) $exp);
        }
        if (array_key_exists('status', $data)) {
            $patch['status'] = sanitize_key((string) $data['status']);
        }

        if ($patch === []) {
            return true;
        }

        return false !== $wpdb->update($this->coupons, $patch, ['id' => $id]);
    }
}
