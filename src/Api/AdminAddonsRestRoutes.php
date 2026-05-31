<?php

namespace Sikshya\Api;

use Sikshya\Addons\Addons;
use Sikshya\Addons\AddonInterface;
use Sikshya\Addons\AddonManager;
use Sikshya\Core\Plugin;
use Sikshya\Licensing\TierCapabilities;
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
            $license_ok = TierCapabilities::feature($id);
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
            'docsUrl' => self::docsUrlForAddon($id),
        ];
    }

    /**
     * Documentation deep link for a specific addon section.
     *
     * Anchor IDs are taken verbatim from the live docs at
     * `https://sikshya.mantrabrain.com/docs/third-party-integrations` — the
     * markdown headings auto-generate slugs that don't 1:1 match the addon
     * IDs (e.g. `coupons_advanced` → `#advanced-coupons-upsells`,
     * `course_reviews` → `#course-reviews-ratings`), so a naive slugify of
     * the addon id produces broken anchors. This explicit map is the source
     * of truth.
     *
     * Sites that ship their own knowledge base can override:
     *   - `sikshya_addons_docs_base_url` — change the base for ALL addons
     *   - `sikshya_addons_docs_url_for_addon` — override per-addon URL
     */
    private static function docsUrlForAddon(string $id): string
    {
        $base = (string) apply_filters(
            'sikshya_addons_docs_base_url',
            'https://sikshya.mantrabrain.com/docs/',
            $id
        );
        $base = rtrim($base, '/') . '/';

        // addon id → [docs page slug, anchor fragment without `#`]
        $map = [
            // Build (curriculum & assets)
            'course_bundles'              => ['third-party-integrations', 'course-bundles'],
            'course_reviews'              => ['third-party-integrations', 'course-reviews-ratings'],
            'community_discussions'       => ['third-party-integrations', 'course-discussions-qa'],
            'multi_instructor'            => ['third-party-integrations', 'multi-instructor-co-authors'],
            'instructor_dashboard'        => ['third-party-integrations', 'instructor-dashboard'],
            'live_classes'                => ['third-party-integrations', 'live-classes-zoom-meet-classroom'],
            'scorm_h5p_pro'               => ['third-party-integrations', 'scorm-h5p'],
            // Teach (assessment, automation, learner journey)
            'content_drip'                => ['third-party-integrations', 'content-drip-scheduled-unlock'],
            'drip_notifications'          => ['third-party-integrations', 'drip-automation-emails'],
            'prerequisites'               => ['third-party-integrations', 'prerequisites-lessons-courses'],
            'calendar'                    => ['third-party-integrations', 'calendar'],
            'assignments_advanced'        => ['third-party-integrations', 'advanced-assignments'],
            'quiz_advanced'               => ['third-party-integrations', 'advanced-quiz-types-question-banks'],
            'gradebook'                   => ['third-party-integrations', 'gradebook'],
            'certificates_advanced'       => ['third-party-integrations', 'advanced-certificates-builder-qr-verification'],
            // Sell (revenue growth)
            'subscriptions'               => ['third-party-integrations', 'subscriptions-memberships'],
            'coupons_advanced'            => ['third-party-integrations', 'advanced-coupons-upsells'],
            'dynamic_checkout_fields'     => ['third-party-integrations', 'dynamic-checkout-fields'],
            'social_login'                => ['third-party-integrations', 'social-login'],
            // Operate (analytics, reporting, audit)
            'reports_advanced'            => ['third-party-integrations', 'advanced-analytics-exports'],
            'activity_log'                => ['third-party-integrations', 'student-activity-log'],
            'enterprise_reports'          => ['third-party-integrations', 'enterprise-reporting'],
            // Communicate (email, marketing, automation, API)
            'email_advanced_customization' => ['third-party-integrations', 'email-advanced-customization'],
            'crm_email_automation'        => ['third-party-integrations', 'crm-email-automation'],
            'email_marketing'             => ['third-party-integrations', 'email-marketing-mailchimp-mailerlite'],
            'webhooks'                    => ['third-party-integrations', 'webhooks'],
            'zapier'                      => ['third-party-integrations', 'zapier'],
            'public_api_keys'             => ['third-party-integrations', 'public-api-api-keys'],
            // Scale (marketplace, branding, enterprise)
            'marketplace_multivendor'     => ['third-party-integrations', 'multi-vendor-marketplace'],
            'white_label'                 => ['third-party-integrations', 'white-label-branding'],
            'multisite_scale'             => ['third-party-integrations', 'multisite-network-license-tools'],
            'multilingual_enterprise'     => ['third-party-integrations', 'multilingual-wpml-weglot'],
        ];

        if (isset($map[$id])) {
            [$page, $anchor] = $map[$id];
            $default = $base . $page . '/#' . $anchor;
        } else {
            // Unknown / future addon → land on the catalog page rather than
            // a guessed anchor that 404s.
            $default = $base . 'addons/';
        }

        return (string) apply_filters('sikshya_addons_docs_url_for_addon', $default, $id);
    }

    public function listAddons(): WP_REST_Response
    {
        $mgr = $this->addonManager();
        if (!$mgr) {
            return new WP_REST_Response(['success' => false, 'message' => __('Addons service unavailable', 'sikshya')], 500);
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
                'licensing' => TierCapabilities::getClientPayload(),
            ],
            200
        );
    }

    public function enableAddon(WP_REST_Request $request): WP_REST_Response
    {
        $id = sanitize_key((string) $request->get_param('id'));
        if ($id === '') {
            return new WP_REST_Response(['success' => false, 'message' => __('Missing addon id', 'sikshya')], 400);
        }

        $mgr = $this->addonManager();
        if (!$mgr) {
            return new WP_REST_Response(['success' => false, 'message' => __('Addons service unavailable', 'sikshya')], 500);
        }

        $reg = $mgr->registry();
        if (!isset($reg[$id])) {
            return new WP_REST_Response(['success' => false, 'message' => __('Unknown addon', 'sikshya')], 404);
        }

        $addon = $reg[$id];
        $tier = $addon->tier();
        if (($tier === 'starter' || $tier === 'pro' || $tier === 'scale') && !TierCapabilities::feature($id)) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => __('License required', 'sikshya'),
                    'code' => 'sikshya_plan_feature_required',
                    'legacy_error_code' => 'sikshya_pro_required',
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
            if (($depTier === 'starter' || $depTier === 'pro' || $depTier === 'scale') && !TierCapabilities::feature($dep)) {
                return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => __('A required add-on needs an active license.', 'sikshya'),
                    'code' => 'sikshya_plan_feature_required',
                    'legacy_error_code' => 'sikshya_pro_required',
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
            return new WP_REST_Response(['success' => false, 'message' => __('Missing addon id', 'sikshya')], 400);
        }

        $mgr = $this->addonManager();
        if (!$mgr) {
            return new WP_REST_Response(['success' => false, 'message' => __('Addons service unavailable', 'sikshya')], 500);
        }

        $reg = $mgr->registry();
        if (!isset($reg[$id])) {
            return new WP_REST_Response(['success' => false, 'message' => __('Unknown addon', 'sikshya')], 404);
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
            'community_discussions',
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

