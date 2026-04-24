<?php

namespace Sikshya\Database\Tables;

final class EnrollmentsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_enrollments';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'enrolled',
            enrolled_date datetime NOT NULL,
            completed_date datetime NULL,
            payment_method varchar(100) NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            transaction_id varchar(255) NULL,
            progress decimal(5,2) NOT NULL DEFAULT 0.00,
            notes text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY status (status),
            KEY enrolled_date (enrolled_date),
            UNIQUE KEY unique_enrollment (user_id, course_id)
        ) {$charset_collate};";
    }
}

