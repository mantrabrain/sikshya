<?php

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\AssignmentSubmissionRepository;
use Sikshya\Database\Repositories\CourseRepository;
use Sikshya\Database\Repositories\ProgressRepository;

/**
 * Basic assignment flow (submit, list, feedback).
 *
 * @package Sikshya\Services
 */
final class AssignmentService
{
    private CourseRepository $courses;

    private AssignmentSubmissionRepository $submissions;

    private ProgressRepository $progress;

    public function __construct(
        ?CourseRepository $courses = null,
        ?AssignmentSubmissionRepository $submissions = null,
        ?ProgressRepository $progress = null
    ) {
        $this->courses = $courses ?? new CourseRepository();
        $this->submissions = $submissions ?? new AssignmentSubmissionRepository();
        $this->progress = $progress ?? new ProgressRepository();
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

        $course_id = LessonCourseLink::resolvedCourseIdForAssignment($assignment_id);
        if ($course_id <= 0 || !$this->courses->findById($course_id)) {
            return ['success' => false, 'message' => __('Assignment is not linked to a valid course.', 'sikshya')];
        }

        // Enrollment check (server enforcement).
        $enrollment = new LearnerEnrollmentService(new CourseService($this->courses));
        if (!$enrollment->isEnrolled($course_id, $user_id)) {
            return ['success' => false, 'message' => __('You are not enrolled in this course.', 'sikshya')];
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

        if ($this->submissionViolatesDueDate($assignment_id, $existing)) {
            return [
                'success' => false,
                'message' => __('This assignment is past its due date.', 'sikshya'),
            ];
        }

        $subtype = $this->normalizedAssignmentSubtype($assignment_id);
        $incoming_files = $this->countIncomingAttachmentFiles($attachments);

        $v = $this->validateSubmissionPayload($assignment_id, $subtype, $content, $incoming_files);
        if (!$v['ok']) {
            return ['success' => false, 'message' => (string) $v['message']];
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

        $attachment_ids = $this->handleAttachments($user_id, $attachments);

        $v2 = $this->validateUploadedAttachments($assignment_id, $attachment_ids);
        if (!$v2['ok']) {
            $this->deleteAttachments($attachment_ids);

            return ['success' => false, 'message' => (string) $v2['message']];
        }

        $min_files = max(0, (int) get_post_meta($assignment_id, '_sikshya_assignment_min_files', true));
        if ($subtype === 'file_upload' && $min_files > 0 && count($attachment_ids) < $min_files) {
            $this->deleteAttachments($attachment_ids);

            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: %d: minimum files */
                    __('One or more uploads failed. Please try again and upload at least %d file(s).', 'sikshya'),
                    $min_files
                ),
            ];
        }

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

        $this->progress->markAssignmentComplete($user_id, $course_id, $assignment_id);

        do_action('sikshya_assignment_submitted', $sid, $assignment_id, $course_id, $user_id);

        $row = $this->submissions->findByAssignmentAndUser($assignment_id, $user_id);

        return [
            'success' => true,
            'submission' => $row ? $this->formatSubmission($row) : [
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

        $now = (int) current_time('timestamp');
        $out = [];
        foreach ($posts as $p) {
            $pid = (int) $p->ID;
            $submission = $this->submissions->findByAssignmentAndUser($pid, $user_id);
            $due_raw = (string) get_post_meta($pid, '_sikshya_assignment_due_date', true);
            $due_ts = $due_raw !== '' ? strtotime($due_raw) : 0;
            $stype = sanitize_key((string) get_post_meta($pid, '_sikshya_assignment_type', true));

            $out[] = [
                'id' => $pid,
                'title' => get_the_title($p),
                'due_date' => $due_raw,
                'due_formatted' => $due_ts > 0
                    ? (string) wp_date(get_option('date_format') . ' ' . get_option('time_format'), $due_ts)
                    : '',
                'is_overdue' => $due_ts > 0 && $now > $due_ts,
                'points' => (int) get_post_meta($pid, '_sikshya_assignment_points', true),
                'submission_type' => $stype,
                'submission_type_label' => $this->publicSubmissionTypeLabel($stype),
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

    private function publicSubmissionTypeLabel(string $stype): string
    {
        $labels = [
            'file' => __('File upload', 'sikshya'),
            'file_upload' => __('File upload', 'sikshya'),
            'text' => __('Essay', 'sikshya'),
            'essay' => __('Essay', 'sikshya'),
            'url_submission' => __('URL', 'sikshya'),
        ];

        return $stype !== '' && isset($labels[$stype]) ? $labels[$stype] : '';
    }

    private function normalizedAssignmentSubtype(int $assignment_id): string
    {
        $raw = sanitize_key((string) get_post_meta($assignment_id, '_sikshya_assignment_type', true));
        if ($raw === '') {
            return 'essay';
        }
        if (in_array($raw, ['file', 'file_upload'], true)) {
            return 'file_upload';
        }
        if (in_array($raw, ['text', 'essay'], true)) {
            return 'essay';
        }
        if ($raw === 'url_submission') {
            return 'url_submission';
        }

        return 'essay';
    }

    /**
     * @param array<string, mixed> $attachments
     */
    private function countIncomingAttachmentFiles(array $attachments): int
    {
        if (!isset($attachments['name'])) {
            return 0;
        }
        if (is_string($attachments['name']) && $attachments['name'] !== '') {
            return 1;
        }
        if (is_array($attachments['name'])) {
            $n = 0;
            foreach ($attachments['name'] as $name) {
                if (is_string($name) && $name !== '') {
                    ++$n;
                }
            }

            return $n;
        }

        return 0;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function validateSubmissionPayload(int $assignment_id, string $subtype, string $content, int $incoming_files): array
    {
        $plain = trim(wp_strip_all_tags($content));
        $require_text = (string) get_post_meta($assignment_id, '_sikshya_require_text', true) === '1';
        $min_files = max(0, (int) get_post_meta($assignment_id, '_sikshya_assignment_min_files', true));
        $max_files = max(0, (int) get_post_meta($assignment_id, '_sikshya_assignment_max_files', true));

        if ($max_files > 0 && $incoming_files > $max_files) {
            return [
                'ok' => false,
                'message' => sprintf(
                    /* translators: %d: maximum files */
                    __('You can upload at most %d file(s) for this assignment.', 'sikshya'),
                    $max_files
                ),
            ];
        }

        if ($min_files > 0 && $incoming_files < $min_files) {
            return [
                'ok' => false,
                'message' => sprintf(
                    /* translators: %d: minimum files */
                    __('Please upload at least %d file(s).', 'sikshya'),
                    $min_files
                ),
            ];
        }

        if ($subtype === 'essay') {
            if ($plain === '' && $incoming_files <= 0) {
                return ['ok' => false, 'message' => __('Please enter your response before submitting.', 'sikshya')];
            }
        } elseif ($subtype === 'file_upload') {
            if ($incoming_files <= 0) {
                return ['ok' => false, 'message' => __('Please choose at least one file to upload.', 'sikshya')];
            }
            if ($require_text && $plain === '') {
                return ['ok' => false, 'message' => __('Please add a short note or description with your upload.', 'sikshya')];
            }
        } elseif ($subtype === 'url_submission') {
            if ($plain === '' || !filter_var($plain, FILTER_VALIDATE_URL)) {
                return ['ok' => false, 'message' => __('Please submit a valid URL.', 'sikshya')];
            }
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * @param array<int> $attachment_ids
     * @return array{ok: bool, message: string}
     */
    private function validateUploadedAttachments(int $assignment_id, array $attachment_ids): array
    {
        $allowed = (string) get_post_meta($assignment_id, '_sikshya_allowed_file_extensions', true);
        $allowed = trim(strtolower($allowed));
        if ($allowed === '' || $attachment_ids === []) {
            return ['ok' => true, 'message' => ''];
        }

        $allowed_list = array_filter(array_map('trim', explode(',', $allowed)));
        if ($allowed_list === []) {
            return ['ok' => true, 'message' => ''];
        }

        foreach ($attachment_ids as $fid) {
            $path = get_attached_file($fid);
            if (!$path || !is_string($path)) {
                continue;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === '' || !in_array($ext, $allowed_list, true)) {
                return [
                    'ok' => false,
                    'message' => sprintf(
                        /* translators: %s: comma-separated list of allowed extensions */
                        __('One or more files use a type that is not allowed. Allowed: %s', 'sikshya'),
                        implode(', ', $allowed_list)
                    ),
                ];
            }
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * @param array<int> $ids
     */
    private function deleteAttachments(array $ids): void
    {
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                wp_delete_attachment($id, true);
            }
        }
    }

    /**
     * @param object|null $existing
     */
    private function submissionViolatesDueDate(int $assignment_id, $existing): bool
    {
        $allow_late = (string) get_post_meta($assignment_id, '_sikshya_allow_late', true) === '1';
        if ($allow_late) {
            return false;
        }

        $due_raw = (string) get_post_meta($assignment_id, '_sikshya_assignment_due_date', true);
        $due_ts = $due_raw !== '' ? strtotime($due_raw) : 0;
        if ($due_ts <= 0) {
            return false;
        }

        $now = (int) current_time('timestamp');
        if ($now <= $due_ts) {
            return false;
        }

        if (
            $existing
            && (string) ($existing->status ?? '') === 'graded'
            && (string) get_post_meta($assignment_id, '_sikshya_assignment_allow_resubmit', true) === '1'
        ) {
            return false;
        }

        return true;
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
