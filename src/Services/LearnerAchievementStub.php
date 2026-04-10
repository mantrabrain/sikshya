<?php

namespace Sikshya\Services;

/**
 * Placeholder until achievements module is wired.
 *
 * @package Sikshya\Services
 */
final class LearnerAchievementStub
{
    /**
     * @return array<int, mixed>
     */
    public function getUserAchievements(int $user_id): array
    {
        unset($user_id);

        return [];
    }

    public function getUserAchievementsCount(int $user_id): int
    {
        unset($user_id);

        return 0;
    }
}
