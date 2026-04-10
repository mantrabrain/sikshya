<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Services\CourseService;
use Sikshya\Core\Plugin;

/**
 * Learner dashboard data for the account page.
 *
 * @package Sikshya\Frontend\Public
 */
final class AccountTemplateData
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $uid = get_current_user_id();
        $enrollments = [];
        $orders = [];

        $display_name = '';
        $email = '';
        $avatar_url = '';

        if ($uid > 0) {
            $user = wp_get_current_user();
            if ($user && $user->exists()) {
                $display_name = (string) $user->display_name;
                if ($display_name === '') {
                    $display_name = (string) $user->user_login;
                }
                $email = (string) $user->user_email;
                $avatar_url = (string) get_avatar_url($uid, ['size' => 96]);
            }

            $courseService = Plugin::getInstance()->getService('course');
            if ($courseService instanceof CourseService) {
                $enrollments = $courseService->getUserEnrollments($uid, ['limit' => 50]);
            }

            $orderRepo = new OrderRepository();
            if ($orderRepo->tableExists()) {
                $orders = $orderRepo->findRecentForUser($uid, 25);
            }
        }

        return apply_filters(
            'sikshya_account_template_data',
            [
                'user_id' => $uid,
                'display_name' => $display_name,
                'email' => $email,
                'avatar_url' => $avatar_url,
                'enrollments' => $enrollments,
                'orders' => $orders,
                'enrollment_count' => count($enrollments),
                'orders_count' => count($orders),
                'urls' => [
                    'home' => home_url('/'),
                    'account' => PublicPageUrls::url('account'),
                    'learn' => PublicPageUrls::url('learn'),
                    'cart' => PublicPageUrls::url('cart'),
                    'checkout' => PublicPageUrls::url('checkout'),
                    'courses' => get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'),
                ],
            ]
        );
    }
}
