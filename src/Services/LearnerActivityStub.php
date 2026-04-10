<?php

namespace Sikshya\Services;

/**
 * Placeholder until activity module is wired.
 *
 * @package Sikshya\Services
 */
final class LearnerActivityStub
{
    /**
     * @return array<int, mixed>
     */
    public function getUserActivities(int $user_id, int $limit_or_per_page = 10, int $page = 1): array
    {
        unset($user_id, $limit_or_per_page, $page);

        return [];
    }
}
