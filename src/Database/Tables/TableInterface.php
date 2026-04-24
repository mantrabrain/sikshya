<?php

namespace Sikshya\Database\Tables;

interface TableInterface
{
    /**
     * Base table name without prefix.
     */
    public static function baseName(): string;

    /**
     * Fully qualified table name with the active WP prefix.
     */
    public static function name(): string;

    /**
     * Alias of {@see name()}; use for explicit call sites (queries, uninstall).
     */
    public static function getTableName(): string;

    /**
     * dbDelta-friendly CREATE TABLE statement.
     *
     * @param string $charset_collate result of $wpdb->get_charset_collate()
     */
    public static function createSql(string $charset_collate): string;
}

