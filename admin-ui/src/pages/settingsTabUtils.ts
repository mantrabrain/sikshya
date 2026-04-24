import type { SettingsSection } from '../types/settingsSchema';

/** REST / filters may return a single section object or a non-list; keep render paths safe. */
export function normalizeTabSections(raw: unknown): SettingsSection[] {
  if (Array.isArray(raw)) {
    return raw as SettingsSection[];
  }
  if (raw && typeof raw === 'object' && Array.isArray((raw as SettingsSection).fields)) {
    return [raw as SettingsSection];
  }
  return [];
}
