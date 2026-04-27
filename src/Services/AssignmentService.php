<?php

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\AssignmentSubmissionRepository;
use Sikshya\Database\Repositories\CourseRepository;

/**
 * Basic assignment flow (submit, list, feedback).
 *
 * @package Sikshya\Services
 */
final class AssignmentService
{
    public function __construct(
        private CourseRepository $courses = new CourseRepository(),
        private AssignmentSubmissionRepository $submissions = new AssignmentSubmissionRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $attachments
     * @return array{success: bool, message?: string, submission?: array<string, mixed>}
     */
    public function submitAssignment(int $assignment_id, int $user_id, string $content, array $attachments = []): array
    {
        $assignment_id = absint($assignment_id);
        $user_id = absint($user_id);
        $content = trim($content);
        if ($assignment_id <= 0 || $user_id <= 0) {
            return ['success' => false, 'message' => __('Invalid request.', 'sikshya')];
        }

        $assignment = get_post($assignment_id);
        if (!$assignment || $assignment->post_type !== PostTypes::ASSIGNMENT) {
            return ['success' => false, 'message' => __('Invalid assignment.', 'sikshya')];
        }

        $course_id = (int) get_post_meta($assignment_id, '_sikshya_assignment_course', true);
        if ($course_id <= 0 || !$this->courses->findById($course_id)) {
            return ['success' => false, 'message' => __('Assignment is not linked to a valid course.', 'sikshya')];
        }

        // Enrollment check (server enforcement).
        $enrollment = new LearnerEnrollmentService(new CourseService($this->courses));
        if (!$enrollment->isEnrolled($course_id, $user_id)) {
            return ['success' => false, 'message' => __('You are not enrolled in this course.', 'sikshya')];
        }

        // Extension point: Pro addons (e.g. assignments_advanced) can validate the submission
        // against rubric/file-extension/late rules and short-circuit with `['ok' => false, ...]`.
        $pre = apply_filters(
            'sikshya_assignment_pre_submit',
            ['ok' => true, 'message' => ''],
            $assignment_id,
            $user_id,
            $content,
            $attachments
        );
        if (is_array($pre) && empty($pre['ok'])) {
            return [
                'success' => false,
                'message' => (string) ($pre['message'] ?? __('Submission rejected.', 'sikshya')),
            ];
        }

        $existing = $this->submissions->findByAssignmentAndUser($assignment_id, $user_id);
        if ($existing && (string) ($existing->status ?? '') === 'graded') {
            $allow_resubmit = (string) get_post_meta($assignment_id, '_sikshya_assignment_allow_resubmit', true) === '1';
            if (!$allow_resubmit) {
                return [
                    'success' => false,
                    'message' => __(
                        'This assignment is already graded. Resubmissions are not allowed unless your instructor enables them.',
                        'sikshya'
                    ),
                ];
            }
        }

        $attachment_ids = $this->handleAttachments($user_id, $attachments);
        $sid = $this->submissions->upsertSubmission(
            [
                'assignment_id' => $assignment_id,
                'course_id' => $course_id,
                'user_id' => $user_id,
                'content' => $content !== '' ? $content : null,
                'attachment_ids' => $attachment_ids,
                'status' => 'submitted',
                'submitted_at' => current_time('mysql'),
            ]
        );

        do_action('sikshya_assignment_submitted', $sid, $assignment_id, $course_id, $user_id);

        return [
            'success' => true,
            'submission' => [
                'id' => $sid,
                'assignment_id' => $assignment_id,
                'course_id' => $course_id,
                'user_id' => $user_id,
                'status' => 'submitted',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserAssignments(int $course_id, int $user_id): array
    {
        $course_id = absint($course_id);
        $user_id = absint($user_id);
        if ($course_id <= 0 || $user_id <= 0) {
            return [];
        }

        $posts = get_posts(
            [
                'post_type' => PostTypes::ASSIGNMENT,
                'post_status' => 'publish',
                'posts_per_page' => 500,
                'meta_key' => '_sikshya_assignment_course',
                'meta_value' => $course_id,
            ]
        );

        $out = [];
        foreach ($posts as $p) {
            $submission = $this->submissions->findByAssignmentAndUser((int) $p->ID, $user_id);
            $out[] = [
                'id' => (int) $p->ID,
                'title' => get_the_title($p),
                'due_date' => (string) get_post_meta($p->ID, '_sikshya_assignment_due_date', true),
                'points' => (int) get_post_meta($p->ID, '_sikshya_assignment_points', true),
                'submission' => $submission ? $this->formatSubmission($submission) : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAssignmentFeedback(int $assignment_id, int $user_id): ?array
    {
        $row = $this->submissions->findByAssignmentAndUser(absint($assignment_id), absint($user_id));
        return $row ? $this->formatSubmission($row) : null;
    }

    /**
     * @param array<string, mixed> $attachments
     * @return array<int>
     */
    private function handleAttachments(int $user_id, array $attachments): array
    {
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $ids = [];
        if (!isset($attachments['name'])) {
            return $ids;
        }

        // Basic single-file support: `attachments` file input.
        if (is_string($attachments['name']) && $attachments['name'] !== '') {
            $id = media_handle_upload('attachments', 0, [], ['test_form' => false]);
            if (!is_wp_error($id) && $id > 0) {
                $ids[] = (int) $id;
            }
        }

        // Multi-file support: `attachments[]`.
        if (is_array($attachments['name'])) {
            $count = count($attachments['name']);
            for ($i = 0; $i < $count; $i++) {
                if (empty($attachments['name'][$i])) {
                    continue;
                }
                $file = [
                    'name' => $attachments['name'][$i] ?? '',
                    'type' => $attachments['type'][$i] ?? '',
                    'tmp_name' => $attachments['tmp_name'][$i] ?? '',
                    'error' => $attachments['error'][$i] ?? 0,
                    'size' => $attachments['size'][$i] ?? 0,
                ];

                $key = 'sikshya_assignment_file_' . $i;
                $_FILES[$key] = $file;
                $id = media_handle_upload($key, 0, [], ['test_form' => false]);
                unset($_FILES[$key]);
                if (!is_wp_error($id) && $id > 0) {
                    $ids[] = (int) $id;
                }
            }
        }

        if ($ids) {
            foreach ($ids as $id) {
                wp_update_post(
                    [
                        'ID' => $id,
                        'post_author' => $user_id,
                    ]
                );
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSubmission(object $row): array
    {
        $attachment_ids = [];
        if (!empty($row->attachment_ids)) {
            $decoded = json_decode((string) $row->attachment_ids, true);
            if (is_array($decoded)) {
                $attachment_ids = array_values(array_filter(array_map('absint', $decoded)));
            }
        }

        $attachments = [];
        foreach ($attachment_ids as $aid) {
            $url = wp_get_attachment_url($aid);
            if ($url) {
                $attachments[] = [
                    'id' => $aid,
                    'url' => $url,
                    'name' => get_the_title($aid),
                ];
            }
        }

        $rubric_scores = null;
        if (isset($row->rubric_scores_json) && is_string($row->rubric_scores_json) && $row->rubric_scores_json !== '') {
            $decoded = json_decode($row->rubric_scores_json, true);
            $rubric_scores = is_array($decoded) ? $decoded : null;
        }

        $aid = (int) $row->assignment_id;

        return [
            'id' => (int) $row->id,
            'assignment_id' => $aid,
            'course_id' => (int) $row->course_id,
            'user_id' => (int) $row->user_id,
            'content' => (string) ($row->content ?? ''),
            'status' => (string) $row->status,
            'grade' => $row->grade !== null ? (float) $row->grade : null,
            'feedback' => (string) ($row->feedback ?? ''),
            'rubric_scores' => $rubric_scores,
            'allow_resubmit' => $aid > 0 && (string) get_post_meta($aid, '_sikshya_assignment_allow_resubmit', true) === '1',
            'attachments' => $attachments,
            'submitted_at' => (string) $row->submitted_at,
            'graded_at' => (string) ($row->graded_at ?? ''),
        ];
    }
}

