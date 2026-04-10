<?php

namespace Sikshya\Api;

use Sikshya\Database\Repositories\CertificateRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public certificate verification (no auth).
 *
 * @package Sikshya\Api
 */
class CertificatesPublicRoutes
{
    private CertificateRepository $certificates;

    public function __construct()
    {
        $this->certificates = new CertificateRepository();
    }

    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/public/certificates/verify', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'verify'],
                'permission_callback' => '__return_true',
                'args' => [
                    'code' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
    }

    public function verify(WP_REST_Request $request): WP_REST_Response
    {
        $code = (string) $request->get_param('code');
        $row = $this->certificates->findByVerificationCode($code);

        if (!$row || $row->status !== 'active') {
            return new WP_REST_Response(
                [
                    'ok' => false,
                    'code' => 'not_found',
                    'message' => __('Certificate not found or revoked.', 'sikshya'),
                ],
                404
            );
        }

        $course_title = get_the_title((int) $row->course_id);
        $user = get_userdata((int) $row->user_id);

        return new WP_REST_Response(
            [
                'ok' => true,
                'data' => [
                    'certificate_number' => (string) $row->certificate_number,
                    'issued_date' => (string) $row->issued_date,
                    'course_id' => (int) $row->course_id,
                    'course_title' => $course_title ?: '',
                    'recipient' => $user ? $user->display_name : '',
                ],
            ],
            200
        );
    }
}
