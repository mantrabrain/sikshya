<?php

namespace Sikshya\Database\Tables;

final class AssignmentSubmissionsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_assignment_submissions';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            assignment_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            content longtext NULL,
            attachment_ids longtext NULL,
            status varchar(50) NOT NULL DEFAULT 'submitted',
            grade decimal(8,2) NULL,
            feedback longtext NULL,
            submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            graded_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY assignment_id (assignment_id),
            KEY course_id (course_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY submitted_at (submitted_at)
            ,UNIQUE KEY unique_submission (assignment_id, user_id)
        ) {$charset_collate};";
    }
}

