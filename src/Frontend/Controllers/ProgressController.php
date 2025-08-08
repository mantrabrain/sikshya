<?php

namespace Sikshya\Frontend\Controllers;

use Sikshya\Core\Plugin;

/**
 * Frontend Progress Controller
 *
 * @package Sikshya\Frontend\Controllers
 */
class ProgressController
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
     * Handle AJAX requests
     */
    public function handleAjax(string $action): void
    {
        switch ($action) {
            case 'get_course_progress':
                $this->getCourseProgressAjax();
                break;
            case 'get_lesson_progress':
                $this->getLessonProgressAjax();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'sikshya'));
        }
    }

    /**
     * Get course progress via AJAX
     */
    private function getCourseProgressAjax(): void
    {
        $course_id = intval($_POST['course_id'] ?? 0);
        $user_id = get_current_user_id();
        if (!$user_id || !$course_id) {
            wp_send_json_error(__('Invalid request.', 'sikshya'));
        }
        $progress = $this->getCourseProgress($course_id, $user_id);
        wp_send_json_success($progress);
    }

    /**
     * Get lesson progress via AJAX
     */
    private function getLessonProgressAjax(): void
    {
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $user_id = get_current_user_id();
        if (!$user_id || !$lesson_id) {
            wp_send_json_error(__('Invalid request.', 'sikshya'));
        }
        $progress = $this->getLessonProgress($lesson_id, $user_id);
        wp_send_json_success($progress);
    }

    /**
     * Get course progress
     */
    public function getCourseProgress(int $course_id, int $user_id): array
    {
        return $this->plugin->getService('progress')->getCourseProgress($course_id, $user_id);
    }

    /**
     * Get lesson progress
     */
    public function getLessonProgress(int $lesson_id, int $user_id): array
    {
        return $this->plugin->getService('progress')->getLessonProgress($lesson_id, $user_id);
    }
} 