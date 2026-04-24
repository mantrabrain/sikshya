<?php

namespace Sikshya\Database\Tables;

final class QuizAttemptsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_quiz_attempts';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            quiz_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            attempt_number int(11) NOT NULL DEFAULT 1,
            score decimal(5,2) NOT NULL DEFAULT 0.00,
            total_questions int(11) NOT NULL DEFAULT 0,
            correct_answers int(11) NOT NULL DEFAULT 0,
            time_taken int(11) NOT NULL DEFAULT 0,
            status varchar(50) NOT NULL DEFAULT 'in_progress',
            started_at datetime NOT NULL,
            completed_at datetime NULL,
            answers_data longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY quiz_id (quiz_id),
            KEY course_id (course_id),
            KEY status (status),
            KEY started_at (started_at)
        ) {$charset_collate};";
    }
}

