<?php

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Tables\AnalyticsTable;

/**
 * Write-only analytics event store (custom table).
 *
 * @package Sikshya\Database\Repositories
 */
final class AnalyticsRepository
{
    private string $table;

    public function __construct()
    {
        $this->table = AnalyticsTable::getTableName();
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
                'event_type' => (string) ($row['event_type'] ?? ''),
                'event_data' => (string) ($row['event_data'] ?? ''),
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                'course_id' => isset($row['course_id']) ? (int) $row['course_id'] : null,
                'session_id' => (string) ($row['session_id'] ?? ''),
                'ip_address' => (string) ($row['ip_address'] ?? ''),
                'user_agent' => (string) ($row['user_agent'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? current_time('mysql')),
            ],
            ['%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );
    }
}

