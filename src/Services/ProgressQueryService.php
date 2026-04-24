<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\ProgressRepository;

/**
 * Read-only progress queries for REST.
 */
final class ProgressQueryService
{
    private ProgressRepository $repo;

    public function __construct(?ProgressRepository $repo = null)
    {
        $this->repo = $repo ?: new ProgressRepository();
    }

    /**
     * @return array<int, object>
     */
    public function list(int $user_id = 0, int $course_id = 0): array
    {
        return $this->repo->listFiltered($user_id, $course_id, 500, 0);
    }
}

