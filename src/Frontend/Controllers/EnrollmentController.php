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
            wp_redirect(wp_login_url(add_query_arg('redirect_to', urlencode($_SERVER['REQUEST_URI']), '')));
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

        // Get course data
        $course_data = $this->getCourseData($course_id);

        // Get payment methods
        $payment_methods = $this->plugin->getService('payment')->getAvailableMethods();

        // Get user data
        $user_data = $this->getUserData($user_id);

        // Load template
        include $this->plugin->getTemplatePath('frontend/enroll.php');
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
            $current_enrollments = $this->plugin->getService('enrollment')->getCourseEnrollmentsCount($course_id);
            if ($current_enrollments >= $max_students) {
                wp_send_json_error(__('Course enrollment limit reached.', 'sikshya'));
            }
        }

        $pricing = sikshya_get_course_pricing($course_id);
        $final_price = $pricing['effective'];

        // If course is free, enroll directly
        if (!$final_price || $final_price == 0) {
            $enrollment_id = $this->plugin->getService('enrollment')->enrollUser($course_id, $user_id, [
                'payment_method' => 'free',
                'amount' => 0,
                'status' => 'completed',
            ]);

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

    /**
     * Get course data
     */
    private function getCourseData(int $course_id): array
    {
        $pricing = sikshya_get_course_pricing($course_id);

        return [
            'id' => $course_id,
            'title' => get_the_title($course_id),
            'excerpt' => get_post_field('post_excerpt', $course_id),
            'thumbnail' => get_the_post_thumbnail_url($course_id, 'large'),
            'price' => $pricing['price'],
            'sale_price' => $pricing['sale_price'],
            'currency' => $pricing['currency'],
            'price_html' => null !== $pricing['effective'] && (float) $pricing['effective'] > 0
                ? sikshya_format_price((float) $pricing['effective'], $pricing['currency'])
                : '',
            'duration' => sikshya_first_nonempty_post_meta(
                $course_id,
                ['_sikshya_duration', '_sikshya_course_duration', 'sikshya_course_duration']
            ),
            'level' => sikshya_first_nonempty_post_meta(
                $course_id,
                ['_sikshya_difficulty', '_sikshya_course_level', 'sikshya_course_level']
            ),
            'instructor' => $this->getCourseInstructor($course_id),
            'lessons_count' => $this->getCourseLessonsCount($course_id),
            'students_count' => $this->getCourseStudentsCount($course_id),
            'rating' => $this->getCourseRating($course_id),
        ];
    }

    /**
     * Get user data
     */
    private function getUserData(int $user_id): array
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return [];
        }

        return [
            'id' => $user_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ];
    }

    /**
     * Get course instructor
     */
    private function getCourseInstructor(int $course_id): array
    {
        $instructor_id = get_post_meta($course_id, 'sikshya_course_instructor', true);

        if (!$instructor_id) {
            return [];
        }

        $instructor = get_userdata($instructor_id);

        if (!$instructor) {
            return [];
        }

        return [
            'id' => $instructor_id,
            'name' => $instructor->display_name,
            'bio' => get_user_meta($instructor_id, 'sikshya_instructor_bio', true),
            'avatar' => get_avatar_url($instructor_id, ['size' => 100]),
        ];
    }

    /**
     * Get course lessons count
     */
    private function getCourseLessonsCount(int $course_id): int
    {
        $args = [
            'post_type' => 'sikshya_lesson',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'sikshya_lesson_course',
                    'value' => $course_id,
                    'compare' => '=',
                ],
            ],
        ];

        $lessons_query = new \WP_Query($args);
        $count = $lessons_query->found_posts;
        wp_reset_postdata();

        return $count;
    }

    /**
     * Get course students count
     */
    private function getCourseStudentsCount(int $course_id): int
    {
        return intval(get_post_meta($course_id, 'sikshya_enrollment_count', true));
    }

    /**
     * Get course rating
     */
    private function getCourseRating(int $course_id): array
    {
        return $this->plugin->getService('review')->getCourseRating($course_id);
    }
}
