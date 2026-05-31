<?php

declare(strict_types=1);

namespace Sikshya\Api\Admin;

use Sikshya\Database\Repositories\CouponRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin coupon CRUD.
 *
 * Extracted from {@see \Sikshya\Api\AdminRestRoutes} as the first domain to follow the
 * `AbstractAdminRestController` pattern. Owns `/sikshya/v1/admin/coupons` (GET/POST) and
 * `/sikshya/v1/admin/coupons/(?P<id>\d+)` (PATCH). Route paths and response shapes preserved
 * 1:1 with the original implementation.
 *
 * @package Sikshya\Api\Admin
 */
final class CouponRoutes extends AbstractAdminRestController
{
    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/admin/coupons', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAdminCoupons'],
                'permission_callback' => [$this, 'permissionSalesCommerce'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createAdminCoupon'],
                'permission_callback' => [$this, 'permissionSalesCommerce'],
            ],
        ]);

        register_rest_route($namespace, '/admin/coupons/(?P<id>\\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'patchAdminCoupon'],
                'permission_callback' => [$this, 'permissionSalesCommerce'],
            ],
        ]);

        register_rest_route($namespace, '/admin/coupons/checkout-toggle', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCheckoutToggle'],
                'permission_callback' => [$this, 'permissionSalesCommerce'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'setCheckoutToggle'],
                'permission_callback' => [$this, 'permissionSalesCommerce'],
            ],
        ]);
    }

    /**
     * Current state of the storefront `enable_coupons` flag.
     *
     * Returned alongside a tiny envelope the React admin can read without
     * touching the full settings tab payload.
     */
    public function getCheckoutToggle(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);
        $enabled = \Sikshya\Services\Settings::isTruthy(
            \Sikshya\Services\Settings::get('enable_coupons', false)
        );

        return new WP_REST_Response(
            [
                'ok' => true,
                'enabled' => $enabled,
            ],
            200
        );
    }

    /**
     * Flip the storefront `enable_coupons` flag without disturbing other
     * payment-tab settings. Body: `{ "enabled": true|false }`.
     */
    public function setCheckoutToggle(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = $request->get_body_params();
        }
        $enabled = !empty($body['enabled']);

        \Sikshya\Services\Settings::set('enable_coupons', $enabled ? '1' : '0');

        return new WP_REST_Response(
            [
                'ok' => true,
                'enabled' => $enabled,
            ],
            200
        );
    }

    /**
     * Coupon codes (basic CRUD list + create).
     */
    public function getAdminCoupons(WP_REST_Request $request): WP_REST_Response
    {
        $repo = new CouponRepository();
        if (!$repo->tableExists()) {
            return new WP_REST_Response(
                [
                    'ok' => true,
                    'coupons' => [],
                    'table_missing' => true,
                ],
                200
            );
        }

        $rows = $repo->findAll(200, 0);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'discount_type' => (string) $row->discount_type,
                'discount_value' => (float) $row->discount_value,
                'max_uses' => (int) $row->max_uses,
                'used_count' => (int) $row->used_count,
                'expires_at' => $row->expires_at ?? null,
                'status' => (string) $row->status,
            ];
        }

        return new WP_REST_Response(['ok' => true, 'coupons' => $out], 200);
    }

    /**
     * Create a coupon (admin UI).
     */
    public function createAdminCoupon(WP_REST_Request $request): WP_REST_Response
    {
        $repo = new CouponRepository();
        if (!$repo->tableExists()) {
            return new WP_REST_Response(
                ['ok' => false, 'message' => __('Coupons table not installed.', 'sikshya')],
                500
            );
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        $code = isset($params['code']) ? (string) $params['code'] : '';
        if (trim($code) === '') {
            return new WP_REST_Response(['ok' => false, 'message' => __('Code is required.', 'sikshya')], 400);
        }

        $id = $repo->createAdminCoupon(
            [
                'code' => $code,
                'discount_type' => $params['discount_type'] ?? 'percent',
                'discount_value' => $params['discount_value'] ?? 0,
                'max_uses' => $params['max_uses'] ?? 0,
                'expires_at' => $params['expires_at'] ?? null,
                'status' => $params['status'] ?? 'active',
            ]
        );

        if ($id <= 0) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Could not create coupon.', 'sikshya')], 500);
        }

        return new WP_REST_Response(['ok' => true, 'id' => $id], 201);
    }

    /**
     * Update coupon basics (code, discount, limits, status).
     */
    public function patchAdminCoupon(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($id <= 0) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Invalid coupon id.', 'sikshya')], 400);
        }

        $repo = new CouponRepository();
        if (!$repo->tableExists()) {
            return new WP_REST_Response(
                ['ok' => false, 'message' => __('Coupons table not installed.', 'sikshya')],
                500
            );
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        if (!is_array($params)) {
            $params = [];
        }

        $data = [];
        if (array_key_exists('code', $params)) {
            $data['code'] = (string) $params['code'];
        }
        if (array_key_exists('discount_type', $params)) {
            $data['discount_type'] = $params['discount_type'];
        }
        if (array_key_exists('discount_value', $params)) {
            $data['discount_value'] = $params['discount_value'];
        }
        if (array_key_exists('max_uses', $params)) {
            $data['max_uses'] = $params['max_uses'];
        }
        if (array_key_exists('expires_at', $params)) {
            $data['expires_at'] = $params['expires_at'];
        }
        if (array_key_exists('status', $params)) {
            $data['status'] = $params['status'];
        }

        if ($data === []) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Nothing to update.', 'sikshya')], 400);
        }

        $ok = $repo->updateAdminCoupon($id, $data);
        if (!$ok) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Could not update coupon.', 'sikshya')], 500);
        }

        return new WP_REST_Response(['ok' => true, 'id' => $id], 200);
    }
}
