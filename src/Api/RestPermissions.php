<?php

declare(strict_types=1);

namespace Sikshya\Api;

use Sikshya\Core\Plugin;
use Sikshya\Security\AdminBackendAccess;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Static facade for REST permission callbacks.
 *
 * Consolidates the cookie+nonce / JWT auth dance previously duplicated across
 * {@see \Sikshya\Api\Admin\AbstractAdminRestController}, {@see \Sikshya\Api\Learner\AbstractLearnerRestController},
 * {@see \Sikshya\Api\PublicRestRoutes::requireLoginOrJwt()} and the Pro
 * permission helpers, so new routes can express their auth needs with a
 * single static reference instead of inheriting a base class or copy-pasting
 * the JWT bearer flow.
 *
 * **Adoption is incremental.** Existing controllers keep their instance-method
 * permission callbacks (`[$this, 'permissionAdmin']`, etc.) so no public REST
 * contract changes; new endpoints — and progressively refactored existing
 * ones — can adopt `[RestPermissions::class, 'forStaff']` instead.
 *
 * @see RestResponse for the matching response-envelope helpers.
 * @package Sikshya\Api
 */
final class RestPermissions
{
    /**
     * LMS staff backend (React admin shell + content REST). Honours cookie + nonce, or a JWT bearer.
     *
     * @return bool|WP_Error
     */
    public static function forStaff(WP_REST_Request $request)
    {
        if (AdminBackendAccess::canAccessStaffBackend()) {
            return true;
        }

        return self::validateJwtAndCheck($request, static function (): bool {
            return AdminBackendAccess::canAccessStaffBackend();
        });
    }

    /**
     * Site-administrator-only routes (orders, payments, coupons, Sikshya settings).
     * Cookie + nonce or JWT; final check is `manage_options` (or the LMS-equivalent cap bundle).
     *
     * @return bool|WP_Error
     */
    public static function forManageOptions(WP_REST_Request $request)
    {
        if (AdminBackendAccess::canManageSalesAndSettings()) {
            return true;
        }

        return self::validateJwtAndCheck($request, static function (): bool {
            return AdminBackendAccess::canManageSalesAndSettings();
        });
    }

    /**
     * Maintainer tools (export/import, cache, diagnostics) — `manage_options` only, no JWT path.
     *
     * @return bool|WP_Error
     */
    public static function forTools()
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __('Insufficient permissions', 'sikshya'),
            ['status' => 403]
        );
    }

    /**
     * Learner routes (the `/me/*` family). Logged-in cookie session or valid JWT bearer.
     *
     * @return bool|WP_Error
     */
    public static function forLearner(WP_REST_Request $request)
    {
        if (is_user_logged_in()) {
            return true;
        }

        $jwt = JwtAuthService::bearerFromRequest($request);
        if ($jwt === '') {
            return new WP_Error(
                'rest_forbidden',
                __('You must be logged in.', 'sikshya'),
                ['status' => 401]
            );
        }

        $svc = self::jwtService();
        if (!$svc instanceof JwtAuthService) {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication unavailable.', 'sikshya'),
                ['status' => 500]
            );
        }

        $uid = $svc->validateToken($jwt);
        if (is_wp_error($uid)) {
            return $uid;
        }

        wp_set_current_user((int) $uid);

        return true;
    }

    /**
     * Public route — anyone can call. Equivalent to `__return_true` but kept here so the
     * permission_callback parameter has a single, greppable home.
     */
    public static function forPublic(): bool
    {
        return true;
    }

    /**
     * Shared JWT bearer flow: pull the token, resolve to a user id, `wp_set_current_user`,
     * then re-run a check closure. Mirrors the shape of
     * {@see \Sikshya\Api\Admin\AbstractAdminRestController::validateJwtAndCheck()} so
     * downstream UI behaviour is unchanged.
     *
     * @return bool|WP_Error
     */
    private static function validateJwtAndCheck(WP_REST_Request $request, callable $finalCheck)
    {
        $jwt = JwtAuthService::bearerFromRequest($request);
        if ($jwt === '') {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required', 'sikshya'),
                ['status' => 401]
            );
        }

        $svc = self::jwtService();
        if (!$svc instanceof JwtAuthService) {
            return new WP_Error(
                'rest_forbidden',
                __('JWT unavailable', 'sikshya'),
                ['status' => 500]
            );
        }

        $uid = $svc->validateToken($jwt);
        if (is_wp_error($uid)) {
            return $uid;
        }

        wp_set_current_user((int) $uid);

        return $finalCheck()
            ? true
            : new WP_Error(
                'rest_forbidden',
                __('Insufficient permissions', 'sikshya'),
                ['status' => 403]
            );
    }

    /**
     * Resolve the JWT service from the plugin container. Centralised so JWT-dependent
     * permission paths share one indirection point.
     */
    private static function jwtService(): ?JwtAuthService
    {
        $svc = Plugin::getInstance()->getService('jwtAuth');

        return $svc instanceof JwtAuthService ? $svc : null;
    }
}
