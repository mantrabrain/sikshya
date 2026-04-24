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

    public function incrementUsedCount(int $coupon_id): void
    {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->coupons} SET used_count = used_count + 1 WHERE id = %d",
                $coupon_id
            )
        );
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
}
