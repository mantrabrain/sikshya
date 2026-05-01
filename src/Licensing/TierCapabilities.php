<?php

/**
 * Free vs commercial tiers — activation and per-feature gates.
 *
 * A separate commercial add-on sets the `sikshya_commercial_*` filters; core never ships license keys.
 *
 * Plan ladder (store variable prices map via the add-on):
 * - starter — Starter plan (subset of Growth features).
 * - growth — Growth plan (full Growth catalog).
 * - scale — Scale plan (Growth + Scale-tier catalog features).
 *
 * @package Sikshya\Licensing
 */

namespace Sikshya\Licensing;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tier / catalog gate helper for REST, React bootstrap, and server-side guards.
 */
final class TierCapabilities
{
    public const TIER_FREE = 'free';

    public const TIER_STARTER = 'starter';

    public const TIER_GROWTH = 'growth';

    public const TIER_SCALE = 'scale';

    /**
     * @deprecated Use TIER_GROWTH (same value: "growth").
     */
    public const TIER_BUSINESS = 'growth';

    /**
     * Whether a commercial Sikshya tier is active for this site.
     */
    public static function isActive(): bool
    {
        /**
         * Whether a commercial Sikshya tier is active for this site.
         *
         * @param bool $active Default false (free-only).
         */
        return (bool) apply_filters('sikshya_commercial_is_active', false);
    }

    /**
     * Normalize store / alias tier strings to current slugs.
     */
    public static function normalizeSiteTierString(string $tier): string
    {
        $tier = strtolower(trim($tier));
        $map = [
            'business' => self::TIER_GROWTH,
            'pro' => self::TIER_GROWTH,
            'agency' => self::TIER_SCALE,
        ];

        return $map[$tier] ?? $tier;
    }

    /**
     * Paid SKU tier from license / filters: starter | growth | scale.
     */
    public static function siteTier(): string
    {
        if (!self::isActive()) {
            return self::TIER_FREE;
        }

        /**
         * Commercial plan slug (after normalization).
         *
         * @param string $tier Default starter when commercial tier is active but not resolved yet.
         */
        $tier = apply_filters('sikshya_commercial_site_tier', self::TIER_STARTER);
        $tier = is_string($tier) ? self::normalizeSiteTierString($tier) : self::TIER_STARTER;

        $allowed = [self::TIER_STARTER, self::TIER_GROWTH, self::TIER_SCALE];
        if (!in_array($tier, $allowed, true)) {
            return self::TIER_STARTER;
        }

        return $tier;
    }

    /**
     * Human-readable plan name for admin UI.
     */
    public static function siteTierLabel(): string
    {
        switch (self::siteTier()) {
            case self::TIER_STARTER:
                return __('Starter', 'sikshya');
            case self::TIER_GROWTH:
                return __('Growth', 'sikshya');
            case self::TIER_SCALE:
                return __('Scale', 'sikshya');
            default:
                return __('Free', 'sikshya');
        }
    }

    /**
     * Numeric rank for tier gating (higher = more features).
     */
    private static function siteTierRank(string $site): int
    {
        $site = self::normalizeSiteTierString($site);

        switch ($site) {
            case self::TIER_FREE:
                return 0;
            case self::TIER_STARTER:
                return 1;
            case self::TIER_GROWTH:
                return 2;
            case self::TIER_SCALE:
                return 3;
            default:
                return 0;
        }
    }

    /**
     * Minimum rank required for a FeatureRegistry `tier` value.
     */
    private static function catalogTierRequiredRank(string $need): int
    {
        $need = strtolower(trim($need));

        switch ($need) {
            case 'free':
                return 0;
            case 'starter':
                return 1;
            case 'pro':
                return 2;
            case 'scale':
                return 3;
            default:
                return 99;
        }
    }

    /**
     * Whether a catalog feature is enabled for this site.
     */
    public static function feature(string $featureId): bool
    {
        $def = FeatureRegistry::get($featureId);
        if ($def === null) {
            return true;
        }

        $need = isset($def['tier']) ? strtolower((string) $def['tier']) : 'free';
        if ($need === 'free') {
            return true;
        }

        if (!self::isActive()) {
            return false;
        }

        $site = self::siteTier();

        return self::siteTierRank($site) >= self::catalogTierRequiredRank($need);
    }

    /**
     * REST / React: full map of feature id => enabled.
     *
     * @return array<string, bool>
     */
    public static function featureStates(): array
    {
        $states = [];
        foreach (array_keys(FeatureRegistry::definitions()) as $id) {
            $states[$id] = self::feature((string) $id);
        }

        /**
         * Override feature toggles (e.g. beta, bundles).
         *
         * @param array<string, bool> $states
         */
        return apply_filters('sikshya_commercial_feature_states', $states);
    }

    /**
     * Payload for admin UI (all screens ship in free; use this for upsell + locks).
     *
     * @return array<string, mixed>
     */
    public static function getClientPayload(): array
    {
        $upgradeUrl = apply_filters(
            'sikshya_commercial_upgrade_url',
            'https://mantrabrain.com/plugins/sikshya/#pricing'
        );

        /**
         * Whether the optional commercial PHP package is present (add-on plugin loaded).
         *
         * @param bool $installed Default false.
         */
        $extensionInstalled = (bool) apply_filters('sikshya_commercial_extension_installed', false);

        return [
            'isProActive' => self::isActive(),
            'proPluginInstalled' => $extensionInstalled,
            'siteTier' => self::siteTier(),
            'siteTierLabel' => self::siteTierLabel(),
            'upgradeUrl' => is_string($upgradeUrl) ? $upgradeUrl : 'https://mantrabrain.com/plugins/sikshya/#pricing',
            'featureStates' => self::featureStates(),
            'catalog' => FeatureRegistry::catalogForClient(),
        ];
    }

    /**
     * Standard REST error when a catalog feature is not available on the current plan.
     */
    public static function restFeatureRequired(string $featureId): \WP_Error
    {
        $def = FeatureRegistry::get($featureId);
        $label = is_array($def) && isset($def['label']) ? (string) $def['label'] : $featureId;

        return new \WP_Error(
            'sikshya_plan_feature_required',
            sprintf(
                /* translators: %s: feature label */
                __('This action requires a higher Sikshya plan or add-on: %s', 'sikshya'),
                $label
            ),
            [
                'status' => 403,
                'feature' => $featureId,
                // Deprecated client alias; same semantics as top-level WP_Error code `sikshya_plan_feature_required`.
                'legacy_error_code' => 'sikshya_pro_required',
            ]
        );
    }

    /**
     * REST error when the catalog feature is licensed but the site toggle is off in Addons.
     */
    public static function restAddonDisabled(string $featureId): \WP_Error
    {
        return new \WP_Error(
            'sikshya_addon_disabled',
            __('This module is turned off in Addons.', 'sikshya'),
            [
                'status' => 403,
                'feature' => $featureId,
            ]
        );
    }
}
