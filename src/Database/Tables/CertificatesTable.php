<?php

namespace Sikshya\Database\Tables;

final class CertificatesTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_certificates';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();
        return "CREATE TABLE {$t} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            certificate_number varchar(100) NOT NULL,
            issued_date datetime NOT NULL,
            expiry_date datetime NULL,
            status varchar(50) NOT NULL DEFAULT 'active',
            download_url varchar(500) NULL,
            certificate_data longtext NULL,
            template_post_id bigint(20) NULL,
            verification_code varchar(64) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY certificate_number (certificate_number),
            KEY verification_code (verification_code),
            KEY status (status),
            UNIQUE KEY unique_certificate (user_id, course_id)
        ) {$charset_collate};";
    }
}

