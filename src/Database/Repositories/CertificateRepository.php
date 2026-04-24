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

    public function countByUser(int $user_id): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d",
                $user_id
            )
        );
    }

    /**
     * @return array<int, object>
     */
    public function findByUserPaged(int $user_id, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY issued_date DESC LIMIT %d OFFSET %d",
                $user_id,
                $limit,
                $offset
            )
        );
    }

    public function findByIdForUser(int $id, int $user_id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d AND user_id = %d LIMIT 1",
                $id,
                $user_id
            )
        );

        return $row ?: null;
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
                $id
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
     * Partial update for issued certificate rows.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $fields = [];
        $formats = [];

        if (array_key_exists('download_url', $data)) {
            $fields['download_url'] = esc_url_raw((string) $data['download_url']);
            $formats[] = '%s';
        }
        if (array_key_exists('certificate_data', $data)) {
            $fields['certificate_data'] = $data['certificate_data'] !== null ? wp_json_encode($data['certificate_data']) : null;
            $formats[] = '%s';
        }
        if (array_key_exists('certificate_number', $data)) {
            $fields['certificate_number'] = sanitize_text_field((string) $data['certificate_number']);
            $formats[] = '%s';
        }
        if (array_key_exists('status', $data)) {
            $fields['status'] = sanitize_text_field((string) $data['status']);
            $formats[] = '%s';
        }

        if ($fields === []) {
            return false;
        }

        return false !== $wpdb->update(
            $this->table_name,
            $fields,
            ['id' => $id],
            $formats,
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
