<?php

namespace Sikshya\Database\Tables;

final class ReviewsTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_reviews';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            rating int(11) NOT NULL,
            review_text text NULL,
            is_approved tinyint(1) NOT NULL DEFAULT 0,
            reply_text text NULL,
            reply_user_id bigint(20) NULL,
            reply_created_at datetime NULL,
            reported_count int(11) NOT NULL DEFAULT 0,
            last_reported_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY rating (rating),
            KEY is_approved (is_approved),
            KEY course_status_created (course_id, is_approved, created_at),
            KEY reported_count (reported_count),
            UNIQUE KEY unique_review (user_id, course_id)
        ) {$charset_collate};";
    }
}

