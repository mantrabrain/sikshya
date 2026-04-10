<?php

namespace Sikshya\Database\Repositories;

/**
 * Issued certificates (custom table).
 *
 * @package Sikshya\Database\Repositories
 */
class CertificateRepository
{
    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sikshya_certificates';
    }

    public function findByVerificationCode(string $code): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE verification_code = %s LIMIT 1",
                $code
            )
        );

        return $row ?: null;
    }

    public function findByUserAndCourse(int $user_id, int $course_id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d AND course_id = %d LIMIT 1",
                $user_id,
                $course_id
            )
        );

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        global $wpdb;

        $defaults = [
            'user_id' => 0,
            'course_id' => 0,
            'certificate_number' => '',
            'issued_date' => current_time('mysql'),
            'expiry_date' => null,
            'status' => 'active',
            'download_url' => '',
            'certificate_data' => null,
            'template_post_id' => null,
            'verification_code' => null,
        ];
        $data = wp_parse_args($data, $defaults);

        $wpdb->insert(
            $this->table_name,
            [
                'user_id' => (int) $data['user_id'],
                'course_id' => (int) $data['course_id'],
                'certificate_number' => sanitize_text_field((string) $data['certificate_number']),
                'issued_date' => $data['issued_date'],
                'expiry_date' => $data['expiry_date'],
                'status' => sanitize_text_field((string) $data['status']),
                'download_url' => esc_url_raw((string) $data['download_url']),
                'certificate_data' => $data['certificate_data'] !== null ? wp_json_encode($data['certificate_data']) : null,
                'template_post_id' => $data['template_post_id'] ? (int) $data['template_post_id'] : null,
                'verification_code' => $data['verification_code'] ? sanitize_text_field((string) $data['verification_code']) : null,
            ]
        );

        return (int) $wpdb->insert_id;
    }

    public function updateStatus(int $id, string $status): bool
    {
        global $wpdb;

        return false !== $wpdb->update(
            $this->table_name,
            ['status' => sanitize_text_field($status)],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * @return array<int, object>
     */
    public function findAllPaged(int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY issued_date DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }
}
