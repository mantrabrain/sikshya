<?php

namespace Sikshya\Database\Tables;

final class QuizAttemptItemsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_quiz_attempt_items';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            attempt_id bigint(20) NOT NULL,
            question_id bigint(20) NOT NULL,
            answer longtext NULL,
            is_correct tinyint(1) NOT NULL DEFAULT 0,
            points_earned decimal(8,2) NOT NULL DEFAULT 0.00,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attempt_id (attempt_id),
            KEY question_id (question_id)
        ) {$charset_collate};";
    }
}

