<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Core\Plugin;
use Sikshya\Services\CourseService;

/**
 * POST handler for cart and free enrollment actions (no logic in templates).
 *
 * @package Sikshya\Frontend\Public
 */
final class CartFormHandler
{
    public static function maybeHandle(): void
    {
        if (!isset($_POST['sikshya_cart_action'], $_POST['sikshya_cart_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['sikshya_cart_nonce'])), 'sikshya_cart')) {
            return;
        }

        $action = sanitize_key(wp_unslash((string) $_POST['sikshya_cart_action']));
        $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;

        if ($action === 'add' && $course_id > 0) {
            CartStorage::addCourse($course_id);
        } elseif ($action === 'remove' && $course_id > 0) {
            CartStorage::removeCourse($course_id);
        } elseif ($action === 'clear') {
            CartStorage::clear();
        } elseif ($action === 'enroll_free' && $course_id > 0 && is_user_logged_in()) {
            self::enrollFreeIfZeroPrice($course_id);
        }

        wp_safe_redirect(wp_get_referer() ? (string) wp_get_referer() : home_url('/'));
        exit;
    }

    private static function enrollFreeIfZeroPrice(int $course_id): void
    {
        if (!function_exists('sikshya_get_course_pricing')) {
            return;
        }
        $p = sikshya_get_course_pricing($course_id);
        $free = null === $p['effective'] || (float) $p['effective'] <= 0.00001;
        if (!$free) {
            return;
        }

        $plugin = Plugin::getInstance();
        $courseService = $plugin->getService('course');
        if (!$courseService instanceof CourseService) {
            return;
        }

        try {
            $courseService->enrollUser(get_current_user_id(), $course_id, []);
        } catch (\Exception $e) {
            // Duplicate enrollment or validation.
        }
    }
}
