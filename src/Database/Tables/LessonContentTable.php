<?php

namespace Sikshya\Database\Tables;

final class LessonContentTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_lesson_content';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            lesson_id bigint(20) NOT NULL,
            content_type varchar(50) NOT NULL DEFAULT 'text',
            content_data longtext NOT NULL,
            file_url varchar(500) NULL,
            duration int(11) NOT NULL DEFAULT 0,
            order_number int(11) NOT NULL DEFAULT 0,
            is_required tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lesson_id (lesson_id),
            KEY content_type (content_type),
            KEY order_number (order_number)
        ) {$charset_collate};";
    }
}

