<?php

namespace Sikshya\Commerce;

use Sikshya\Licensing\TierCapabilities;

/**
 * Payment gateway registry (extensible via filters).
 *
 * Core provides Offline + PayPal as free gateways. Other gateways are considered Pro by default.
 *
 * @package Sikshya\Commerce
 */
final class PaymentGatewayRegistry
{
    /**
     * @return array<string, string> Map gateway id => SVG icon filename (in Sikshya assets).
     */
    private static function iconMap(): array
    {
        return [
            'offline' => 'pay-later.svg',
            'paypal' => 'paypal.svg',
            'stripe' => 'stripe.svg',
            'razorpay' => 'razorpay.svg',
            'mollie' => 'mollie.svg',
            'paystack' => 'paystack.svg',
            'square' => 'square.svg',
            'authorize_net' => 'authorize-net.svg',
            'bank_transfer' => 'bank_transfer.svg',
        ];
    }

    /**
     * @return array<int, array{
     *   id: string,
     *   label: string,
     *   description: string,
     *   tier: 'free'|'pro',
     *   enabled_setting_key?: string,
     *   setting_keys?: array<int, string>,
     * }>
     */
    public static function all(): array
    {
        $gateways = [
            [
                'id' => 'offline',
                'label' => __('Offline / manual', 'sikshya'),
                'description' => __('No API keys. Use for bank transfer, invoice, or manual confirmation.', 'sikshya'),
                'tier' => 'free',
                'enabled_setting_key' => 'enable_offline_payment',
                'setting_keys' => [
                    'offline_payment_instructions',
                    'offline_payment_auto_fulfill',
                ],
            ],
            [
                'id' => 'paypal',
                'label' => __('PayPal', 'sikshya'),
                'description' => __('Accept PayPal and card payments (requires Client ID and Secret).', 'sikshya'),
                'tier' => 'free',
                'enabled_setting_key' => 'enable_paypal_payment',
                'setting_keys' => [
                    'paypal_integration_mode',
                    'paypal_email',
                    'paypal_client_id',
                    'paypal_secret',
                    'paypal_mode',
                    'paypal_webhook_id',
                ],
            ],
            [
                'id' => 'stripe',
                'label' => __('Stripe', 'sikshya'),
                'description' => __('Accept credit/debit cards via Stripe (API keys + webhook secret).', 'sikshya'),
                'tier' => 'pro',
                'enabled_setting_key' => 'enable_stripe_payment',
                'setting_keys' => [
                    'stripe_publishable_key',
                    'stripe_secret_key',
                    'stripe_webhook_secret',
                ],
            ],
            [
                'id' => 'razorpay',
                'label' => __('Razorpay', 'sikshya'),
                'description' => __('Accept payments via Razorpay (India).', 'sikshya'),
                'tier' => 'pro',
                'enabled_setting_key' => 'enable_razorpay_payment',
                'setting_keys' => [
                    'razorpay_key_id',
                    'razorpay_key_secret',
                    'razorpay_webhook_secret',
                ],
            ],
            [
                'id' => 'mollie',
                'label' => __('Mollie', 'sikshya'),
                'description' => __('Accept payments via Mollie (Europe).', 'sikshya'),
                'tier' => 'pro',
                'enabled_setting_key' => 'enable_mollie_payment',
                'setting_keys' => [
                    'mollie_api_key',
                    'mollie_payment_methods',
                    'mollie_webhook_secret',
                ],
            ],
            [
                'id' => 'paystack',
                'label' => __('Paystack', 'sikshya'),
                'description' => __('Accept payments via Paystack (Africa).', 'sikshya'),
                'tier' => 'pro',
                'enabled_setting_key' => 'enable_paystack_payment',
                'setting_keys' => [
                    'paystack_public_key',
                    'paystack_payment_channels',
                    'paystack_secret_key',
                    'paystack_webhook_secret',
                ],
            ],
            [
                'id' => 'square',
                'label' => __('Square', 'sikshya'),
                'description' => __('Accept payments via Square (US/Canada).', 'sikshya'),
                'tier' => 'pro',
                'enabled_setting_key' => 'enable_square_payment',
                'setting_keys' => [
                    'square_application_id',
                    'square_access_token',
                    'square_location_id',
                    'square_webhook_signature_key',
                ],
            ],
            [
                'id' => 'authorize_net',
                'label' => __('Authorize.Net', 'sikshya'),
                'description' => __('Accept payments via Authorize.Net (US).', 'sikshya'),
                'tier' => 'pro',
                'enabled_setting_key' => 'enable_authorize_net_payment',
                'setting_keys' => [
                    'authorize_net_login_id',
                    'authorize_net_public_client_key',
                    'authorize_net_transaction_key',
                    'authorize_net_signature_key',
                ],
            ],
            [
                'id' => 'bank_transfer',
                'label' => __('Bank Transfer', 'sikshya'),
                'description' => __('Accept manual bank transfer payments.', 'sikshya'),
                'tier' => 'pro',
                'enabled_setting_key' => 'enable_bank_transfer_payment',
                'setting_keys' => [
                    'bank_transfer_bank_name',
                    'bank_transfer_account_name',
                    'bank_transfer_account_number',
                    'bank_transfer_routing_code',
                    'bank_transfer_instructions',
                ],
            ],
        ];

        /**
         * Register or modify gateways (addons can push their own gateways here).
         *
         * Each gateway:
         * - id: stable string
         * - tier: free|pro
         * - enabled_setting_key: settings option key (without _sikshya_ prefix)
         * - setting_keys: list of setting keys that belong to this gateway
         */
        $gateways = apply_filters('sikshya_payment_gateways_registry', $gateways);

        // Normalize.
        $out = [];
        foreach ((array) $gateways as $g) {
            if (!is_array($g)) {
                continue;
            }
            $id = isset($g['id']) ? sanitize_key((string) $g['id']) : '';
            if ($id === '') {
                continue;
            }
            $tier = isset($g['tier']) && (string) $g['tier'] === 'pro' ? 'pro' : 'free';
            $out[] = [
                'id' => $id,
                'label' => isset($g['label']) ? (string) $g['label'] : $id,
                'description' => isset($g['description']) ? (string) $g['description'] : '',
                'tier' => $tier,
                'enabled_setting_key' => isset($g['enabled_setting_key']) ? sanitize_key((string) $g['enabled_setting_key']) : '',
                'setting_keys' => isset($g['setting_keys']) && is_array($g['setting_keys'])
                    ? array_values(array_filter(array_map('sanitize_key', array_map('strval', $g['setting_keys']))))
                    : [],
            ];
        }

        return $out;
    }

    /**
     * Payload for admin UI.
     *
     * @return array<int, array{id: string, label: string, description: string, tier: string, locked: bool, enabled_setting_key: string, setting_keys: array<int, string>}>
     */
    public static function clientPayload(): array
    {
        $out = [];
        $proActive = TierCapabilities::isActive();
        $icons = self::iconMap();
        $base = defined('SIKSHYA_PLUGIN_URL') ? (string) constant('SIKSHYA_PLUGIN_URL') : '';
        foreach (self::all() as $g) {
            $tier = (string) ($g['tier'] ?? 'free');
            $locked = $tier === 'pro' && !$proActive;
            $id = (string) $g['id'];
            $icon_file = $icons[$id] ?? '';
            $icon_url = '';
            if ($base !== '' && $icon_file !== '') {
                $icon_url = $base . 'assets/images/payment-gateways/' . $icon_file;
            }
            $out[] = [
                'id' => $id,
                'label' => (string) $g['label'],
                'description' => (string) ($g['description'] ?? ''),
                'tier' => $tier,
                'locked' => $locked,
                'enabled_setting_key' => (string) ($g['enabled_setting_key'] ?? ''),
                'setting_keys' => isset($g['setting_keys']) && is_array($g['setting_keys']) ? $g['setting_keys'] : [],
                'icon_url' => $icon_url,
            ];
        }
        return $out;
    }
}

