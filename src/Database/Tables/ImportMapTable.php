<?php

namespace Sikshya\Database\Tables;

/**
 * What came from where, when importing from another LMS.
 *
 * Lives with the other core tables — rather than beside the importer — so it
 * is created on activation and dropped on uninstall with everything else.
 * {@see \Sikshya\Import\ImportMap} is what reads and writes it.
 *
 * @package Sikshya\Database\Tables
 */
final class ImportMapTable extends AbstractTable
{
    public static function baseName(): string
    {
        return 'sikshya_import_map';
    }

    public static function createSql(string $charset_collate): string
    {
        $t = static::name();

        return "CREATE TABLE {$t} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source varchar(32) NOT NULL,
            object_type varchar(32) NOT NULL,
            source_id bigint(20) UNSIGNED NOT NULL,
            sikshya_id bigint(20) UNSIGNED NOT NULL,
            run_id varchar(36) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY source_object (source, object_type, source_id),
            KEY sikshya_id (sikshya_id),
            KEY run_id (run_id)
        ) {$charset_collate};";
    }
}
