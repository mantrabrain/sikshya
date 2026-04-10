export type NavItem = {
  id: string;
  label: string;
  /** Icon key from `assets/admin/icons/icons.json`. */
  icon?: string;
  href?: string;
  external?: boolean;
  children?: NavItem[];
};

/** Injected by PHP (`Pro::getClientPayload`) for upsell and per-feature UI locks. */
export type SikshyaLicensing = {
  isProActive: boolean;
  /** Mirrors PHP {@see \Sikshya\Licensing\Pro::siteTier()}. */
  siteTier: 'free' | 'business' | 'agency' | 'elite';
  upgradeUrl: string;
  featureStates: Record<string, boolean>;
  catalog: Array<{
    id: string;
    label: string;
    tier: 'free' | 'pro' | 'elite';
    group: string;
    description?: string;
  }>;
};

export type SikshyaReactConfig = {
  page: string;
  version: string;
  restUrl: string;
  restNonce: string;
  adminUrl: string;
  /** Full URL to `admin.php?page=sikshya` (use {@link appViewHref} for subpages). */
  appAdminBase: string;
  siteUrl: string;
  pluginUrl: string;
  user: { name: string; avatarUrl: string };
  navigation: NavItem[];
  initialData: Record<string, unknown>;
  query: Record<string, string>;
  licensing?: SikshyaLicensing;
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
  depends_all?: Array<{ on: string; value?: string }>;
  media_type?: string;
  validation?: string;
  sanitization?: string;
};

export type TabFieldsMap = Record<string, Record<string, { section?: { title?: string; description?: string }; fields?: Record<string, FieldConfig> }>>;

/** WordPress REST post object (subset). */
export type WpPost = {
  id: number;
  title: { rendered: string };
  slug?: string;
  status: string;
  link?: string;
  date?: string;
  modified?: string;
  featured_media?: number;
  meta?: Record<string, unknown>;
  _embedded?: {
    'wp:featuredmedia'?: Array<{
      source_url?: string;
      alt_text?: string;
    }>;
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
};

