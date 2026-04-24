<?php

namespace Sikshya\Database\Tables;

final class PaymentsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_payments';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            payment_method varchar(100) NOT NULL,
            transaction_id varchar(255) NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            payment_date datetime NOT NULL,
            refund_date datetime NULL,
            refund_amount decimal(10,2) NULL,
            gateway_response longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY transaction_id (transaction_id),
            KEY status (status),
            KEY payment_date (payment_date)
        ) {$charset_collate};";
    }
}

