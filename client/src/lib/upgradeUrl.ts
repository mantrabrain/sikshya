/**
 * Canonical upgrade URLs (Sikshya LMS pricing page) with UTM tagging so the
 * mantrabrain.com analytics can attribute conversions back to the in-product
 * upgrade surface that triggered them.
 *
 * Every upgrade/see-pricing CTA in the admin should route through this helper
 * — never link to a raw pricing URL directly. The UTM contract:
 *   utm_source   = sikshya        (the plugin family generating the click)
 *   utm_medium   = admin          (in-app admin context)
 *   utm_campaign = upgrade-gate   (the campaign — every gated screen)
 *   utm_content  = <button id>    (which button: upgrade-cta, see-plans, …)
 *   utm_term     = <feature id>   (which feature triggered the gate, optional)
 */

export const SIKSHYA_PRICING_URL = 'https://mantrabrain.com/plugins/sikshya-lms/pricing/';

export type UpgradeCtaContent =
  /** Primary "Upgrade to {Plan}" CTA on the gated screen */
  | 'upgrade-cta'
  /** Secondary "See pricing" / "See all plans" link on the gated screen */
  | 'see-plans'
  /** Primary "Upgrade to unlock" CTA on inline addon-enable cards */
  | 'addon-enable-upgrade'
  /** Sidebar "Pro" badge / generic in-product link */
  | 'sidebar-pro';

/**
 * Build a pricing-page URL with the standard UTM tags. Pass a feature id when
 * the click originated from a specific feature gate (e.g. `gradebook`).
 */
export function sikshyaPricingUrl(content: UpgradeCtaContent, featureId?: string): string {
  const params = new URLSearchParams({
    utm_source: 'sikshya',
    utm_medium: 'admin',
    utm_campaign: 'upgrade-gate',
    utm_content: content,
  });
  if (featureId && featureId.trim() !== '') {
    params.set('utm_term', featureId);
  }
  return `${SIKSHYA_PRICING_URL}?${params.toString()}`;
}
