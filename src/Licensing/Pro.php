<?php

/**
 * Free vs commercial tiers — activation and per-feature gates.
 *
 * Sikshya Pro (separate plugin) sets filters; core never ships license keys.
 *
 * Commercial ladder (EDD variable prices map here via Sikshya Pro):
 * - starter — Starter plan (subset of Growth features).
 * - growth — Growth plan (full “Pro” catalog / former Business).
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
 * Licensing helper for REST, React bootstrap, and server-side guards.
 */
final class Pro
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
        return (bool) apply_filters('sikshya_pro_is_active', false);
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
         * @param string $tier Default **starter** when Pro is active but no tier resolved yet — Growth/Scale unlock only from license data.
         */
        $tier = apply_filters('sikshya_pro_site_tier', self::TIER_STARTER);
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
        return match (self::siteTier()) {
            self::TIER_STARTER => __('Starter', 'sikshya'),
            self::TIER_GROWTH => __('Growth', 'sikshya'),
            self::TIER_SCALE => __('Scale', 'sikshya'),
            default => __('Free', 'sikshya'),
        };
    }

    /**
     * Numeric rank for tier gating (higher = more features).
     */
    private static function siteTierRank(string $site): int
    {
        $site = self::normalizeSiteTierString($site);

        return match ($site) {
            self::TIER_FREE => 0,
            self::TIER_STARTER => 1,
            self::TIER_GROWTH => 2,
            self::TIER_SCALE => 3,
            default => 0,
        };
    }

    /**
     * Minimum rank required for a FeatureRegistry `tier` value.
     */
    private static function catalogTierRequiredRank(string $need): int
    {
        $need = strtolower(trim($need));

        return match ($need) {
            'free' => 0,
            'starter' => 1,
            'pro' => 2,
            'scale' => 3,
            default => 99,
        };
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
        return apply_filters('sikshya_pro_feature_states', $states);
    }

    /**
     * Payload for admin UI (all screens ship in free; use this for upsell + locks).
     *
     * @return array<string, mixed>
     */
    public static function getClientPayload(): array
    {
        $upgradeUrl = apply_filters(
            'sikshya_pro_upgrade_url',
            'https://store.mantrabrain.com/downloads/sikshya-pro/'
        );

        return [
            'isProActive' => self::isActive(),
            'proPluginInstalled' => defined('SIKSHYA_PRO_VERSION'),
            'siteTier' => self::siteTier(),
            'siteTierLabel' => self::siteTierLabel(),
            'upgradeUrl' => is_string($upgradeUrl) ? $upgradeUrl : 'https://store.mantrabrain.com/downloads/sikshya-pro/',
            'featureStates' => self::featureStates(),
            'catalog' => FeatureRegistry::catalogForClient(),
        ];
    }

    /**
     * Standard REST error when a Pro-only action is blocked.
     */
    public static function restFeatureRequired(string $featureId): \WP_Error
    {
        $def = FeatureRegistry::get($featureId);
        $label = is_array($def) && isset($def['label']) ? (string) $def['label'] : $featureId;

        return new \WP_Error(
            'sikshya_pro_required',
            sprintf(
                /* translators: %s: feature label */
                __('This action requires Sikshya Pro: %s', 'sikshya'),
                $label
            ),
            [
                'status' => 403,
                'feature' => $featureId,
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
