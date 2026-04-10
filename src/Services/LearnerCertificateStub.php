<?php

namespace Sikshya\Services;

/**
 * Placeholder until certificate learner API is fully wired.
 *
 * @package Sikshya\Services
 */
final class LearnerCertificateStub
{
    /**
     * @return array<int, mixed>
     */
    public function getUserCertificates(int $user_id, int $per_page = 10, int $page = 1): array
    {
        unset($user_id, $per_page, $page);

        return [];
    }

    public function getUserCertificatesCount(int $user_id): int
    {
        unset($user_id);

        return 0;
    }
}
