<?php

namespace Sikshya\Api;

use Sikshya\Services\CertificateQueryService;
use WP_REST_Request;
use WP_REST_Response;

class CertificateService
{
    private CertificateQueryService $svc;

    public function __construct(?CertificateQueryService $svc = null)
    {
        $this->svc = $svc ?: new CertificateQueryService();
    }

    public function getCertificates(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = (int) $request->get_param('user_id');
        $course_id = (int) $request->get_param('course_id');
        $certificates = $this->svc->list($user_id, $course_id);

        return new WP_REST_Response([
            'certificates' => array_map([$this, 'formatCertificate'], $certificates),
        ]);
    }

    private function formatCertificate($certificate): array
    {
        return [
            'id' => $certificate->id,
            'user_id' => $certificate->user_id,
            'course_id' => $certificate->course_id,
            'certificate_number' => $certificate->certificate_number,
            'issued_date' => $certificate->issued_date,
            'expiry_date' => $certificate->expiry_date,
            'status' => $certificate->status,
            'download_url' => $certificate->download_url,
        ];
    }
}
