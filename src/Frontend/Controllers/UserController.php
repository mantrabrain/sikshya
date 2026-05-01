<?php

namespace Sikshya\Frontend\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Frontend\Site\PublicPageUrls;

/**
 * Frontend User Controller
 *
 * @package Sikshya\Frontend\Controllers
 */
class UserController
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
     * Display user dashboard
     */
    public function dashboard(): void
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            $req = (string) (is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : home_url('/'));
            wp_safe_redirect(PublicPageUrls::login($req));
            exit;
        }

        // Learner hub is the account shell (/account/).
        wp_safe_redirect(PublicPageUrls::url('account'));
        exit;
    }

    /**
     * Display user profile
     */
    public function profile(): void
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            $req = (string) (is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : home_url('/'));
            wp_safe_redirect(PublicPageUrls::login($req));
            exit;
        }

        wp_safe_redirect(PublicPageUrls::url('account'));
        exit;
    }

    /**
     * Handle AJAX requests
     */
    public function handleAjax(string $action): void
    {
        switch ($action) {
            case 'update_profile':
                $this->updateProfile();
                break;
            case 'update_avatar':
                $this->updateAvatar();
                break;
            case 'get_activities':
                $this->getActivities();
                break;
            case 'get_progress':
                $this->getProgress();
                break;
            case 'get_certificates':
                $this->getCertificates();
                break;
            case 'get_achievements':
                $this->getAchievements();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'sikshya'));
        }
    }

    /**
     * Update user profile
     */
    private function updateProfile(): void
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        $profile_data = [
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'display_name' => sanitize_text_field($_POST['display_name'] ?? ''),
            'bio' => sanitize_textarea_field($_POST['bio'] ?? ''),
            'website' => esc_url_raw($_POST['website'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'location' => sanitize_text_field($_POST['location'] ?? ''),
            'social_links' => [
                'facebook' => esc_url_raw($_POST['facebook'] ?? ''),
                'twitter' => esc_url_raw($_POST['twitter'] ?? ''),
                'linkedin' => esc_url_raw($_POST['linkedin'] ?? ''),
                'instagram' => esc_url_raw($_POST['instagram'] ?? ''),
            ],
        ];

        // Update user data
        $result = $this->plugin->getService('user')->updateProfile($user_id, $profile_data);

        if ($result) {
            wp_send_json_success(__('Profile updated successfully.', 'sikshya'));
        } else {
            wp_send_json_error(__('Failed to update profile.', 'sikshya'));
        }
    }

    /**
     * Update user avatar
     */
    private function updateAvatar(): void
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!isset($_FILES['avatar'])) {
            wp_send_json_error(__('No avatar file uploaded.', 'sikshya'));
        }

        $file = $_FILES['avatar'];

        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(__('Invalid file type. Please upload a JPEG, PNG, or GIF image.', 'sikshya'));
        }

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            wp_send_json_error(__('File size too large. Please upload an image smaller than 5MB.', 'sikshya'));
        }

        // Upload and update avatar
        $result = $this->plugin->getService('user')->updateAvatar($user_id, $file);

        if ($result) {
            wp_send_json_success([
                'avatar_url' => $result['avatar_url'],
                'message' => __('Avatar updated successfully.', 'sikshya'),
            ]);
        } else {
            wp_send_json_error(__('Failed to update avatar.', 'sikshya'));
        }
    }

    /**
     * Get user activities
     */
    private function getActivities(): void
    {
        $user_id = get_current_user_id();
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 10);

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        $activities = $this->plugin->getService('activity')->getUserActivities($user_id, $per_page, $page);

        wp_send_json_success($activities);
    }

    /**
     * Get user progress
     */
    private function getProgress(): void
    {
        $user_id = get_current_user_id();
        $course_id = intval($_POST['course_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$course_id) {
            wp_send_json_error(__('Course ID is required.', 'sikshya'));
        }

        $progress = $this->plugin->getService('progress')->getCourseProgress($course_id, $user_id);

        wp_send_json_success($progress);
    }

    /**
     * Get user certificates
     */
    private function getCertificates(): void
    {
        $user_id = get_current_user_id();
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 10);

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        $certificates = $this->plugin->getService('certificate')->getUserCertificates($user_id, $per_page, $page);

        wp_send_json_success($certificates);
    }

    /**
     * Get user achievements
     */
    private function getAchievements(): void
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        $achievements = $this->plugin->getService('achievement')->getUserAchievements($user_id);

        wp_send_json_success($achievements);
    }
}
