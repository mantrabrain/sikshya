<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Database\Repositories\OrderRepository;

/**
 * @package Sikshya\Frontend\Public
 */
final class OrderTemplateData
{
    /**
     * @return array{order: ?object, items: array<int, object>, error: string, urls: array<string, string>}
     */
    public static function fromRequest(): array
    {
        $order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        $uid = get_current_user_id();
        $error = '';
        $order = null;
        $items = [];

        if ($order_id <= 0) {
            $error = __('Missing order reference.', 'sikshya');
        } elseif ($uid <= 0) {
            $error = __('Please log in to view your order.', 'sikshya');
        } else {
            $repo = new OrderRepository();
            $order = $repo->findByIdForUser($order_id, $uid);
            if (!$order) {
                $error = __('Order not found.', 'sikshya');
            } else {
                $items = $repo->getItems($order_id);
            }
        }

        return apply_filters(
            'sikshya_order_template_data',
            [
                'order' => $order,
                'items' => $items,
                'error' => $error,
                'urls' => [
                    'account' => PublicPageUrls::url('account'),
                    'checkout' => PublicPageUrls::url('checkout'),
                ],
            ]
        );
    }
}
