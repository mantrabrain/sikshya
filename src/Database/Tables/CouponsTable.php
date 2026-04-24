<?php

namespace Sikshya\Database\Tables;

final class CouponsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_coupons';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code varchar(64) NOT NULL,
            discount_type varchar(16) NOT NULL DEFAULT 'percent',
            discount_value decimal(12,2) NOT NULL DEFAULT 0.00,
            max_uses int(11) NOT NULL DEFAULT 0,
            used_count int(11) NOT NULL DEFAULT 0,
            expires_at datetime NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY status (status)
        ) {$charset_collate};";
    }
}

