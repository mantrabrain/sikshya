<?php

declare(strict_types=1);

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Repositories\Contracts\RepositoryInterface;
use Sikshya\Database\Tables\AchievementsTable;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Reads + writes against the {@see AchievementsTable} (`sikshya_achievements`).
 *
 * Achievements are awarded idempotently — a `(user_id, achievement_type)` pair only
 * earns once. Callers ask {@see awardOnce()} which short-circuits when the row
 * already exists.
 *
 * @package Sikshya\Database\Repositories
 */
final class AchievementsRepository implements RepositoryInterface
{
    private string $table_name;

    public function __construct()
    {
        $this->table_name = AchievementsTable::getTableName();
    }

    public function tableExists(): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name)) === $this->table_name;
    }

    /**
     * Whether the learner has already earned this achievement type.
     */
    public function hasAchievement(int $user_id, string $type): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        global $wpdb;
        $n = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d AND achievement_type = %s",
                $user_id,
                $type
            )
        );

        return $n > 0;
    }

    /**
     * Award an achievement to a learner. No-op when they already have it.
     *
     * @return int New row ID, or 0 if the achievement already existed or insert failed.
     */
    public function awardOnce(int $user_id, string $type, string $name, string $description = '', string $badge_url = ''): int
    {
        if ($user_id <= 0 || $type === '' || $name === '') {
            return 0;
        }
        if (!$this->tableExists()) {
            return 0;
        }
        if ($this->hasAchievement($user_id, $type)) {
            return 0;
        }

        global $wpdb;
        $result = $wpdb->insert(
            $this->table_name,
            [
                'user_id' => $user_id,
                'achievement_type' => $type,
                'achievement_name' => $name,
                'description' => $description !== '' ? $description : null,
                'badge_url' => $badge_url !== '' ? $badge_url : null,
                'earned_date' => current_time('mysql'),
            ]
        );

        return $result ? (int) $wpdb->insert_id : 0;
    }

    /**
     * @return array<int, object>
     */
    public function findByUser(int $user_id, int $limit = 100): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY earned_date DESC LIMIT %d",
                $user_id,
                $limit
            )
        );

        return is_array($rows) ? $rows : [];
    }

    public function countByUser(int $user_id): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d",
                $user_id
            )
        );
    }
}
