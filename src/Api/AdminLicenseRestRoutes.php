<?php

namespace Sikshya\Api;

use Sikshya\Licensing\TierCapabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * License + upgrade UI REST. Commercial add-on supplies license operations via filters.
 *
 * @package Sikshya\Api
 */
final class AdminLicenseRestRoutes
{
    public static function register(): void
    {
        $ns = 'sikshya/v1';

        register_rest_route($ns, '/admin/license', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_license'],
                'permission_callback' => [self::class, 'perm'],
            ],
        ]);

        register_rest_route($ns, '/admin/license/activate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'activate'],
                'permission_callback' => [self::class, 'perm'],
                'args' => [
                    'license_key' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route($ns, '/admin/license/save', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'save'],
                'permission_callback' => [self::class, 'perm'],
                'args' => [
                    'license_key' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route($ns, '/admin/license/deactivate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'deactivate'],
                'permission_callback' => [self::class, 'perm'],
            ],
        ]);

        register_rest_route($ns, '/admin/license/check', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'check'],
                'permission_callback' => [self::class, 'perm'],
            ],
        ]);
    }

    /**
     * @return bool|WP_Error
     */
    public static function perm()
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error('rest_forbidden', __('You do not have permission to manage the license.', 'sikshya'), ['status' => 403]);
        }

        return true;
    }

    public static function get_license(WP_REST_Request $request): WP_REST_Response
    {
        $payload = self::base_payload();
        $payload['license_info'] = null;

        /** @var array<string, mixed> $payload */
        $payload = apply_filters('sikshya_rest_admin_license_payload', $payload, $request);

        $lic_info = null;
        if (isset($payload['license_info']) && is_array($payload['license_info'])) {
            $lic_info = $payload['license_info'];
        }
        $payload['commercial_plan_summary'] = self::build_commercial_plan_summary($lic_info);

        return new WP_REST_Response($payload, 200);
    }

    public static function activate(WP_REST_Request $request): WP_REST_Response
    {
        $out = apply_filters('sikshya_rest_admin_license_activate', null, $request);
        if (!is_array($out)) {
            return self::need_extension_response();
        }

        return new WP_REST_Response(self::normalize_response($out), self::status_code($out));
    }

    public static function save(WP_REST_Request $request): WP_REST_Response
    {
        $out = apply_filters('sikshya_rest_admin_license_save', null, $request);
        if (!is_array($out)) {
            return self::need_extension_response();
        }

        return new WP_REST_Response(self::normalize_response($out), 200);
    }

    public static function deactivate(WP_REST_Request $request): WP_REST_Response
    {
        $out = apply_filters('sikshya_rest_admin_license_deactivate', null, $request);
        if (!is_array($out)) {
            return self::need_extension_response();
        }

        return new WP_REST_Response(self::normalize_response($out), self::status_code($out));
    }

    public static function check(WP_REST_Request $request): WP_REST_Response
    {
        $out = apply_filters('sikshya_rest_admin_license_check', null, $request);
        if (!is_array($out)) {
            return self::need_extension_response();
        }

        return new WP_REST_Response(self::normalize_response($out), self::status_code($out));
    }

    /**
     * @return array<string, mixed>
     */
    private static function base_payload(): array
    {
        $lic = TierCapabilities::getClientPayload();

        return [
            /** Paid tier unlocked (active/valid license or dev filter). */
            'is_license_active' => (bool) ($lic['isProActive'] ?? false),
            /**
             * Commercial add-on PHP is loaded (filter) — kept as `pro_plugin_active` for admin shell compatibility.
             */
            'pro_plugin_active' => self::is_extension_runtime_active(),
            'upgrade_url' => (string) ($lic['upgradeUrl'] ?? 'https://mantrabrain.com/plugins/sikshya/#pricing'),
            'site_tier' => (string) ($lic['siteTier'] ?? 'free'),
            'site_tier_label' => (string) ($lic['siteTierLabel'] ?? ''),
        ];
    }

    /**
     * Whether the optional commercial add-on is loaded for this request.
     */
    private static function is_extension_runtime_active(): bool
    {
        /**
         * @param bool $loaded Default false.
         */
        return (bool) apply_filters('sikshya_commercial_extension_loaded', false);
    }

    /**
     * Human-readable commercial plan for the License screen (EDD variable price → tier).
     *
     * @param array<string, mixed>|null $license_info
     */
    private static function build_commercial_plan_summary(?array $license_info): string
    {
        if (!TierCapabilities::isActive()) {
            return '';
        }

        $tier_label = TierCapabilities::siteTierLabel();
        $sr = is_array($license_info) && isset($license_info['server_response']) && is_array($license_info['server_response'])
            ? $license_info['server_response']
            : [];

        $price_id = 0;
        if (isset($sr['price_id'])) {
            $price_id = (int) $sr['price_id'];
        } elseif (isset($sr['license_details']) && is_array($sr['license_details']) && isset($sr['license_details']['price_id'])) {
            $price_id = (int) $sr['license_details']['price_id'];
        }

        $cycle = '';
        if (in_array($price_id, [1, 2, 3], true)) {
            $cycle = ' (' . __('Yearly', 'sikshya') . ')';
        } elseif (in_array($price_id, [4, 5, 6], true)) {
            $cycle = ' (' . __('Lifetime', 'sikshya') . ')';
        }

        $item_name = isset($sr['item_name']) ? trim(sanitize_text_field((string) $sr['item_name'])) : '';
        if ($item_name !== '' && strcasecmp($item_name, $tier_label) !== 0) {
            return $item_name . ' — ' . $tier_label . $cycle;
        }

        return $tier_label . $cycle;
    }

    /**
     * @param array<string, mixed> $out
     * @return array<string, mixed>
     */
    private static function normalize_response(array $out): array
    {
        if (isset($out['notice'])) {
            $out['notice'] = wp_strip_all_tags((string) $out['notice']);
        }

        $merged = array_merge(self::base_payload(), $out);
        $lic_info = isset($merged['license_info']) && is_array($merged['license_info']) ? $merged['license_info'] : null;
        $merged['commercial_plan_summary'] = self::build_commercial_plan_summary($lic_info);

        return $merged;
    }

    /**
     * @param array<string, mixed> $out
     */
    private static function status_code(array $out): int
    {
        $st = isset($out['status']) ? (string) $out['status'] : '';

        return in_array($st, ['error', 'invalid', 'failed'], true) ? 400 : 200;
    }

    private static function need_extension_response(): WP_REST_Response
    {
        return new WP_REST_Response(
            array_merge(
                self::base_payload(),
                [
                    'status' => 'error',
                    'notice' => __('Install and activate the commercial Sikshya add-on to manage your license.', 'sikshya'),
                ]
            ),
            400
        );
    }
}
