<?php

namespace Sikshya\Api;

use Sikshya\Addons\Addons;
use Sikshya\Addons\AddonInterface;
use Sikshya\Addons\AddonManager;
use Sikshya\Core\Plugin;
use Sikshya\Licensing\Pro;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin endpoints for addon enablement (feature modules).
 */
final class AdminAddonsRestRoutes
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        $namespace = 'sikshya/v1';

        register_rest_route($namespace, '/admin/addons', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listAddons'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route($namespace, '/admin/addons/(?P<id>[a-z0-9_-]+)/enable', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'enableAddon'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route($namespace, '/admin/addons/(?P<id>[a-z0-9_-]+)/disable', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'disableAddon'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    private function addonManager(): ?AddonManager
    {
        $svc = $this->plugin->getService('addons');
        return $svc instanceof AddonManager ? $svc : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAddon(AddonInterface $addon): array
    {
        $id = $addon->id();
        $tier = $addon->tier();
        $enabled = Addons::isEnabled($id);

        $license_ok = true;
        if ($tier === 'starter' || $tier === 'pro' || $tier === 'scale') {
            // FeatureRegistry + Pro filters are the canonical license gate.
            $license_ok = Pro::feature($id);
        }

        return [
            'id' => $id,
            'label' => $addon->label(),
            'description' => $addon->description(),
            'detailDescription' => $addon->detailDescription(),
            'tier' => $tier,
            'group' => $addon->group(),
            'dependencies' => array_values(array_filter(array_map('sanitize_key', $addon->dependencies()))),
            'featureIds' => array_values(array_filter(array_map('sanitize_key', $addon->featureIds()))),
            'enabled' => $enabled,
            'licenseOk' => $license_ok,
        ];
    }

    public function listAddons(): WP_REST_Response
    {
        $mgr = $this->addonManager();
        if (!$mgr) {
            return new WP_REST_Response(['success' => false, 'message' => 'Addons service unavailable'], 500);
        }

        $items = [];
        foreach ($mgr->registry() as $id => $addon) {
            // Free-tier features ship with core and stay enabled; only paid tiers appear in Addons UI.
            if ($addon->tier() === 'free') {
                continue;
            }
            $items[] = $this->serializeAddon($addon);
        }

        self::sortAddonsByImportance($items);

        return new WP_REST_Response(
            [
                'success' => true,
                'enabled' => Addons::enabledIds(),
                'addons' => $items,
                'licensing' => Pro::getClientPayload(),
            ],
            200
        );
    }

    public function enableAddon(WP_REST_Request $request): WP_REST_Response
    {
        $id = sanitize_key((string) $request->get_param('id'));
        if ($id === '') {
            return new WP_REST_Response(['success' => false, 'message' => 'Missing addon id'], 400);
        }

        $mgr = $this->addonManager();
        if (!$mgr) {
            return new WP_REST_Response(['success' => false, 'message' => 'Addons service unavailable'], 500);
        }

        $reg = $mgr->registry();
        if (!isset($reg[$id])) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unknown addon'], 404);
        }

        $addon = $reg[$id];
        $tier = $addon->tier();
        if (($tier === 'starter' || $tier === 'pro' || $tier === 'scale') && !Pro::feature($id)) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'License required',
                    'code' => 'sikshya_pro_required',
                    'feature' => $id,
                ],
                403
            );
        }

        // Auto-enable dependencies.
        foreach ($addon->dependencies() as $dep) {
            $dep = sanitize_key((string) $dep);
            if ($dep === '' || !isset($reg[$dep])) {
                continue;
            }

            $depAddon = $reg[$dep];
            $depTier = $depAddon->tier();
            if (($depTier === 'starter' || $depTier === 'pro' || $depTier === 'scale') && !Pro::feature($dep)) {
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'message' => __('A required add-on needs an active license.', 'sikshya'),
                        'code' => 'sikshya_pro_required',
                        'feature' => $dep,
                        'required_by' => $id,
                    ],
                    403
                );
            }

            Addons::enable($dep);
        }
        Addons::enable($id);

        return $this->listAddons();
    }

    public function disableAddon(WP_REST_Request $request): WP_REST_Response
    {
        $id = sanitize_key((string) $request->get_param('id'));
        if ($id === '') {
            return new WP_REST_Response(['success' => false, 'message' => 'Missing addon id'], 400);
        }

        $mgr = $this->addonManager();
        if (!$mgr) {
            return new WP_REST_Response(['success' => false, 'message' => 'Addons service unavailable'], 500);
        }

        $reg = $mgr->registry();
        if (!isset($reg[$id])) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unknown addon'], 404);
        }

        if ($reg[$id]->tier() === 'free') {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => __('Free modules are included with Sikshya and cannot be disabled.', 'sikshya'),
                    'code' => 'sikshya_addon_free_always_on',
                ],
                400
            );
        }

        // Disabling does not auto-disable dependents (safe default).
        Addons::disable($id);

        return $this->listAddons();
    }

    /**
     * Paid add-on ids: most to least typical priority for site owners (mail & revenue → teaching → ops → platform).
     * Ids not listed (future extensions) sort after these, alphabetically by label.
     *
     * Keep in sync with `ADDON_IMPORTANCE_ORDER` in `client/src/pages/AddonsPage.tsx`.
     *
     * @return list<string>
     */
    private static function addonImportanceOrder(): array
    {
        return [
            'email_advanced_customization',
            'subscriptions',
            'content_drip',
            'course_bundles',
            'coupons_advanced',
            'multi_instructor',
            'prerequisites',
            'drip_notifications',
            'reports_advanced',
            'gradebook',
            'certificates_advanced',
            'activity_log',
            'assignments_advanced',
            'quiz_advanced',
            'instructor_dashboard',
            'email_marketing',
            'live_classes',
            'calendar',
            'social_login',
            'scorm_h5p_pro',
            'marketplace_multivendor',
            'white_label',
            'webhooks',
            'zapier',
            'public_api_keys',
            'enterprise_reports',
            'multilingual_enterprise',
            'multisite_scale',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private static function sortAddonsByImportance(array &$items): void
    {
        $order = self::addonImportanceOrder();
        $rank = [];
        foreach ($order as $i => $id) {
            $rank[$id] = $i;
        }
        $fallback = count($order);

        usort(
            $items,
            static function (array $a, array $b) use ($rank, $fallback): int {
                $ida = (string) ($a['id'] ?? '');
                $idb = (string) ($b['id'] ?? '');
                $ra = array_key_exists($ida, $rank) ? $rank[$ida] : $fallback;
                $rb = array_key_exists($idb, $rank) ? $rank[$idb] : $fallback;
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }

                return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
            }
        );
    }
}

