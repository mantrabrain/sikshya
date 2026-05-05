<?php

namespace Sikshya\Commerce;

use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Database\Repositories\PaymentRepository;
use Sikshya\Services\CourseService;
use Sikshya\Services\Settings;

/**
 * Marks orders paid, enrolls learners, writes legacy payment rows.
 *
 * @package Sikshya\Commerce
 */
final class OrderFulfillmentService
{
    private OrderRepository $orders;

    private PaymentRepository $payments;

    private CourseService $courseService;

    public function __construct(
        OrderRepository $orders,
        PaymentRepository $payments,
        CourseService $courseService
    ) {
        $this->orders = $orders;
        $this->payments = $payments;
        $this->courseService = $courseService;
    }

    /**
     * Idempotent fulfillment for a paid order.
     */
    public function fulfillPaidOrder(int $order_id): bool
    {
        $order = $this->orders->findById($order_id);
        if (!$order) {
            return false;
        }

        if ($order->status === 'paid') {
            return true;
        }

        $items = $this->orders->getItems($order_id);
        $has_line = false;
        foreach ($items as $row) {
            if ((int) ($row->course_id ?? 0) > 0) {
                $has_line = true;
                break;
            }
        }
        if (!$has_line) {
            return false;
        }

        $user_id = (int) $order->user_id;
        if ($user_id <= 0) {
            // Guest checkout: ensure an actual WP user exists before fulfilling.
            $user_id = $this->ensureGuestStudentUser($order_id, $order);
        }
        if ($user_id <= 0) {
            return false;
        }

        $auto_enroll = Settings::isTruthy(Settings::get('auto_enroll', true));

        foreach ($items as $item) {
            $course_id = (int) $item->course_id;
            if ($course_id <= 0) {
                continue;
            }

            if ($auto_enroll) {
                try {
                    $this->courseService->enrollUser(
                        $user_id,
                        $course_id,
                        [
                            'payment_method' => (string) $order->gateway,
                            'amount' => (float) $item->line_total,
                            'transaction_id' => (string) ($order->gateway_intent_id ?? ''),
                        ]
                    );
                } catch (\InvalidArgumentException $e) {
                    // Already enrolled or invalid — continue.
                }
            }

            if ($this->payments->tableExists()) {
                $meta = [];
                if (isset($order->meta) && is_string($order->meta) && $order->meta !== '') {
                    $decoded = json_decode($order->meta, true);
                    if (is_array($decoded)) {
                        $meta = $decoded;
                    }
                }
                $payment_meta = isset($meta['payment']) && is_array($meta['payment']) ? $meta['payment'] : [];
                $tx = '';
                if (isset($payment_meta['transaction_id']) && is_string($payment_meta['transaction_id'])) {
                    $tx = (string) $payment_meta['transaction_id'];
                }
                if ($tx === '') {
                    $tx = (string) ($order->gateway_intent_id ?? '');
                }
                $gw_resp = isset($payment_meta['gateway_response']) ? $payment_meta['gateway_response'] : null;

                $this->payments->create(
                    [
                        'user_id' => $user_id,
                        'course_id' => $course_id,
                        'amount' => (float) $item->line_total,
                        'currency' => (string) $order->currency,
                        'payment_method' => (string) $order->gateway,
                        'transaction_id' => $tx,
                        'status' => 'completed',
                        'charge_kind' => 'checkout',
                        'payment_date' => current_time('mysql'),
                        'gateway_response' => $gw_resp !== null ? $gw_resp : ['order_id' => $order_id],
                    ]
                );
            }
        }

        $this->orders->updateOrder($order_id, ['status' => 'paid']);

        /**
         * Fired after an order is fulfilled and marked paid.
         *
         * Pro / Scale modules may attach revenue share, commissions, etc.
         */
        $order_for_hook = $this->orders->findById($order_id) ?: $order;
        do_action('sikshya_order_fulfilled', $order_id, $order_for_hook);

        return true;
    }

    /**
     * Guest checkout creates an order first, then links a student account after payment succeeds.
     *
     * Webhook/IPN-driven gateways may fulfill without going through the browser confirm flow,
     * so fulfillment must be able to provision the student account as well.
     */
    private function ensureGuestStudentUser(int $order_id, object $order): int
    {
        // Respect global guest checkout toggle.
        if (!Settings::isTruthy(Settings::get('enable_guest_checkout', true))) {
            return 0;
        }

        $meta = [];
        if (isset($order->meta) && is_string($order->meta) && $order->meta !== '') {
            $decoded = json_decode($order->meta, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $guest = isset($meta['guest']) && is_array($meta['guest']) ? $meta['guest'] : [];
        $email = isset($guest['email']) ? sanitize_email((string) $guest['email']) : '';
        $name = isset($guest['name']) ? sanitize_text_field((string) $guest['name']) : '';
        if ($email === '' || !is_email($email)) {
            return 0;
        }

        /**
         * Guest email already registered: attach this order to that user.
         * Guest checkout session rejects this email up front so learners sign in; orders that still need
         * linking (admin, imports, gateways) must not deadlock here.
         */
        $existing = (int) email_exists($email);
        if ($existing > 0) {
            $this->orders->updateOrder($order_id, ['user_id' => $existing]);
            $this->persistGuestBillingUserMeta($existing, $order);

            return $existing;
        }

        $base = sanitize_user((string) preg_replace('/@.*/', '', $email), true);
        if ($base === '') {
            $base = 'student';
        }
        $username = $base;
        $i = 1;
        while (username_exists($username)) {
            $i++;
            $username = $base . $i;
            if ($i > 50) {
                $username = $base . wp_rand(1000, 999999);
                break;
            }
        }

        $password = wp_generate_password(20, true, true);
        $new_id = wp_create_user($username, $password, $email);
        if (is_wp_error($new_id) || (int) $new_id <= 0) {
            return 0;
        }
        $uid = (int) $new_id;

        $u = get_userdata($uid);
        if ($u) {
            $u->set_role('sikshya_student');
        }
        if ($name !== '') {
            wp_update_user(['ID' => $uid, 'display_name' => $name]);
        }
        wp_new_user_notification($uid, null, 'user');

        // Link order to the new student user so enrollments and receipts work.
        $this->orders->updateOrder($order_id, ['user_id' => $uid]);
        $this->persistGuestBillingUserMeta($uid, $order);

        return $uid;
    }

    /**
     * Copy checkout billing snapshot from order meta onto the learner user record.
     *
     * @param object $order Order row including meta JSON.
     */
    private function persistGuestBillingUserMeta(int $user_id, object $order): void
    {
        if ($user_id <= 0) {
            return;
        }

        $meta = [];
        if (isset($order->meta) && is_string($order->meta) && $order->meta !== '') {
            $decoded = json_decode($order->meta, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $billing = isset($meta['billing']) && is_array($meta['billing']) ? $meta['billing'] : [];
        if ($billing === []) {
            return;
        }

        $map = [
            'phone' => '_sikshya_billing_phone',
            'address_1' => '_sikshya_billing_address_1',
            'address_2' => '_sikshya_billing_address_2',
            'city' => '_sikshya_billing_city',
            'state' => '_sikshya_billing_state',
            'postcode' => '_sikshya_billing_postcode',
            'country' => '_sikshya_billing_country',
        ];
        foreach ($map as $k => $meta_key) {
            if (!array_key_exists($k, $billing)) {
                continue;
            }
            $val = sanitize_text_field((string) $billing[$k]);
            if ($val !== '') {
                update_user_meta($user_id, $meta_key, $val);
            }
        }
    }
}
