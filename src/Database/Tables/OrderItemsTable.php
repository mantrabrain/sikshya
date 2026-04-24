<?php

namespace Sikshya\Database\Tables;

final class OrderItemsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_order_items';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            unit_price decimal(12,2) NOT NULL DEFAULT 0.00,
            line_total decimal(12,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY course_id (course_id)
        ) {$charset_collate};";
    }
}

