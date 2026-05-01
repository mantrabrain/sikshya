<?php

namespace Sikshya\Frontend\Site;

use Sikshya\Core\Plugin;
use Sikshya\Services\CourseService;

/**
 * POST handler for cart and free enrollment actions (no logic in templates).
 *
 * @package Sikshya\Frontend\Site
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

        $redirect_base = self::redirectBaseUrl($course_id);

        if ($action === 'add' && $course_id > 0) {
            $uid = get_current_user_id();
            // Allow extensions to block adding a course to the cart (e.g. unmet prerequisites).
            // Return WP_Error to block; message is shown via {@see CartFlashResolver}.
            $reject = apply_filters('sikshya_cart_validate_add_course', null, $course_id, $uid);
            if ($reject instanceof \WP_Error) {
                self::redirectWithTransientFlash($redirect_base, 'error', $reject->get_error_message());
            }

            $changed = CartStorage::addCourse($course_id);
            $flag = $changed ? 'added' : 'exists';
            $to_checkout = isset($_POST['sikshya_redirect_to_checkout'])
                && sanitize_text_field(wp_unslash((string) $_POST['sikshya_redirect_to_checkout'])) === '1';
            if ($to_checkout) {
                $checkout = PublicPageUrls::url('checkout');
                if (is_string($checkout) && $checkout !== '') {
                    // Buy now: land on checkout — avoid “added to cart” copy and skip “View cart” here.
                    $msg = $changed
                        ? __('Ready for checkout. Complete your purchase below.', 'sikshya')
                        : __('This course is already in your cart. You can finish checkout below.', 'sikshya');
                    self::redirectWithTransientFlash(
                        $checkout,
                        $changed ? 'success' : 'info',
                        $msg,
                        ['show_view_cart' => false]
                    );
                }
            }
            self::redirectWithTransientFlash(
                $redirect_base,
                $changed ? 'success' : 'info',
                $changed ? __('Course added to your cart.', 'sikshya') : __('This course is already in your cart.', 'sikshya'),
                ['show_view_cart' => true]
            );
        }

        if ($action === 'remove' && $course_id > 0) {
            CartStorage::removeCourse($course_id);
            self::redirectWithTransientFlash($redirect_base, 'success', __('Course removed from your cart.', 'sikshya'));
        }

        if ($action === 'clear') {
            CartStorage::clear();
            self::redirectWithTransientFlash($redirect_base, 'success', __('Your cart was cleared.', 'sikshya'));
        }

        if ($action === 'enroll_free' && $course_id > 0) {
            if (!is_user_logged_in()) {
                self::redirectWithTransientFlash($redirect_base, 'info', __('Log in to enroll in this course.', 'sikshya'));
            }
            $result = self::enrollFreeIfZeroPrice($course_id);
            $enrolled = $result === true;
            $err = $result instanceof \WP_Error ? $result->get_error_message() : '';
            if ($enrolled) {
                $learn = function_exists('sikshya_course_learn_entry_url') ? sikshya_course_learn_entry_url($course_id) : '';
                // Allow extensions to override where learners land after a successful free enrollment.
                $learn = (string) apply_filters('sikshya_enroll_free_redirect_url', $learn, $course_id);
                if ($learn !== '') {
                    wp_safe_redirect($learn);
                    exit;
                }
            }
            self::redirectWithTransientFlash(
                $redirect_base,
                $enrolled ? 'success' : 'error',
                $enrolled
                    ? __('You are now enrolled in this course.', 'sikshya')
                    : ($err !== '' ? $err : __('Could not complete enrollment. Please try again.', 'sikshya'))
            );
        }

        if ($action === 'admin_enroll_bypass' && $course_id > 0) {
            if (!is_user_logged_in()) {
                self::redirectWithTransientFlash($redirect_base, 'info', __('Log in to enroll in this course.', 'sikshya'));
            }
            $enrolled = function_exists('sikshya_enroll_paid_course_as_admin') && sikshya_enroll_paid_course_as_admin($course_id) > 0;
            if (!$enrolled) {
                // Common case: the bypass function returns 0 when the user is already enrolled (or an add-on grants access).
                $plugin = Plugin::getInstance();
                $courseService = $plugin->getService('course');
                if ($courseService instanceof CourseService) {
                    $enrolled = $courseService->isUserEnrolled(get_current_user_id(), $course_id);
                }
            }
            if ($enrolled) {
                $learn = function_exists('sikshya_course_learn_entry_url') ? sikshya_course_learn_entry_url($course_id) : '';
                // Allow extensions to override where admins land after an admin bypass enrollment.
                $learn = (string) apply_filters('sikshya_admin_enroll_bypass_redirect_url', $learn, $course_id);
                if ($learn !== '') {
                    wp_safe_redirect($learn);
                    exit;
                }
            }
            self::redirectWithTransientFlash(
                $redirect_base,
                $enrolled ? 'success' : 'error',
                $enrolled ? __('You are now enrolled in this course.', 'sikshya') : __('Could not complete enrollment. Please try again.', 'sikshya')
            );
        }

        wp_safe_redirect($redirect_base);
        exit;
    }

    /**
     * @return never
     */
    private static function redirectWithTransientFlash(string $redirect_base, string $type, string $message, array $extra = []): void
    {
        $token = wp_generate_password(16, false, false);
        set_transient(
            'sikshya_cart_flash_' . $token,
            array_merge(
                [
                    'type' => $type,
                    'message' => $message,
                ],
                $extra
            ),
            120
        );
        wp_safe_redirect(add_query_arg(['sikshya_cart_flash' => $token], $redirect_base));
        exit;
    }

    private static function redirectBaseUrl(int $course_id): string
    {
        $ref = wp_get_referer();
        if (is_string($ref) && $ref !== '') {
            $validated = wp_validate_redirect($ref, false);
            if (is_string($validated) && $validated !== '') {
                return $validated;
            }
        }
        if ($course_id > 0) {
            $p = get_permalink($course_id);
            if (is_string($p) && $p !== '') {
                return $p;
            }
        }

        return home_url('/');
    }

    /**
     * Attempt to enroll the current user when the effective price is zero.
     *
     * @return bool|\WP_Error True when enrolled (or already has access), otherwise WP_Error with a user-safe message.
     */
    private static function enrollFreeIfZeroPrice(int $course_id)
    {
        if (!function_exists('sikshya_get_course_pricing')) {
            return false;
        }
        $p = sikshya_get_course_pricing($course_id);
        $free = null === $p['effective'] || (float) $p['effective'] <= 0.00001;
        if (!$free) {
            return false;
        }

        $plugin = Plugin::getInstance();
        $courseService = $plugin->getService('course');
        if (!$courseService instanceof CourseService) {
            return new \WP_Error('enroll_unavailable', __('Enrollment service unavailable. Please try again.', 'sikshya'));
        }

        // If the user already has access (including add-on granted access), treat as enrolled.
        if ($courseService->isUserEnrolled(get_current_user_id(), $course_id)) {
            return true;
        }

        try {
            $courseService->enrollUser(get_current_user_id(), $course_id, []);

            return true;
        } catch (\InvalidArgumentException $e) {
            $msg = trim((string) $e->getMessage());
            return new \WP_Error('enroll_failed', $msg !== '' ? $msg : __('Could not complete enrollment. Please try again.', 'sikshya'));
        } catch (\Exception $e) {
            return new \WP_Error('enroll_failed', __('Could not complete enrollment. Please try again.', 'sikshya'));
        }
    }
}
