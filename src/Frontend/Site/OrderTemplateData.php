<?php

namespace Sikshya\Frontend\Site;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Services\Settings;

/**
 * @package Sikshya\Frontend\Site
 */
final class OrderTemplateData
{
    /**
     * @return array{order: ?object, items: array<int, object>, error: string, offline_instructions_html: string, status_label: string, gateway_label: string, urls: array{home: string, cart: string, checkout: string, account: string, courses: string}}
     */
    public static function fromRequest(): array
    {
        $raw_key = isset($_GET['order_key']) ? sanitize_text_field(wp_unslash((string) $_GET['order_key'])) : '';
        $order_key = OrderRepository::sanitizePublicToken($raw_key);
        $uid = get_current_user_id();
        $error = '';
        $order = null;
        $items = [];
        $offline_instructions_html = '';
        $status_label = '';
        $gateway_label = '';

        if ($order_key === '') {
            $error = __('Missing order reference.', 'sikshya');
        } else {
            $repo = new OrderRepository();
            // Default: if logged in, enforce ownership. Otherwise treat the public token as a bearer reference.
            //
            // Admin/support users need to open receipts/invoices for customers, so allow them to bypass ownership
            // checks when they have management capability.
            $can_admin_view = current_user_can('manage_options') || current_user_can('manage_sikshya');
            $order = ($uid > 0 && !$can_admin_view)
                ? $repo->findByPublicTokenForUser($order_key, $uid)
                : $repo->findByPublicToken($order_key);
            if (!$order) {
                $error = __('Order not found.', 'sikshya');
            } else {
                $items = $repo->getItems((int) $order->id);
                $st = (string) $order->status;
                $gw = (string) $order->gateway;
                $status_label = self::statusLabel($st);
                $gateway_label = self::gatewayLabel($gw);
                // Show manual payment instructions on the receipt for offline/bank transfer orders.
                // (Admins may mark orders paid later; keeping the instructions visible avoids confusion.)
                if ($gw === 'offline') {
                    $offline_instructions_html = (string) Settings::get('offline_payment_instructions', '');
                } elseif ($gw === 'bank_transfer') {
                    $offline_instructions_html = (string) Settings::get('bank_transfer_instructions', '');
                }
            }
        }

        return apply_filters(
            'sikshya_order_template_data',
            [
                'order' => $order,
                'items' => $items,
                'error' => $error,
                'offline_instructions_html' => $offline_instructions_html,
                'status_label' => $status_label,
                'gateway_label' => $gateway_label,
                'urls' => [
                    'home' => home_url('/'),
                    'cart' => PublicPageUrls::url('cart'),
                    'checkout' => PublicPageUrls::url('checkout'),
                    'account' => PublicPageUrls::url('account'),
                    'courses' => get_post_type_archive_link(PostTypes::COURSE) ?: home_url('/'),
                ],
            ]
        );
    }

    private static function statusLabel(string $status): string
    {
        $map = [
            'paid' => __('Paid', 'sikshya'),
            'pending' => __('Pending payment', 'sikshya'),
            'on-hold' => __('On hold', 'sikshya'),
        ];

        return $map[$status] ?? $status;
    }

    private static function gatewayLabel(string $gateway): string
    {
        if ($gateway === '') {
            return __('—', 'sikshya');
        }

        $map = [
            'offline' => __('Offline / manual', 'sikshya'),
            'bank_transfer' => __('Bank transfer', 'sikshya'),
            'stripe' => __('Stripe', 'sikshya'),
            'paypal' => __('PayPal', 'sikshya'),
            'mollie' => __('Mollie', 'sikshya'),
            'paystack' => __('Paystack', 'sikshya'),
            'razorpay' => __('Razorpay', 'sikshya'),
        ];

        return $map[$gateway] ?? $gateway;
    }
}
