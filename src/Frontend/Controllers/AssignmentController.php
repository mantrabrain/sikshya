<?php

namespace Sikshya\Frontend\Controllers;

use Sikshya\Core\Plugin;

/**
 * Frontend Assignment Controller
 *
 * @package Sikshya\Frontend\Controllers
 */
class AssignmentController
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
            case 'submit_assignment':
                $this->submitAssignment();
                break;
            case 'get_assignments':
                $this->getAssignments();
                break;
            case 'get_assignment_feedback':
                $this->getAssignmentFeedback();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'sikshya'));
        }
    }

    /**
     * Submit assignment
     */
    private function submitAssignment(): void
    {
        $user_id = get_current_user_id();
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $attachments = $_FILES['attachments'] ?? [];
        if (!$user_id || !$assignment_id || empty($content)) {
            wp_send_json_error(__('Invalid request.', 'sikshya'));
        }
        $result = $this->plugin->getService('assignment')->submitAssignment($assignment_id, $user_id, $content, $attachments);
        if ($result['success']) {
            wp_send_json_success($result['assignment']);
        } else {
            wp_send_json_error($result['message'] ?? __('Failed to submit assignment.', 'sikshya'));
        }
    }

    /**
     * Get assignments
     */
    private function getAssignments(): void
    {
        $user_id = get_current_user_id();
        $course_id = intval($_POST['course_id'] ?? 0);
        if (!$user_id || !$course_id) {
            wp_send_json_error(__('Invalid request.', 'sikshya'));
        }
        $assignments = $this->plugin->getService('assignment')->getUserAssignments($course_id, $user_id);
        wp_send_json_success($assignments);
    }

    /**
     * Get assignment feedback
     */
    private function getAssignmentFeedback(): void
    {
        $user_id = get_current_user_id();
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        if (!$user_id || !$assignment_id) {
            wp_send_json_error(__('Invalid request.', 'sikshya'));
        }
        $feedback = $this->plugin->getService('assignment')->getAssignmentFeedback($assignment_id, $user_id);
        wp_send_json_success($feedback);
    }
}
