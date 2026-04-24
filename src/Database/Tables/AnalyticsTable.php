<?php

namespace Sikshya\Database\Tables;

final class AnalyticsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_analytics';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(191) NOT NULL,
            event_data longtext NULL,
            user_id bigint(20) NULL,
            course_id bigint(20) NULL,
            session_id varchar(191) NULL,
            ip_address varchar(64) NULL,
            user_agent text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
    }
}

