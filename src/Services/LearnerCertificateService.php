<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\CertificateRepository;

/**
 * Learner certificates (table-backed).
 *
 * @package Sikshya\Services
 */
final class LearnerCertificateService
{
    private CertificateRepository $repo;

    public function __construct(?CertificateRepository $repo = null)
    {
        $this->repo = $repo ?? new CertificateRepository();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserCertificates(int $user_id, int $per_page = 50, int $page = 1): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return [];
        }

        if (!Settings::isTruthy(Settings::get('students_can_download_certificates', '1'))) {
            return [];
        }

        $per_page = max(1, min(200, $per_page));
        $page = max(1, $page);
        $offset = ($page - 1) * $per_page;

        $rows = $this->repo->findByUserPaged($user_id, $per_page, $offset);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'course_id' => (int) $r->course_id,
                'course_title' => get_the_title((int) $r->course_id),
                'certificate_number' => (string) $r->certificate_number,
                'issued_date' => (string) $r->issued_date,
                'status' => (string) $r->status,
                'download_url' => (string) ($r->download_url ?? ''),
                'verification_code' => (string) ($r->verification_code ?? ''),
            ];
        }

        return $out;
    }

    public function getUserCertificatesCount(int $user_id): int
    {
        if (!Settings::isTruthy(Settings::get('students_can_download_certificates', '1'))) {
            return 0;
        }

        return $this->repo->countByUser(absint($user_id));
    }

    /**
     * @return array{success: bool, url?: string, message?: string}
     */
    public function downloadCertificate(int $certificate_id, int $user_id): array
    {
        $certificate_id = absint($certificate_id);
        $user_id = absint($user_id);
        if ($certificate_id <= 0 || $user_id <= 0) {
            return ['success' => false, 'message' => __('Invalid request.', 'sikshya')];
        }

        if (!Settings::isTruthy(Settings::get('students_can_download_certificates', '1'))) {
            return ['success' => false, 'message' => __('Certificate downloads are disabled in LMS settings.', 'sikshya')];
        }

        $row = $this->repo->findByIdForUser($certificate_id, $user_id);
        if (!$row) {
            return ['success' => false, 'message' => __('Certificate not found.', 'sikshya')];
        }

        $url = (string) ($row->download_url ?? '');
        if ($url === '') {
            return ['success' => false, 'message' => __('Certificate download is not available yet.', 'sikshya')];
        }

        return ['success' => true, 'url' => $url];
    }
}

