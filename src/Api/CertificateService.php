<?php

namespace Sikshya\Api;

use WP_REST_Request;
use WP_REST_Response;

class CertificateService
{
    public function getCertificates(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sikshya_certificates';
        $user_id = $request->get_param('user_id');
        $course_id = $request->get_param('course_id');

        $where = [];
        $prepare_values = [];

        if ($user_id) {
            $where[] = 'user_id = %d';
            $prepare_values[] = $user_id;
        }

        if ($course_id) {
            $where[] = 'course_id = %d';
            $prepare_values[] = $course_id;
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT * FROM {$table} {$where_clause} ORDER BY issued_date DESC";

        if (!empty($prepare_values)) {
            $query = $wpdb->prepare($query, ...$prepare_values);
        }

        $certificates = $wpdb->get_results($query);

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
