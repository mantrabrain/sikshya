<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Tables\LogsTable;

/**
 * Write-only log store (custom table).
 *
 * @package Sikshya\Database\Repositories
 */
final class LogRepository
{
    private string $table;

    public function __construct()
    {
        $this->table = LogsTable::getTableName();
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insert(array $row): void
    {
        global $wpdb;

        $wpdb->insert(
            $this->table,
            [
                'level' => (string) ($row['level'] ?? 'info'),
                'message' => (string) ($row['message'] ?? ''),
                'context' => (string) ($row['context'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? current_time('mysql')),
            ],
            ['%s', '%s', '%s', '%s']
        );
    }
}

