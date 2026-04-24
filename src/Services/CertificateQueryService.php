<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\CertificateRepository;

/**
 * Read-only certificate queries for REST.
 */
final class CertificateQueryService
{
    private CertificateRepository $repo;

    public function __construct(?CertificateRepository $repo = null)
    {
        $this->repo = $repo ?: new CertificateRepository();
    }

    /**
     * @return array<int, object>
     */
    public function list(int $user_id = 0, int $course_id = 0): array
    {
        return $this->repo->listFiltered($user_id, $course_id, 500);
    }
}

