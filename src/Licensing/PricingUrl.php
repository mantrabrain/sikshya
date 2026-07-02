<?php

declare(strict_types=1);

namespace Sikshya\Licensing;

/**
 * Canonical pricing-page URLs with UTM tagging.
 *
 * Mirrors the JS helper at `client/src/lib/upgradeUrl.ts`. Every PHP upgrade
 * / see-pricing CTA should route through this helper so mantrabrain.com
 * analytics can attribute conversions back to the surface that triggered
 * them. UTM contract:
 *   utm_source   = sikshya        (the plugin family generating the click)
 *   utm_medium   = admin          (in-app admin context)
 *   utm_campaign = upgrade-gate   (the campaign — every gated surface)
 *   utm_content  = <surface id>   (which admin surface: license-screen, …)
 *   utm_term     = <feature id>   (which feature triggered the gate, optional)
 *
 * The old `#pricing` fragment URLs used to work when the landing page had
 * an inline pricing section — the mantrabrain.com site now serves pricing at
 * a dedicated `/pricing/` route, so the fragment silently 404s the click's
 * scroll-to intent. This helper produces the canonical path.
 */
final class PricingUrl
{
    public const BASE = 'https://mantrabrain.com/plugins/sikshya-lms/pricing/';

    /**
     * Build a pricing-page URL tagged with the standard UTM parameters.
     *
     * @param string      $content Surface id — appears as `utm_content`.
     *                             Free-form; existing values include
     *                             `licensing-payload`, `admin-nudge`,
     *                             `marketing-notice`, `license-screen`.
     * @param string|null $feature Optional feature slug when the click
     *                             originated from a specific gated screen
     *                             (appears as `utm_term`).
     */
    public static function withUtm(string $content, ?string $feature = null): string
    {
        $params = [
            'utm_source' => 'sikshya',
            'utm_medium' => 'admin',
            'utm_campaign' => 'upgrade-gate',
            'utm_content' => $content,
        ];

        if ($feature !== null && $feature !== '') {
            $params['utm_term'] = $feature;
        }

        return self::BASE . '?' . http_build_query($params);
    }
}
