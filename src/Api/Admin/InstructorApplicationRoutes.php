<?php

declare(strict_types=1);

namespace Sikshya\Api\Admin;

use Sikshya\Api\JwtAuthService;
use Sikshya\Services\InstructorApplicationsService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Instructor-application admin routes — list, approve, reject.
 *
 * Extracted from {@see \Sikshya\Api\AdminRestRoutes}. Owns `/sikshya/v1/admin/instructor-applications`
 * (GET) and the per-id approve/reject sub-routes. Carries its own permission callback
 * ({@see self::permissionInstructorApplications()}) because the cap check is `manage_sikshya OR
 * manage_options` — slightly more permissive than the staff-backend gate on {@see AbstractAdminRestController}.
 *
 * Route paths and response shapes preserved 1:1 with the original implementation.
 *
 * @package Sikshya\Api\Admin
 */
final class InstructorApplicationRoutes extends AbstractAdminRestController
{
    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/admin/instructor-applications', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listInstructorApplications'],
                'permission_callback' => [$this, 'permissionInstructorApplications'],
                'args' => [
                    'page' => [
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1,
                    ],
                    'per_page' => [
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100,
                    ],
                    'status' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'search' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/instructor-applications/(?P<id>\\d+)/approve', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'approveInstructorApplication'],
                'permission_callback' => [$this, 'permissionInstructorApplications'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/instructor-applications/(?P<id>\\d+)/reject', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'rejectInstructorApplication'],
                'permission_callback' => [$this, 'permissionInstructorApplications'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Permission callback specific to instructor-applications: `manage_sikshya` OR `manage_options`,
     * with the same JWT fallback every staff-backend route shares.
     *
     * Kept on the concrete controller (not the abstract base) because no other admin domain uses
     * exactly this cap combination — pulling it up would just be dead code on every sibling controller.
     *
     * @return bool|WP_Error
     */
    public function permissionInstructorApplications(WP_REST_Request $request)
    {
        if (current_user_can('manage_sikshya') || current_user_can('manage_options')) {
            return true;
        }

        $jwt = JwtAuthService::bearerFromRequest($request);
        if ($jwt === '') {
            return new WP_Error('rest_forbidden', __('Authentication required', 'sikshya'), ['status' => 401]);
        }

        $svc = $this->plugin->getService('jwtAuth');
        if (!$svc instanceof JwtAuthService) {
            return new WP_Error('rest_forbidden', __('JWT unavailable', 'sikshya'), ['status' => 500]);
        }

        $uid = $svc->validateToken($jwt);
        if (is_wp_error($uid)) {
            return $uid;
        }

        wp_set_current_user((int) $uid);

        return current_user_can('manage_sikshya') || current_user_can('manage_options')
            ? true
            : new WP_Error('rest_forbidden', __('Insufficient permissions', 'sikshya'), ['status' => 403]);
    }

    public function listInstructorApplications(WP_REST_Request $request): WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = max(1, min(100, (int) $request->get_param('per_page')));
        $status = (string) $request->get_param('status');
        $search = (string) $request->get_param('search');

        $svc = new InstructorApplicationsService();
        $out = $svc->listForRest($page, $per_page, $status, $search);

        return new WP_REST_Response($out, 200);
    }

    public function approveInstructorApplication(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $svc = new InstructorApplicationsService();
        $res = $svc->approve($id);
        if (is_wp_error($res)) {
            return new WP_REST_Response(
                ['ok' => false, 'message' => $res->get_error_message()],
                (int) ($res->get_error_data()['status'] ?? 400)
            );
        }

        return new WP_REST_Response(['ok' => true, 'message' => __('Instructor approved.', 'sikshya')], 200);
    }

    public function rejectInstructorApplication(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $svc = new InstructorApplicationsService();
        $res = $svc->reject($id);
        if (is_wp_error($res)) {
            return new WP_REST_Response(
                ['ok' => false, 'message' => $res->get_error_message()],
                (int) ($res->get_error_data()['status'] ?? 400)
            );
        }

        return new WP_REST_Response(['ok' => true, 'message' => __('Application rejected.', 'sikshya')], 200);
    }
}
