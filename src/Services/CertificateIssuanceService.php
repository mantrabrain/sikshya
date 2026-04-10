<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\CertificateRepository;

/**
 * Creates rows in sikshya_certificates when a learner completes a course.
 *
 * @package Sikshya\Services
 */
final class CertificateIssuanceService
{
    private CertificateRepository $certificates;

    public function __construct(?CertificateRepository $certificates = null)
    {
        $this->certificates = $certificates ?? new CertificateRepository();
    }

    public function certificatesEnabled(): bool
    {
        $settings = get_option('sikshya_course_settings', []);

        return !empty($settings['enable_certificates']);
    }

    /**
     * Issue certificate if settings allow and none active exists.
     */
    public function issueIfEnabled(int $user_id, int $course_id): ?int
    {
        if (!$this->certificatesEnabled()) {
            return null;
        }

        $existing = $this->certificates->findByUserAndCourse($user_id, $course_id);
        if ($existing && $existing->status === 'active') {
            return (int) $existing->id;
        }

        $template_post_id = (int) get_post_meta($course_id, '_sikshya_certificate', true);
        if ($template_post_id <= 0) {
            $template_post_id = 0;
        }

        $number = sprintf('SK-%d-%d-%s', $user_id, $course_id, gmdate('Ymd'));
        $verification = strtolower(wp_generate_password(24, false, false));

        $id = $this->certificates->create(
            [
                'user_id' => $user_id,
                'course_id' => $course_id,
                'certificate_number' => $number,
                'issued_date' => current_time('mysql'),
                'expiry_date' => null,
                'status' => 'active',
                'download_url' => '',
                'certificate_data' => [
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'issued_at' => current_time('mysql'),
                ],
                'template_post_id' => $template_post_id > 0 ? $template_post_id : null,
                'verification_code' => $verification,
            ]
        );

        return $id > 0 ? $id : null;
    }
}
