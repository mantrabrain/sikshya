<?php

namespace Sikshya\Services;

/**
 * Placeholder until quiz stats aggregation is wired for the learner dashboard.
 *
 * @package Sikshya\Services
 */
final class LearnerQuizStatsStub
{
    public function getUserQuizzesCount(int $user_id): int
    {
        unset($user_id);

        return 0;
    }

    public function getPassedQuizzesCount(int $user_id): int
    {
        unset($user_id);

        return 0;
    }

    public function getAverageQuizScore(int $user_id): float
    {
        unset($user_id);

        return 0.0;
    }
}
