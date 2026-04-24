<?php

namespace Sikshya\Database\Tables;

final class CouponRedemptionsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_coupon_redemptions';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            coupon_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            order_id bigint(20) NOT NULL,
            redeemed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY coupon_id (coupon_id),
            KEY order_id (order_id)
        ) {$charset_collate};";
    }
}

