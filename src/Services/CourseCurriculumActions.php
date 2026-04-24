<?php

/**
 * Curriculum mutations for the React admin (chapters + linked content). No HTML templates.
 *
 * @package Sikshya\Services
 */

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

final class CourseCurriculumActions
{
    /**
     * @param array<string,mixed> $params title, description, duration, type, lesson_type
     * @return array{success:bool,message?:string,data?:array<string,mixed>}
     */
    public function createContentForService(array $params): array
    {
        $title = sanitize_text_field($params['title'] ?? '');
        $description = wp_kses_post($params['description'] ?? '');
        $duration = sanitize_text_field($params['duration'] ?? '');
        $content_type = sanitize_text_field($params['type'] ?? 'lesson');
        $lesson_type = sanitize_text_field($params['lesson_type'] ?? '');

        if ($title === '') {
            return ['success' => false, 'message' => __('Content title is required', 'sikshya')];
        }

        switch ($content_type) {
            case 'lesson':
                $post_type = PostTypes::LESSON;
                break;
            case 'quiz':
                $post_type = PostTypes::QUIZ;
                break;
            case 'assignment':
                $post_type = PostTypes::ASSIGNMENT;
                break;
            case 'question':
                $post_type = PostTypes::QUESTION;
                break;
            default:
                $post_type = PostTypes::LESSON;
                break;
        }

        $content_id = wp_insert_post(
            [
                'post_title' => $title,
                'post_content' => $description,
                'post_type' => $post_type,
                'post_status' => 'publish',
            ]
        );

        if (is_wp_error($content_id)) {
            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: content type */
                    __('Failed to create %s', 'sikshya'),
                    $content_type
                ),
            ];
        }

        if ($duration !== '') {
            // Normalize duration to an integer minute count when possible.
            $mins = (int) preg_replace('/[^0-9]/', '', (string) $duration);
            if ($mins < 0) {
                $mins = 0;
            }

            // Keep legacy generic key for backward compatibility, but prefer per-type keys for rendering.
            update_post_meta($content_id, '_sikshya_duration', (string) $duration);

            if ($post_type === PostTypes::LESSON) {
                update_post_meta($content_id, '_sikshya_lesson_duration', $mins);
                update_post_meta($content_id, 'sikshya_lesson_duration', $mins);
            } elseif ($post_type === PostTypes::QUIZ) {
                update_post_meta($content_id, '_sikshya_quiz_time_limit', $mins);
            }
        }

        if ($post_type === PostTypes::LESSON) {
            // Free kinds always allowed; "live" / "scorm" / "h5p" are accepted here so the
            // course-builder picker can create them in one step. The renderer + lesson editor
            // separately enforce that the matching Pro addon is enabled.
            $allowed_kinds = ['text', 'video', 'live', 'scorm', 'h5p'];
            $kind = in_array($lesson_type, $allowed_kinds, true) ? $lesson_type : 'text';
            update_post_meta($content_id, '_sikshya_lesson_type', $kind);
        }

        return [
            'success' => true,
            'message' => sprintf(
                /* translators: %s: content type */
                __('%s created successfully', 'sikshya'),
                ucfirst($content_type)
            ),
            'data' => [
                'content_id' => (int) $content_id,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $params course_id, title, description?, duration?, order?
     * @return array{success:bool,message?:string,data?:array<string,mixed>}
     */
    public function restCreateChapter(array $params): array
    {
        $title = sanitize_text_field($params['title'] ?? '');
        $description = wp_kses_post($params['description'] ?? '');
        $duration = sanitize_text_field($params['duration'] ?? '');
        $order = (int) ($params['order'] ?? 1);
        $course_id = (int) ($params['course_id'] ?? 0);

        if ($title === '') {
            return ['success' => false, 'message' => __('Chapter title is required', 'sikshya')];
        }
        if ($course_id <= 0) {
            return ['success' => false, 'message' => __('Invalid course ID', 'sikshya')];
        }

        $chapter_id = wp_insert_post(
            [
                'post_title' => $title,
                'post_content' => $description,
                'post_type' => PostTypes::CHAPTER,
                'post_status' => 'publish',
                'post_parent' => $course_id,
            ],
            true
        );

        if (is_wp_error($chapter_id)) {
            return ['success' => false, 'message' => $chapter_id->get_error_message()];
        }

        if ($duration !== '') {
            update_post_meta($chapter_id, '_sikshya_duration', $duration);
        }
        update_post_meta($chapter_id, '_sikshya_order', $order);
        update_post_meta($chapter_id, '_sikshya_chapter_course_id', $course_id);

        return [
            'success' => true,
            'message' => __('Chapter created successfully', 'sikshya'),
            'data' => [
                'chapter_id' => (int) $chapter_id,
            ],
        ];
    }

    /**
     * @return array{success:bool,data?:array<string,mixed>,message?:string}
     */
    public function restChapterData(int $chapter_id): array
    {
        if ($chapter_id <= 0) {
            return ['success' => false, 'message' => __('Invalid chapter ID', 'sikshya')];
        }

        $chapter = get_post($chapter_id);
        if (!$chapter || $chapter->post_type !== PostTypes::CHAPTER) {
            return ['success' => false, 'message' => __('Chapter not found', 'sikshya')];
        }

        $duration = get_post_meta($chapter_id, '_sikshya_duration', true);
        $order = get_post_meta($chapter_id, '_sikshya_order', true);

        return [
            'success' => true,
            'data' => [
                'id' => $chapter_id,
                'title' => $chapter->post_title,
                'description' => $chapter->post_content,
                'duration' => $duration ?: '',
                'order' => $order ?: 1,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $params chapter_id, title, description?, duration?, order?
     * @return array{success:bool,message?:string}
     */
    public function restUpdateChapter(array $params): array
    {
        $chapter_id = (int) ($params['chapter_id'] ?? 0);
        $title = sanitize_text_field($params['title'] ?? '');
        $description = wp_kses_post($params['description'] ?? '');
        $duration = sanitize_text_field($params['duration'] ?? '');
        $order = (int) ($params['order'] ?? 1);

        if ($chapter_id <= 0) {
            return ['success' => false, 'message' => __('Invalid chapter ID', 'sikshya')];
        }
        if ($title === '') {
            return ['success' => false, 'message' => __('Chapter title is required', 'sikshya')];
        }

        $updated = wp_update_post(
            [
                'ID' => $chapter_id,
                'post_title' => $title,
                'post_content' => $description,
                'post_type' => PostTypes::CHAPTER,
            ],
            true
        );

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        update_post_meta($chapter_id, '_sikshya_duration', $duration);
        update_post_meta($chapter_id, '_sikshya_order', $order);

        return ['success' => true, 'message' => __('Chapter updated successfully', 'sikshya')];
    }
}
