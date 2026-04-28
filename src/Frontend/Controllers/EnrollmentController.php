<?php

namespace Sikshya\Frontend\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Constants\PostTypes;

/**
 * Frontend Enrollment Controller
 *
 * @package Sikshya\Frontend\Controllers
 */
class EnrollmentController
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
     * Display enrollment page
     */
    public function enroll(): void
    {
        $course_id = intval($_GET['course_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$user_id) {
            $req = (string) (is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : home_url('/'));
            wp_safe_redirect(\Sikshya\Frontend\Public\PublicPageUrls::login($req));
            exit;
        }

        if (!$course_id) {
            wp_redirect(home_url());
            exit;
        }

        $course = get_post($course_id);
        if (!$course || $course->post_type !== PostTypes::COURSE) {
            wp_redirect(home_url());
            exit;
        }

        // Check if already enrolled
        if ($this->plugin->getService('enrollment')->isEnrolled($course_id, $user_id)) {
            wp_redirect(get_permalink($course_id));
            exit;
        }

        // Enrollment UX lives on the course page (cart / free enroll). Legacy virtual page kept for bookmarks.
        wp_safe_redirect(get_permalink($course_id));
        exit;
    }

    /**
     * Handle AJAX requests
     */
    public function handleAjax(string $action): void
    {
        switch ($action) {
            case 'enroll_course':
                $this->enrollCourse();
                break;
            case 'unenroll_course':
                $this->unenrollCourse();
                break;
            case 'get_enrollment_status':
                $this->getEnrollmentStatus();
                break;
            case 'process_payment':
                $this->processPayment();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'sikshya'));
        }
    }

    /**
     * Enroll in course
     */
    private function enrollCourse(): void
    {
        $course_id = intval($_POST['course_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$course_id) {
            wp_send_json_error(__('Course ID is required.', 'sikshya'));
        }

        // Check if already enrolled
        if ($this->plugin->getService('enrollment')->isEnrolled($course_id, $user_id)) {
            wp_send_json_error(__('You are already enrolled in this course.', 'sikshya'));
        }

        // Check course availability
        $course = get_post($course_id);
        if (!$course || $course->post_status !== 'publish') {
            wp_send_json_error(__('Course is not available.', 'sikshya'));
        }

        // Check enrollment limits (course builder + legacy meta keys).
        $max_raw = sikshya_first_nonempty_post_meta(
            $course_id,
            ['_sikshya_max_students', '_sikshya_course_max_students', 'sikshya_course_max_students']
        );
        $max_students = is_numeric($max_raw) ? (int) $max_raw : 0;
        if ($max_students > 0) {
            $course_svc = $this->plugin->getService('course');
            $stats = $course_svc instanceof \Sikshya\Services\CourseService ? $course_svc->getCourseStats($course_id) : [];
            $current_enrollments = isset($stats['total_enrollments']) ? (int) $stats['total_enrollments'] : 0;
            if ($current_enrollments >= $max_students) {
                wp_send_json_error(__('Course enrollment limit reached.', 'sikshya'));
            }
        }

        $pricing = sikshya_get_course_pricing($course_id);
        $final_price = $pricing['effective'];

        $courseService = $this->plugin->getService('course');
        if (!$courseService instanceof \Sikshya\Services\CourseService) {
            wp_send_json_error(__('Enrollment is unavailable.', 'sikshya'));
        }

        // If course is free, enroll directly
        if (!$final_price || $final_price == 0) {
            try {
                $enrollment_id = $courseService->enrollUser($user_id, $course_id, [
                    'payment_method' => 'free',
                    'amount' => 0,
                ]);
            } catch (\Exception $e) {
                wp_send_json_error($e->getMessage());
            }

            if ($enrollment_id) {
                wp_send_json_success([
                    'enrollment_id' => $enrollment_id,
                    'redirect_url' => get_permalink($course_id),
                    'message' => __('Successfully enrolled in course.', 'sikshya'),
                ]);
            } else {
                wp_send_json_error(__('Failed to enroll in course.', 'sikshya'));
            }
        }

        // Paid course: optional admin bypass (same rules as cart POST handler).
        if (function_exists('sikshya_enroll_paid_course_as_admin')) {
            $bypass_id = sikshya_enroll_paid_course_as_admin($course_id);
            if ($bypass_id > 0) {
                wp_send_json_success([
                    'enrollment_id' => $bypass_id,
                    'redirect_url' => get_permalink($course_id),
                    'message' => __('Enrolled without purchase (administrator access).', 'sikshya'),
                ]);
            }
        }

        // For paid courses, redirect to payment
        $payment_url = add_query_arg(
            [
                'course_id' => $course_id,
                'amount' => $final_price,
                'currency' => $pricing['currency'],
            ],
            home_url('/sikshya-payment/')
        );

        wp_send_json_success([
            'redirect_url' => $payment_url,
            'message' => __('Redirecting to payment...', 'sikshya'),
        ]);
    }

    /**
     * Unenroll from course
     */
    private function unenrollCourse(): void
    {
        $course_id = intval($_POST['course_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$course_id) {
            wp_send_json_error(__('Course ID is required.', 'sikshya'));
        }

        // Check if enrolled
        if (!$this->plugin->getService('enrollment')->isEnrolled($course_id, $user_id)) {
            wp_send_json_error(__('You are not enrolled in this course.', 'sikshya'));
        }

        // Unenroll user
        $result = $this->plugin->getService('enrollment')->unenrollUser($course_id, $user_id);

        if ($result) {
            wp_send_json_success(__('Successfully unenrolled from course.', 'sikshya'));
        } else {
            wp_send_json_error(__('Failed to unenroll from course.', 'sikshya'));
        }
    }

    /**
     * Get enrollment status
     */
    private function getEnrollmentStatus(): void
    {
        $course_id = intval($_POST['course_id'] ?? 0);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$course_id) {
            wp_send_json_error(__('Course ID is required.', 'sikshya'));
        }

        $is_enrolled = $this->plugin->getService('enrollment')->isEnrolled($course_id, $user_id);
        $enrollment_data = null;

        if ($is_enrolled) {
            $enrollment_data = $this->plugin->getService('enrollment')->getEnrollmentData($course_id, $user_id);
        }

        wp_send_json_success([
            'enrolled' => $is_enrolled,
            'enrollment_data' => $enrollment_data,
        ]);
    }

    /**
     * Process payment
     */
    private function processPayment(): void
    {
        $course_id = intval($_POST['course_id'] ?? 0);
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? '');
        $payment_data = $_POST['payment_data'] ?? [];
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('You must be logged in.', 'sikshya'));
        }

        if (!$course_id) {
            wp_send_json_error(__('Course ID is required.', 'sikshya'));
        }

        if (!$payment_method) {
            wp_send_json_error(__('Payment method is required.', 'sikshya'));
        }

        $pricing = sikshya_get_course_pricing($course_id);
        $final_price = $pricing['effective'];

        // Process payment
        $payment_result = $this->plugin->getService('payment')->processPayment([
            'course_id' => $course_id,
            'user_id' => $user_id,
            'amount' => $final_price,
            'currency' => $pricing['currency'],
            'payment_method' => $payment_method,
            'payment_data' => $payment_data,
        ]);

        if ($payment_result['success']) {
            // Enroll user after successful payment
            $enrollment_id = $this->plugin->getService('enrollment')->enrollUser($course_id, $user_id, [
                'payment_id' => $payment_result['payment_id'],
                'payment_method' => $payment_method,
                'amount' => $final_price,
                'status' => 'completed',
            ]);

            if ($enrollment_id) {
                wp_send_json_success([
                    'enrollment_id' => $enrollment_id,
                    'payment_id' => $payment_result['payment_id'],
                    'redirect_url' => get_permalink($course_id),
                    'message' => __('Payment successful and enrolled in course.', 'sikshya'),
                ]);
            } else {
                wp_send_json_error(__('Payment successful but failed to enroll in course.', 'sikshya'));
            }
        } else {
            wp_send_json_error($payment_result['message'] ?? __('Payment failed.', 'sikshya'));
        }
    }
}
