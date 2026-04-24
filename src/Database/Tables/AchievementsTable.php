<?php

namespace Sikshya\Database\Tables;

final class AchievementsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_achievements';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            achievement_type varchar(100) NOT NULL,
            achievement_name varchar(255) NOT NULL,
            description text NULL,
            badge_url varchar(500) NULL,
            earned_date datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY achievement_type (achievement_type),
            KEY earned_date (earned_date)
        ) {$charset_collate};";
    }
}

