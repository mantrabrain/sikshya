<?php

declare(strict_types=1);

namespace Sikshya\Api\Learner;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Learner-initiated enrollment changes.
 *
 * Currently owns only `/sikshya/v1/me/unenroll`. Site-wide enrollment policy
 * (allow-self-unenroll toggle, deadline window, etc.) is enforced by
 * {@see \Sikshya\Services\CourseService::unenrollUser()}, which throws
 * `InvalidArgumentException` with the user-facing message on policy violation.
 *
 * Kept separate from {@see ProgressRoutes} so future enroll-self / re-enroll
 * routes have a natural home.
 *
 * @package Sikshya\Api\Learner
 */
final class EnrollmentRoutes extends AbstractLearnerRestController
{
    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/me/unenroll', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'unenroll'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => [
                    'course_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'minimum' => 1,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => 'rest_validate_request_arg',
                    ],
                ],
            ],
        ]);
    }

    public function unenroll(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        $course_id = isset($params['course_id']) ? (int) $params['course_id'] : 0;
        if ($course_id <= 0) {
            return $this->error('invalid_course', __('Invalid course.', 'sikshya'), 400);
        }

        try {
            $this->getCourseService()->unenrollUser(get_current_user_id(), $course_id);
        } catch (\InvalidArgumentException $e) {
            return $this->error('unenroll_failed', $e->getMessage(), 400);
        }

        return new WP_REST_Response(['ok' => true, 'message' => __('Unenrolled.', 'sikshya')], 200);
    }
}
