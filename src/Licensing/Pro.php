<?php

/**
 * Free vs Pro / Elite — activation and per-feature gates.
 *
 * Sikshya Pro (separate plugin) sets filters; core never ships license keys.
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

    public const TIER_BUSINESS = 'business';

    public const TIER_AGENCY = 'agency';

    public const TIER_ELITE = 'elite';

    /**
     * True when Sikshya Pro (or another add-on) asserts an active paid license.
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
     * Paid SKU tier: business ≈ Pro roadmap; agency/elite unlock Elite features.
     */
    public static function siteTier(): string
    {
        if (!self::isActive()) {
            return self::TIER_FREE;
        }

        /**
         * Commercial plan: business | agency | elite
         *
         * @param string $tier Default business when Pro is active but filter unset.
         */
        $tier = apply_filters('sikshya_pro_site_tier', self::TIER_BUSINESS);
        $tier = is_string($tier) ? strtolower(trim($tier)) : self::TIER_BUSINESS;

        if (in_array($tier, [self::TIER_AGENCY, self::TIER_ELITE], true)) {
            return $tier;
        }

        return self::TIER_BUSINESS;
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

        $need = $def['tier'];
        if ($need === 'free') {
            return true;
        }

        if (!self::isActive()) {
            return false;
        }

        $site = self::siteTier();

        if ($need === 'pro') {
            return in_array($site, [self::TIER_BUSINESS, self::TIER_AGENCY, self::TIER_ELITE], true);
        }

        if ($need === 'elite') {
            return in_array($site, [self::TIER_AGENCY, self::TIER_ELITE], true);
        }

        return false;
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
            'https://sikshya.com/pricing/'
        );

        return [
            'isProActive' => self::isActive(),
            'siteTier' => self::siteTier(),
            'upgradeUrl' => is_string($upgradeUrl) ? $upgradeUrl : 'https://sikshya.com/pricing/',
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
}
