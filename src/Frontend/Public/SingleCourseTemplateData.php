<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\EnrollmentRepository;

/**
 * View-model for {@see templates/single-course.php} (no business logic in the template file).
 *
 * @package Sikshya\Frontend\Public
 */
final class SingleCourseTemplateData
{
    /**
     * @return array<string, mixed>
     */
    public static function forPost(\WP_Post $post): array
    {
        $course_id = (int) $post->ID;
        $pricing = function_exists('sikshya_get_course_pricing') ? sikshya_get_course_pricing($course_id) : [
            'price' => null,
            'sale_price' => null,
            'currency' => 'USD',
            'effective' => null,
            'on_sale' => false,
        ];

        $uid = get_current_user_id();
        $repo = new EnrollmentRepository();
        $enrolled = $uid > 0 && $repo->findByUserAndCourse($uid, $course_id) !== null;

        $is_paid = null !== $pricing['effective'] && (float) $pricing['effective'] > 0;

        $primary = 'login';
        if ($enrolled) {
            $primary = 'continue';
        } elseif ($is_paid) {
            $primary = 'cart';
        } elseif (is_user_logged_in()) {
            $primary = 'enroll_free';
        }

        $data = [
            'course_id' => $course_id,
            'post' => $post,
            'pricing' => $pricing,
            'is_paid' => $is_paid,
            'is_enrolled' => $enrolled,
            'primary_action' => $primary,
            'urls' => [
                'cart' => PublicPageUrls::url('cart'),
                'checkout' => PublicPageUrls::url('checkout'),
                'learn' => PublicPageUrls::learnForCourse($course_id),
                'account' => PublicPageUrls::url('account'),
                'courses_archive' => get_post_type_archive_link(PostTypes::COURSE) ?: '',
                'login' => wp_login_url(get_permalink($course_id)),
            ],
        ];

        return apply_filters('sikshya_single_course_template_data', $data, $post);
    }
}
