<?php

namespace Sikshya\Database\Tables;

final class OrdersTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_orders';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            status varchar(32) NOT NULL DEFAULT 'pending',
            currency varchar(3) NOT NULL DEFAULT 'USD',
            subtotal decimal(12,2) NOT NULL DEFAULT 0.00,
            discount_total decimal(12,2) NOT NULL DEFAULT 0.00,
            total decimal(12,2) NOT NULL DEFAULT 0.00,
            gateway varchar(32) NOT NULL DEFAULT '',
            gateway_intent_id varchar(255) NULL,
            public_token varchar(32) NULL,
            coupon_id bigint(20) NULL,
            meta longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY gateway_intent_id (gateway_intent_id),
            UNIQUE KEY public_token (public_token)
        ) {$charset_collate};";
    }
}

