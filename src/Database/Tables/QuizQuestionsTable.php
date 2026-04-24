<?php

namespace Sikshya\Database\Tables;

final class QuizQuestionsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_quiz_questions';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) NOT NULL,
            question_text longtext NOT NULL,
            question_type varchar(50) NOT NULL DEFAULT 'multiple_choice',
            options longtext NULL,
            correct_answer longtext NULL,
            points int(11) NOT NULL DEFAULT 1,
            order_number int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id),
            KEY question_type (question_type),
            KEY order_number (order_number)
        ) {$charset_collate};";
    }
}

