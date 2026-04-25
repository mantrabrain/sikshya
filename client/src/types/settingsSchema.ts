/** Mirrors PHP `SettingsManager` field/section shapes for the React settings UI. */

export type SettingsField = {
  key: string;
  type?: string;
  label?: string;
  description?: string;
  default?: string | number;
  placeholder?: string;
  /** If set, a first “Choose one…” option with empty value; empty saves as field default (PHP). */
  select_placeholder?: string;
  options?: Record<string, string>;
  min?: number;
  max?: number;
  /** For number inputs (e.g. tax rate decimals). */
  step?: number;
  /**
   * Pro-gating metadata populated by `SettingsManager::decorateSchemaGating()`.
   * When `locked` is true the field is read-only and rendered with an upgrade
   * overlay; `required_addon` / `required_feature` drive the upgrade CTA.
   */
  locked?: boolean;
  locked_reason?: string;
  required_addon?: string;
  /** Human label for required_addon (eg. "Course reviews & ratings"). */
  required_addon_label?: string;
  required_feature?: string;
  /** Human label for the minimum plan/tier (eg. "Starter", "Growth", "Scale"). */
  required_plan_label?: string;
};

export type SettingsSection = {
  section_key?: string;
  title?: string;
  icon?: string;
  description?: string;
  fields?: SettingsField[];
  locked?: boolean;
  locked_reason?: string;
  required_addon?: string;
  required_addon_label?: string;
  required_feature?: string;
  required_plan_label?: string;
};
