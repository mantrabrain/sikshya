<?php

/**
 * Logged-in frontend actions via REST (replaces admin-ajax for theme JS).
 *
 * @package Sikshya\Api
 */

namespace Sikshya\Api;

use Sikshya\Core\Plugin;
use Sikshya\Services\CourseService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class PublicRestRoutes
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/me/enroll', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'enroll'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
            ],
        ]);
    }

    /**
     * Cookie session (X-WP-Nonce) or Bearer JWT.
     */
    public function requireLoginOrJwt(WP_REST_Request $request)
    {
        if (is_user_logged_in()) {
            return true;
        }

        $jwt = JwtAuthService::bearerFromRequest($request);
        if ($jwt === '') {
            return new \WP_Error('rest_forbidden', __('You must be logged in.', 'sikshya'), ['status' => 401]);
        }

        $svc = $this->plugin->getService('jwtAuth');
        if (!$svc instanceof JwtAuthService) {
            return new \WP_Error('rest_forbidden', __('Authentication unavailable.', 'sikshya'), ['status' => 500]);
        }

        $uid = $svc->validateToken($jwt);
        if (is_wp_error($uid)) {
            return $uid;
        }

        wp_set_current_user($uid);

        return true;
    }

    public function enroll(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        $course_id = isset($params['course_id']) ? (int) $params['course_id'] : 0;
        if ($course_id <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => __('Invalid course.', 'sikshya')], 400);
        }

        $courseService = $this->plugin->getService('course');
        if (!$courseService instanceof CourseService) {
            return new WP_REST_Response(['success' => false, 'message' => 'Service unavailable'], 500);
        }

        try {
            $courseService->enrollUser(get_current_user_id(), $course_id, []);
            return new WP_REST_Response(['success' => true, 'message' => __('Enrolled successfully.', 'sikshya')], 200);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

}
