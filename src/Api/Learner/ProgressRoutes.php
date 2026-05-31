<?php

declare(strict_types=1);

namespace Sikshya\Api\Learner;

use Sikshya\Constants\PostTypes;
use Sikshya\Services\LearnerCurriculumHelper;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Learner progress + lesson-completion routes.
 *
 * Extracted from {@see \Sikshya\Api\LearnerRestRoutes} as a domain-bounded subclass of
 * {@see AbstractLearnerRestController}. Owns `/sikshya/v1/me/progress` (GET) and
 * `/sikshya/v1/me/lesson-complete` (POST). Route paths and response shapes preserved
 * 1:1 with the original implementation.
 *
 * The lesson-complete callback fires `sikshya_can_complete_lesson` for Pro modules
 * (drip, prerequisites) to block completion; that filter contract is unchanged.
 *
 * @package Sikshya\Api\Learner
 */
final class ProgressRoutes extends AbstractLearnerRestController
{
    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/me/progress', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getMyProgress'],
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

        register_rest_route($namespace, '/me/lesson-complete', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'lessonComplete'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => [
                    'course_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'minimum' => 1,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => 'rest_validate_request_arg',
                    ],
                    'lesson_id' => [
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

    public function getMyProgress(WP_REST_Request $request): WP_REST_Response
    {
        $uid = get_current_user_id();
        $course_id = (int) $request->get_param('course_id');

        $courseService = $this->getCourseService();
        if (!$courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        $lessons = LearnerCurriculumHelper::lessonIdsForCourse($course_id);
        $total = count($lessons);
        $completed = $this->progress->countCompletedLessons($uid, $course_id);
        $pct = $total > 0 ? round(100 * $completed / $total, 2) : 0.0;

        $rows = $this->progress->getCourseProgress($uid, $course_id);

        return new WP_REST_Response(
            [
                'ok' => true,
                'data' => [
                    'course_id' => $course_id,
                    'lesson_total' => $total,
                    'lessons_completed' => $completed,
                    'progress_percent' => $pct,
                    'rows' => array_map([$this, 'mapProgressRow'], $rows),
                ],
            ],
            200
        );
    }

    public function lessonComplete(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        $course_id = isset($params['course_id']) ? (int) $params['course_id'] : 0;
        $lesson_id = isset($params['lesson_id']) ? (int) $params['lesson_id'] : 0;

        if ($course_id <= 0 || $lesson_id <= 0) {
            return $this->error('invalid_params', __('Invalid course or lesson.', 'sikshya'), 400);
        }

        $uid = get_current_user_id();
        $courseService = $this->getCourseService();
        if (!$courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        if (get_post_type($lesson_id) !== PostTypes::LESSON) {
            return $this->error('invalid_lesson', __('Invalid lesson.', 'sikshya'), 400);
        }
        // The curriculum helper returns both draft and published lessons; reject
        // here so a learner can't mark a draft/trashed lesson complete.
        if (get_post_status($lesson_id) !== 'publish') {
            return $this->error('lesson_unavailable', __('This lesson is no longer available.', 'sikshya'), 400);
        }

        $allowed = LearnerCurriculumHelper::lessonIdsForCourse($course_id);
        if (!in_array($lesson_id, $allowed, true)) {
            return $this->error('lesson_not_in_course', __('Lesson is not part of this course.', 'sikshya'), 400);
        }

        /**
         * Allow Pro modules (drip, prerequisites) to block lesson completion.
         */
        $can_complete = apply_filters('sikshya_can_complete_lesson', true, $uid, $course_id, $lesson_id);
        if (!$can_complete) {
            return $this->error('lesson_locked', __('This lesson is not available yet.', 'sikshya'), 403);
        }

        $this->progress->markLessonComplete($uid, $course_id, $lesson_id);
        $this->syncEnrollmentProgress($uid, $course_id);

        return new WP_REST_Response(['ok' => true, 'message' => __('Lesson marked complete.', 'sikshya')], 200);
    }

    /**
     * @param object $row
     * @return array<string, mixed>
     */
    private function mapProgressRow($row): array
    {
        return [
            'id' => (int) $row->id,
            'lesson_id' => $row->lesson_id ? (int) $row->lesson_id : null,
            'quiz_id' => $row->quiz_id ? (int) $row->quiz_id : null,
            'status' => (string) $row->status,
            'percentage' => isset($row->percentage) ? (float) $row->percentage : 0.0,
            'completed_date' => $row->completed_date ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }
}
