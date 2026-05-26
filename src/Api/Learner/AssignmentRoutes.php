<?php

declare(strict_types=1);

namespace Sikshya\Api\Learner;

use Sikshya\Services\AssignmentService;
use Sikshya\Services\LessonCourseLink;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Learner assignment routes — list, submit, and view feedback.
 *
 * Extracted from {@see \Sikshya\Api\LearnerRestRoutes} as the second domain to follow the
 * `AbstractLearnerRestController` pattern set by {@see ContentNoteRoutes}. Owns the three
 * `/sikshya/v1/me/assignment*` routes with the original paths and response shapes preserved.
 *
 * @package Sikshya\Api\Learner
 */
final class AssignmentRoutes extends AbstractLearnerRestController
{
    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/me/assignments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getMyAssignments'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => [
                    'course_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/me/assignment-submit', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submitAssignment'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
            ],
        ]);

        register_rest_route($namespace, '/me/assignment-feedback', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getMyAssignmentFeedback'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => [
                    'assignment_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);
    }

    public function getMyAssignments(WP_REST_Request $request): WP_REST_Response
    {
        $uid = get_current_user_id();
        $course_id = (int) $request->get_param('course_id');

        $courseService = $this->getCourseService();
        if (!$courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        $svc = $this->assignmentService();
        $rows = $svc->getUserAssignments($course_id, $uid);

        return new WP_REST_Response(['ok' => true, 'data' => ['assignments' => $rows]], 200);
    }

    public function submitAssignment(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        $assignment_id = (int) $request->get_param('assignment_id');
        if ($assignment_id <= 0 && is_array($params)) {
            $assignment_id = (int) ($params['assignment_id'] ?? 0);
        }

        $contentRaw = $request->get_param('content');
        if ($contentRaw === null && is_array($params)) {
            $content = (string) ($params['content'] ?? '');
        } else {
            $content = is_string($contentRaw) ? $contentRaw : (string) $contentRaw;
        }

        // File uploads for REST can come in $_FILES; keep parity with legacy controller.
        $files = $_FILES['attachments'] ?? [];

        $svc = $this->assignmentService();
        $result = $svc->submitAssignment($assignment_id, get_current_user_id(), $content, is_array($files) ? $files : []);
        if (empty($result['success'])) {
            return $this->error('assignment_submit_failed', (string) ($result['message'] ?? __('Could not submit assignment.', 'sikshya')), 400);
        }

        $cid = 0;
        if (isset($result['submission']) && is_array($result['submission'])) {
            $cid = (int) ($result['submission']['course_id'] ?? 0);
        }
        if ($cid <= 0) {
            $cid = (int) LessonCourseLink::resolvedCourseIdForAssignment($assignment_id);
        }
        if ($cid > 0) {
            $this->syncEnrollmentProgress(get_current_user_id(), $cid);
        }

        return new WP_REST_Response(['ok' => true, 'data' => $result['submission']], 200);
    }

    public function getMyAssignmentFeedback(WP_REST_Request $request): WP_REST_Response
    {
        $uid = get_current_user_id();
        $assignment_id = (int) $request->get_param('assignment_id');
        if ($assignment_id <= 0) {
            return $this->error('invalid_assignment', __('Invalid assignment.', 'sikshya'), 400);
        }

        $course_id = (int) LessonCourseLink::resolvedCourseIdForAssignment($assignment_id);
        $denied = LearnerEnrollmentGuard::denyUnlessEnrolled(
            $uid,
            $course_id,
            $this->getCourseService(),
            'assignment_no_course',
            __('Assignment is not linked to a course.', 'sikshya')
        );
        if ($denied !== null) {
            return $this->error($denied['code'], $denied['message'], $denied['status']);
        }

        $svc = $this->assignmentService();
        $row = $svc->getAssignmentFeedback($assignment_id, $uid);

        return new WP_REST_Response(['ok' => true, 'data' => ['feedback' => $row]], 200);
    }

    private function assignmentService(): AssignmentService
    {
        $svc = $this->plugin->getService('assignment');
        if (!$svc instanceof AssignmentService) {
            throw new \RuntimeException('Assignment service unavailable');
        }

        return $svc;
    }
}
