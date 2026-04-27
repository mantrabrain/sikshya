import type { SikshyaLicensing, SikshyaReactConfig } from '../types';

export function getLicensing(config: SikshyaReactConfig): SikshyaLicensing | null {
  const lic = config.licensing;
  if (!lic || typeof lic !== 'object') {
    return null;
  }
  return lic as SikshyaLicensing;
}

/**
 * Plan entitlement for a catalog feature.
 * - Uses `featureStates[featureId]` when the server sent that key.
 * - Otherwise: **free** tier defaults to on; paid tiers default to off until `featureStates` arrives.
 * - Unknown feature ids (no catalog row) default to off.
 * @param config Full boot config (`SikshyaReactConfig`). Do not pass `config.licensing` alone — `getLicensing` would read the wrong shape.
 */
export function isFeatureEnabled(config: SikshyaReactConfig, featureId: string): boolean {
  const lic = getLicensing(config);
  if (lic?.featureStates && Object.prototype.hasOwnProperty.call(lic.featureStates, featureId)) {
    return Boolean(lic.featureStates[featureId]);
  }
  const entry = getCatalogEntry(config, featureId);
  if (!entry) {
    return false;
  }
  return (entry.tier ?? 'free').toLowerCase() === 'free';
}

export function getCatalogEntry(config: SikshyaReactConfig, featureId: string) {
  const lic = getLicensing(config);
  return lic?.catalog?.find((c) => c.id === featureId) ?? null;
}

/**
 * Human label for the minimum plan that includes this catalog feature (registry tier → copy).
 * @see docs/AI_ADDON_PREMIUM_UX_IMPLEMENTATION_BLUEPRINT.md Part D.3
 */
export function requiredPlanLabelForFeature(config: SikshyaReactConfig, featureId: string): string {
  const tier = getCatalogEntry(config, featureId)?.tier;
  if (tier === 'starter') {
    return 'Starter';
  }
  if (tier === 'pro') {
    return 'Growth';
  }
  if (tier === 'scale') {
    return 'Scale';
  }
  return 'Growth';
}

export type GatedWorkspaceMode = 'full' | 'locked-plan' | 'addon-off' | 'pending-addon';

export function resolveGatedWorkspaceMode(
  planOk: boolean,
  addonEnabled: boolean | null,
  addonLoading: boolean
): GatedWorkspaceMode {
  if (!planOk) {
    return 'locked-plan';
  }
  if (addonLoading && addonEnabled === null) {
    return 'pending-addon';
  }
  if (addonEnabled === true) {
    return 'full';
  }
  return 'addon-off';
}
