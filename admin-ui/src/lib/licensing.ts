import type { SikshyaLicensing, SikshyaReactConfig } from '../types';

export function getLicensing(config: SikshyaReactConfig): SikshyaLicensing | null {
  const lic = config.licensing;
  if (!lic || typeof lic !== 'object') {
    return null;
  }
  return lic as SikshyaLicensing;
}

export function isFeatureEnabled(config: SikshyaReactConfig, featureId: string): boolean {
  const lic = getLicensing(config);
  if (!lic?.featureStates || !Object.prototype.hasOwnProperty.call(lic.featureStates, featureId)) {
    return true;
  }
  return Boolean(lic.featureStates[featureId]);
}
