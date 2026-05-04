<?php

namespace Sikshya\Api;

use Sikshya\Services\AdminMarketingNoticeService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST for in-app marketing notices (React shell).
 *
 * @package Sikshya\Api
 */
final class AdminMarketingNoticeRestRoutes
{
    public static function register(): void
    {
        $ns = 'sikshya/v1';

        register_rest_route($ns, '/admin/notices', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'index'],
                'permission_callback' => [self::class, 'perm'],
            ],
        ]);

        register_rest_route($ns, '/admin/notices/(?P<id>[a-z0-9_\\-]+)/dismiss', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'dismiss'],
                'permission_callback' => [self::class, 'perm'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return bool|\WP_Error
     */
    public static function perm()
    {
        if (current_user_can('manage_options') || current_user_can('manage_sikshya')) {
            return true;
        }

        return new \WP_Error('rest_forbidden', __('Sorry, you are not allowed to do that.', 'sikshya'), ['status' => 403]);
    }

    public static function index(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        return rest_ensure_response([
            'success' => true,
            'data' => AdminMarketingNoticeService::getActiveNoticesForCurrentUser(),
        ]);
    }

    public static function dismiss(WP_REST_Request $request): WP_REST_Response
    {
        $id = sanitize_key((string) $request->get_param('id'));
        $result = AdminMarketingNoticeService::dismissForCurrentUser($id);
        if ($result === true) {
            return rest_ensure_response(['success' => true]);
        }

        $response = rest_ensure_response([
            'success' => false,
            'code' => $result->get_error_code(),
            'message' => $result->get_error_message(),
        ]);
        $response->set_status((int) ($result->get_error_data()['status'] ?? 400));

        return $response;
    }
}
