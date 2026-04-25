/** Set from PHP when a nav target is plan-gated or the addon toggle is off (discoverability; routes stay visible). */
export type NavItemBadge = 'off' | 'upgrade';

export type NavItem = {
  id: string;
  label: string;
  /** Icon key from `assets/admin/icons/icons.json`. */
  icon?: string;
  href?: string;
  external?: boolean;
  /** Optional shell badge next to the label (`ReactAdminConfig::navigationItems`). */
  badge?: NavItemBadge;
  children?: NavItem[];
};

/** Full-width alerts below the shell header (from PHP `shellAlerts` + `sikshya_react_shell_alerts`). */
export type ShellAlert = {
  id: string;
  variant: 'info' | 'success' | 'warning' | 'error';
  title: string;
  message?: string;
  actions?: Array<{ label: string; href: string; external?: boolean }>;
};

/** Injected by PHP (`Pro::getClientPayload`) for upsell and per-feature UI locks. */
export type SikshyaLicensing = {
  isProActive: boolean;
  /** True when the Sikshya Pro plugin file is loaded (may still be unlicensed). */
  proPluginInstalled?: boolean;
  /** Mirrors PHP {@see \Sikshya\Licensing\Pro::siteTier()}. */
  siteTier: 'free' | 'starter' | 'growth' | 'scale';
  /** Mirrors PHP {@see \Sikshya\Licensing\Pro::siteTierLabel()}. */
  siteTierLabel?: string;
  upgradeUrl: string;
  featureStates: Record<string, boolean>;
  catalog: Array<{
    id: string;
    label: string;
    tier: 'free' | 'starter' | 'pro' | 'scale';
    group: string;
    description?: string;
    /** Long help shown in licensing UI; add-ons page uses REST `detailDescription`. */
    detailDescription?: string;
  }>;
};

export type SikshyaReactConfig = {
  page: string;
  /** Sikshya (free) plugin version. */
  version: string;
  /** Sikshya Pro add-on version when Pro is licensed (injected by PHP). */
  proVersion?: string;
  /** Installed Sikshya Pro add-on semver when the Pro plugin is loaded (even if not yet licensed). */
  proPluginVersion?: string;
  restUrl: string;
  /** WordPress core REST base (`wp/v2`) — respects plain permalinks. */
  wpRestUrl?: string;
  restNonce: string;
  adminUrl: string;
  /** Full URL to `admin.php?page=sikshya` (use {@link appViewHref} for subpages). */
  appAdminBase: string;
  /** PHP onboarding wizard (`admin.php?page=sikshya-setup`); set for administrators only. */
  setupWizardUrl?: string;
  siteUrl: string;
  pluginUrl: string;
  /** Global frontend permalink bases (from PHP `PermalinkService::get()`). */
  permalinks?: Record<string, string>;
  /** True when WordPress permalink structure is empty (plain permalinks). */
  plainPermalinks?: boolean;
  /** CPT keys needed for building example URLs. */
  postTypes?: { course?: string; lesson?: string; quiz?: string; assignment?: string };
  user: { name: string; avatarUrl: string };
  navigation: NavItem[];
  initialData: Record<string, unknown>;
  query: Record<string, string>;
  licensing?: SikshyaLicensing;
  /** Global banner row(s) under the top bar — not WordPress admin_notices. */
  shellAlerts?: ShellAlert[];
  /** Optional branding overrides from the Pro White label addon. */
  branding?: {
    pluginName?: string;
    logoUrl?: string;
    topbarBg?: string;
    topbarText?: string;
    sidebarBg?: string;
    sidebarText?: string;
  };
};

declare global {
  interface Window {
    sikshyaReact?: SikshyaReactConfig;
  }
}

export type FieldConfig = {
  type?: string;
  label?: string;
  description?: string;
  placeholder?: string;
  /** First “Choose one…” row for selects; empty selection saves as `default` (see CourseBuilder FieldInput). */
  select_placeholder?: string;
  required?: boolean;
  options?: Record<string, string>;
  default?: string | number;
  min?: number;
  max?: number;
  step?: number;
  multiple?: boolean;
  subfields?: Record<string, FieldConfig>;
  add_button_text?: string;
  /** From PHP tab definitions: grid width hints for the React builder. */
  layout?: 'full' | 'two_column' | 'three_column' | string;
  depends_on?: string;
  depends_value?: string;
  /** Show only when `values[depends_on]` is one of these values (OR). */
  depends_in?: string[];
  depends_all?: Array<{ on: string; value?: string }>;
  media_type?: string;
  validation?: string;
  sanitization?: string;
  /** Optional UI hint from PHP — React-only widgets. */
  widget?: string;
  /** Optional Pro gating hints (used by React to lock fields in-place). */
  required_addon?: string;
  required_feature?: string;
  required_addon_label?: string;
  required_plan_label?: string;
};

export type TabFieldsMap = Record<string, Record<string, { section?: { title?: string; description?: string }; fields?: Record<string, FieldConfig> }>>;

/** WordPress REST post object (subset). */
export type WpPost = {
  id: number;
  title: { rendered: string };
  excerpt?: { rendered?: string };
  slug?: string;
  status: string;
  link?: string;
  date?: string;
  modified?: string;
  author?: number;
  featured_media?: number;
  meta?: Record<string, unknown>;
  /** Computed by Sikshya REST: template permalink + ?hash=... (HMAC), for public preview. */
  sikshya_certificate_preview_url?: string;
  /**
   * Computed by Sikshya REST for unpublished course/lesson/quiz/assignment posts only:
   * preview URL signed with the capability-aware preview nonce. Empty / undefined for
   * published posts (use `link` instead) and for users without edit-post capability.
   */
  sikshya_preview_link?: string;
  /** Sikshya REST field for course lists (bundle vs regular). */
  sikshya_course_type?: string;
  /** Term IDs on the post (edit context). */
  sikshya_course_category?: number[];
  _embedded?: {
    author?: Array<{ id?: number; name?: string; slug?: string }>;
    'wp:featuredmedia'?: Array<{
      source_url?: string;
      alt_text?: string;
    }>;
    'wp:term'?: Array<Array<{ id?: number; name?: string; taxonomy?: string; slug?: string }>>;
  };
};

/** WordPress REST user (subset, `context=edit`). */
export type WpRestUser = {
  id: number;
  name: string;
  slug: string;
  email?: string;
  registered_date?: string;
};

/** WordPress REST term (subset). */
export type WpTerm = {
  id: number;
  name: string;
  slug: string;
  taxonomy?: string;
  count?: number;
  description?: string;
  /** Sikshya REST field: course category featured image attachment ID. */
  sikshya_category_image_id?: number;
  /** Sikshya REST field: course category featured image URL (thumbnail). */
  sikshya_category_image_url?: string;
};

