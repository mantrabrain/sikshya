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

        if ($uid > 0) {
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
                'enrollments' => $enrollments,
                'orders' => $orders,
                'urls' => [
                    'learn' => PublicPageUrls::url('learn'),
                    'cart' => PublicPageUrls::url('cart'),
                    'courses' => get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'),
                ],
            ]
        );
    }
}
