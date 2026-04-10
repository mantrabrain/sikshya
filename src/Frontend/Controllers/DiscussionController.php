<?php

namespace Sikshya\Frontend\Controllers;

use Sikshya\Core\Plugin;

/**
 * Frontend Discussion Controller
 *
 * @package Sikshya\Frontend\Controllers
 */
class DiscussionController
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
            case 'post_comment':
                $this->postComment();
                break;
            case 'get_comments':
                $this->getComments();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'sikshya'));
        }
    }

    /**
     * Post a comment
     */
    private function postComment(): void
    {
        $user_id = get_current_user_id();
        $post_id = intval($_POST['post_id'] ?? 0);
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        if (!$user_id || !$post_id || empty($content)) {
            wp_send_json_error(__('Invalid request.', 'sikshya'));
        }
        $result = $this->plugin->getService('discussion')->postComment($post_id, $user_id, $content);
        if ($result['success']) {
            wp_send_json_success($result['comment']);
        } else {
            wp_send_json_error($result['message'] ?? __('Failed to post comment.', 'sikshya'));
        }
    }

    /**
     * Get comments
     */
    private function getComments(): void
    {
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(__('Invalid request.', 'sikshya'));
        }
        $comments = $this->plugin->getService('discussion')->getComments($post_id);
        wp_send_json_success($comments);
    }
}
