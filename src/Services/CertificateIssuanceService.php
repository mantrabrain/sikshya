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
        $settings = Settings::getRaw('sikshya_course_settings', []);

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
        // 64-char URL-safe verification hash (hex): good for QR + verifiable links.
        // 32 random bytes -> 64 hex chars.
        $verification = '';
        try {
            $verification = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            // Fallback: still URL-safe, but keep length stable.
            $verification = bin2hex(openssl_random_pseudo_bytes(32) ?: random_bytes(32));
        }

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

        if ($id > 0) {
            /**
             * After a certificate row is stored (Pro may attach download URLs, QR assets, etc.).
             *
             * @param int $id Certificate row id.
             * @param int $user_id Learner.
             * @param int $course_id Course.
             * @param string $certificate_number Human-readable serial.
             * @param string $verification_code Secret verification token.
             * @param int $template_post_id Certificate template post (0 if none).
             */
            do_action('sikshya_certificate_row_created', $id, $user_id, $course_id, $number, $verification, $template_post_id > 0 ? $template_post_id : 0);
        }

        return $id > 0 ? $id : null;
    }
}
