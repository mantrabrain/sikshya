<?php

namespace Sikshya\Database\Tables;

abstract class AbstractTable implements TableInterface
{
    public static function getTableName(): string
    {
        return static::name();
    }

    public static function name(): string
    {
        global $wpdb;
        return $wpdb->prefix . static::baseName();
    }
}

