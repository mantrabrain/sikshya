<?php

namespace Sikshya\Database\Tables;

final class LogsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_logs';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(32) NOT NULL DEFAULT 'info',
            message longtext NOT NULL,
            context longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at)
        ) {$charset_collate};";
    }
}

