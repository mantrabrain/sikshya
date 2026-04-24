<?php

namespace Sikshya\Database\Tables;

final class ProgressTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_progress';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            lesson_id bigint(20) NULL,
            quiz_id bigint(20) NULL,
            status varchar(50) NOT NULL DEFAULT 'in_progress',
            percentage decimal(5,2) NOT NULL DEFAULT 0.00,
            time_spent int(11) NOT NULL DEFAULT 0,
            completed_date datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY lesson_id (lesson_id),
            KEY quiz_id (quiz_id),
            KEY status (status),
            UNIQUE KEY unique_progress (user_id, course_id, lesson_id, quiz_id)
        ) {$charset_collate};";
    }
}

