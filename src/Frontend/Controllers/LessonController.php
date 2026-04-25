<?php

namespace Sikshya\Frontend\Controllers;

use Sikshya\Core\Plugin;

/**
 * Frontend Lesson Controller
 *
 * @package Sikshya\Frontend\Controllers
 */
class LessonController
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Display single lesson
     */
    public function single(): void
    {
        $lesson_id = get_the_ID();
        $lesson = get_post($lesson_id);

        if (!$lesson || $lesson->post_type !== 'sikshya_lesson') {
            return;
        }

        // Get lesson data
        $lesson_data = $this->getLessonData($lesson_id);

        // Get course data
        $course_id = $lesson_data['course_id'];
        $course_data = $this->getCourseData($course_id);

        // Check if user is enrolled
        $user_id = get_current_user_id();
        $is_enrolled = $this->plugin->getService('enrollment')->isEnrolled($course_id, $user_id);

        if (!$is_enrolled) {
            wp_redirect(get_permalink($course_id));
            exit;
        }

        // Get lesson progress
        $progress = $this->plugin->getService('progress')->getLessonProgress($lesson_id, $user_id);

        // Get next and previous lessons
        $navigation = $this->getLessonNavigation($lesson_id, $course_id);

        // Get lesson content
        $content = $this->getLessonContent($lesson_id);

        // Load template
        include $this->plugin->getTemplatePath('single-lesson.php');
    }

    /**
     * Handle AJAX requests
     */
    public function handleAjax(string $action): void
    {
        switch ($action) {
            case 'mark_complete':
                $this->markLessonComplete();
                break;
            case 'mark_incomplete':
                $this->markLessonIncomplete();
                break;
            case 'save_progress':
                $this->saveLessonProgress();
                break;
            case 'get_lesson_content':
                $this->getLessonContentAjax();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'sikshya'));
        }
    }

    /**
     * Mark lesson as complete
     */
    private function markLessonComplete(): void
    {
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$lesson_id) {
            wp_send_json_error(__('Lesson ID is required.', 'sikshya'));
        }

        // Check if user is enrolled in the course
        $course_id = get_post_meta($lesson_id, 'sikshya_lesson_course', true);
        if (!$this->plugin->getService('enrollment')->isEnrolled($course_id, $user_id)) {
            wp_send_json_error(__('You must be enrolled in this course.', 'sikshya'));
        }

        $course_id = (int) $course_id;
        $access = apply_filters(
            'sikshya_access_check',
            ['ok' => true, 'message' => ''],
            [
                'type' => 'lesson',
                'user_id' => (int) $user_id,
                'course_id' => $course_id,
                'content_id' => (int) $lesson_id,
            ]
        );
        if (is_array($access) && isset($access['ok']) && $access['ok'] === false) {
            wp_send_json_error((string) ($access['message'] ?? __('This lesson is not available yet.', 'sikshya')));
        }

        // Mark lesson as complete
        $result = $this->plugin->getService('progress')->markLessonComplete($lesson_id, $user_id);

        if ($result) {
            wp_send_json_success(__('Lesson marked as complete.', 'sikshya'));
        } else {
            wp_send_json_error(__('Failed to mark lesson as complete.', 'sikshya'));
        }
    }

    /**
     * Mark lesson as incomplete
     */
    private function markLessonIncomplete(): void
    {
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$lesson_id) {
            wp_send_json_error(__('Lesson ID is required.', 'sikshya'));
        }

        // Mark lesson as incomplete
        $result = $this->plugin->getService('progress')->markLessonIncomplete($lesson_id, $user_id);

        if ($result) {
            wp_send_json_success(__('Lesson marked as incomplete.', 'sikshya'));
        } else {
            wp_send_json_error(__('Failed to mark lesson as incomplete.', 'sikshya'));
        }
    }

    /**
     * Save lesson progress
     */
    private function saveLessonProgress(): void
    {
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $progress_data = $_POST['progress_data'] ?? [];
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$lesson_id) {
            wp_send_json_error(__('Lesson ID is required.', 'sikshya'));
        }

        // Save progress
        $result = $this->plugin->getService('progress')->saveLessonProgress($lesson_id, $user_id, $progress_data);

        if ($result) {
            wp_send_json_success(__('Progress saved successfully.', 'sikshya'));
        } else {
            wp_send_json_error(__('Failed to save progress.', 'sikshya'));
        }
    }

    /**
     * Get lesson content via AJAX
     */
    private function getLessonContentAjax(): void
    {
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$lesson_id) {
            wp_send_json_error(__('Lesson ID is required.', 'sikshya'));
        }

        // Check if user is enrolled
        $course_id = get_post_meta($lesson_id, 'sikshya_lesson_course', true);
        if (!$this->plugin->getService('enrollment')->isEnrolled($course_id, $user_id)) {
            wp_send_json_error(__('You must be enrolled in this course.', 'sikshya'));
        }

        $course_id = (int) $course_id;
        $access = apply_filters(
            'sikshya_access_check',
            ['ok' => true, 'message' => ''],
            [
                'type' => 'lesson',
                'user_id' => (int) $user_id,
                'course_id' => $course_id,
                'content_id' => (int) $lesson_id,
            ]
        );
        if (is_array($access) && isset($access['ok']) && $access['ok'] === false) {
            wp_send_json_error((string) ($access['message'] ?? __('This lesson is not available yet.', 'sikshya')));
        }

        $content = $this->getLessonContent($lesson_id);
        wp_send_json_success($content);
    }

    /**
     * Get lesson data
     */
    private function getLessonData(int $lesson_id): array
    {
        return [
            'id' => $lesson_id,
            'title' => get_the_title($lesson_id),
            'content' => get_post_field('post_content', $lesson_id),
            'excerpt' => get_post_field('post_excerpt', $lesson_id),
            'thumbnail' => get_the_post_thumbnail_url($lesson_id, 'large'),
            'duration' => get_post_meta($lesson_id, 'sikshya_lesson_duration', true),
            'type' => get_post_meta($lesson_id, 'sikshya_lesson_type', true),
            'course_id' => get_post_meta($lesson_id, 'sikshya_lesson_course', true),
            'order' => get_post_meta($lesson_id, 'sikshya_lesson_order', true),
            'video_url' => get_post_meta($lesson_id, 'sikshya_lesson_video_url', true),
            'audio_url' => get_post_meta($lesson_id, 'sikshya_lesson_audio_url', true),
            'attachment_url' => get_post_meta($lesson_id, 'sikshya_lesson_attachment_url', true),
            'downloadable' => get_post_meta($lesson_id, 'sikshya_lesson_downloadable', true),
            'preview' => get_post_meta($lesson_id, 'sikshya_lesson_preview', true),
        ];
    }

    /**
     * Get course data
     */
    private function getCourseData(int $course_id): array
    {
        return [
            'id' => $course_id,
            'title' => get_the_title($course_id),
            'url' => get_permalink($course_id),
            'thumbnail' => get_the_post_thumbnail_url($course_id, 'medium'),
        ];
    }

    /**
     * Get lesson navigation
     */
    private function getLessonNavigation(int $lesson_id, int $course_id): array
    {
        $current_order = get_post_meta($lesson_id, 'sikshya_lesson_order', true);

        // Get previous lesson
        $prev_lesson = get_posts([
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'sikshya_lesson_course',
                    'value' => $course_id,
                    'compare' => '=',
                ],
                [
                    'key' => 'sikshya_lesson_order',
                    'value' => $current_order,
                    'compare' => '<',
                    'type' => 'NUMERIC',
                ],
            ],
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
        ]);

        // Get next lesson
        $next_lesson = get_posts([
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'sikshya_lesson_course',
                    'value' => $course_id,
                    'compare' => '=',
                ],
                [
                    'key' => 'sikshya_lesson_order',
                    'value' => $current_order,
                    'compare' => '>',
                    'type' => 'NUMERIC',
                ],
            ],
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
        ]);

        return [
            'prev' => !empty($prev_lesson) ? [
                'id' => $prev_lesson[0]->ID,
                'title' => $prev_lesson[0]->post_title,
                'url' => get_permalink($prev_lesson[0]->ID),
            ] : null,
            'next' => !empty($next_lesson) ? [
                'id' => $next_lesson[0]->ID,
                'title' => $next_lesson[0]->post_title,
                'url' => get_permalink($next_lesson[0]->ID),
            ] : null,
        ];
    }

    /**
     * Get lesson content
     */
    private function getLessonContent(int $lesson_id): array
    {
        $lesson_type = get_post_meta($lesson_id, 'sikshya_lesson_type', true);
        $content = [];

        switch ($lesson_type) {
            case 'video':
                $content['video_url'] = get_post_meta($lesson_id, 'sikshya_lesson_video_url', true);
                $content['video_provider'] = get_post_meta($lesson_id, 'sikshya_lesson_video_provider', true);
                $content['video_id'] = get_post_meta($lesson_id, 'sikshya_lesson_video_id', true);
                break;

            case 'audio':
                $content['audio_url'] = get_post_meta($lesson_id, 'sikshya_lesson_audio_url', true);
                break;

            case 'document':
                $content['document_url'] = get_post_meta($lesson_id, 'sikshya_lesson_document_url', true);
                $content['document_type'] = get_post_meta($lesson_id, 'sikshya_lesson_document_type', true);
                break;

            case 'presentation':
                $content['presentation_url'] = get_post_meta($lesson_id, 'sikshya_lesson_presentation_url', true);
                break;

            case 'interactive':
                $content['interactive_content'] = get_post_meta($lesson_id, 'sikshya_lesson_interactive_content', true);
                break;

            default:
                $content['text_content'] = get_post_field('post_content', $lesson_id);
                break;
        }

        $content['attachments'] = $this->getLessonAttachments($lesson_id);
        $content['type'] = $lesson_type;

        return $content;
    }

    /**
     * Get lesson attachments
     */
    private function getLessonAttachments(int $lesson_id): array
    {
        $attachments = get_post_meta($lesson_id, 'sikshya_lesson_attachments', true);

        if (!$attachments) {
            return [];
        }

        $attachment_data = [];
        foreach ($attachments as $attachment_id) {
            $attachment = get_post($attachment_id);
            if ($attachment) {
                $attachment_data[] = [
                    'id' => $attachment_id,
                    'title' => $attachment->post_title,
                    'url' => wp_get_attachment_url($attachment_id),
                    'type' => get_post_mime_type($attachment_id),
                    'size' => filesize(get_attached_file($attachment_id)),
                ];
            }
        }

        return $attachment_data;
    }
}
