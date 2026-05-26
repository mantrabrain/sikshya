<?php

declare(strict_types=1);

namespace Sikshya\Api\Admin;

use Sikshya\Api\JwtAuthService;
use Sikshya\Constants\PostTypes;
use Sikshya\Core\Plugin;
use Sikshya\Security\AdminBackendAccess;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared scaffolding for admin-facing REST controllers.
 *
 * Mirrors {@see \Sikshya\Api\Learner\AbstractLearnerRestController}: pulls out the bits every
 * admin controller needs (the various capability-gated permission callbacks) so the 3,481-LOC
 * {@see \Sikshya\Api\AdminRestRoutes} god-class can be split one domain at a time without
 * duplicating the JWT + staff-backend dance in every subclass.
 *
 * Pattern decision (2026-05-14): abstract base controller. See the project memory entry
 * `project-rest-split-decision` for rationale and the alternatives considered.
 *
 * @package Sikshya\Api\Admin
 */
abstract class AbstractAdminRestController
{
    protected Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Register this controller's routes against the REST API. Concrete subclasses implement.
     */
    abstract public function register(): void;

    /**
     * LMS staff backend (cookie + nonce or JWT): matches {@see AdminBackendAccess::canAccessStaffBackend()}.
     *
     * @return bool|WP_Error
     */
    public function permissionReactApp(WP_REST_Request $request)
    {
        if (AdminBackendAccess::canAccessStaffBackend()) {
            return true;
        }

        return $this->validateJwtAndCheck($request, static function (): bool {
            return AdminBackendAccess::canAccessStaffBackend();
        });
    }

    /**
     * Site administrator only (payments, orders, coupons, Sikshya settings in wp-admin).
     *
     * @return bool|WP_Error
     */
    public function permissionManageOptions(WP_REST_Request $request)
    {
        if (AdminBackendAccess::canManageSalesAndSettings()) {
            return true;
        }

        return $this->validateJwtAndCheck($request, static function (): bool {
            return AdminBackendAccess::canManageSalesAndSettings();
        });
    }

    /**
     * Same as {@see self::permissionManageOptions()} — commerce UI is `manage_options` only.
     *
     * @return bool|WP_Error
     */
    public function permissionSalesCommerce(WP_REST_Request $request)
    {
        return $this->permissionManageOptions($request);
    }

    /**
     * Logged-in staff (cookie + nonce) or valid JWT; {@see AdminBackendAccess::canAccessStaffBackend()}.
     *
     * @return bool|WP_Error
     */
    public function permissionAdmin(WP_REST_Request $request)
    {
        if (AdminBackendAccess::canAccessStaffBackend()) {
            return true;
        }

        return $this->validateJwtAndCheck($request, static function (): bool {
            return AdminBackendAccess::canAccessStaffBackend();
        });
    }

    /**
     * Certificate preview: allow course builder admins, or any user who can edit that certificate
     * (wp-admin with cookie+nonce, without manage_sikshya / course caps).
     *
     * @return bool|WP_Error
     */
    public function permissionAdminOrCanEditCertificate(WP_REST_Request $request)
    {
        if (AdminBackendAccess::canAccessStaffBackend()) {
            return true;
        }

        $id = (int) $request->get_param('id');
        if ($id > 0 && self::userCanEditCertificate($id)) {
            return true;
        }

        $result = $this->validateJwtAndCheck($request, function () use ($id): bool {
            if (AdminBackendAccess::canAccessStaffBackend()) {
                return true;
            }

            return $id > 0 && self::userCanEditCertificate($id);
        });

        return $result;
    }

    /**
     * Maintainer tools (export/import, cache, diagnostics) — administrators only.
     *
     * @return bool|WP_Error
     */
    public function permissionTools()
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        return new WP_Error('rest_forbidden', __('Insufficient permissions', 'sikshya'), ['status' => 403]);
    }

    /**
     * Body parser shared by every mutating admin route: prefer JSON, fall back to form-encoded.
     * Returns an empty array if neither shape is present so callers can use the short-circuit
     * idiom `$p['field'] ?? ''` without isset checks.
     *
     * @return array<string, mixed>
     */
    protected function jsonBody(WP_REST_Request $request): array
    {
        $p = $request->get_json_params();
        if (is_array($p)) {
            return $p;
        }
        $b = $request->get_body_params();

        return is_array($b) ? $b : [];
    }

    /**
     * Common JWT bearer flow shared by every staff-backend permission callback: pull the JWT,
     * resolve to a user id, `wp_set_current_user`, then re-run a check closure. Returns true on
     * success or a `WP_Error` shaped exactly like the legacy inline implementation so wp-admin
     * UI behavior is unchanged.
     */
    private function validateJwtAndCheck(WP_REST_Request $request, callable $finalCheck)
    {
        $jwt = JwtAuthService::bearerFromRequest($request);
        if ($jwt === '') {
            return new WP_Error('rest_forbidden', __('Authentication required', 'sikshya'), ['status' => 401]);
        }

        $svc = $this->plugin->getService('jwtAuth');
        if (!$svc instanceof JwtAuthService) {
            return new WP_Error('rest_forbidden', __('JWT unavailable', 'sikshya'), ['status' => 500]);
        }

        $uid = $svc->validateToken($jwt);
        if (is_wp_error($uid)) {
            return $uid;
        }

        wp_set_current_user((int) $uid);

        return $finalCheck()
            ? true
            : new WP_Error('rest_forbidden', __('Insufficient permissions', 'sikshya'), ['status' => 403]);
    }

    /**
     * Used by the certificate-preview permission to decide whether a non-staff user owns the
     * certificate post; kept static so the closure-in-closure shape stays simple.
     */
    private static function userCanEditCertificate(int $id): bool
    {
        $post = get_post($id);

        return $post
            && $post->post_type === PostTypes::CERTIFICATE
            && current_user_can('edit_post', $id);
    }
}
