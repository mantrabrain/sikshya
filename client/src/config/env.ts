import type { NavItem, ShellAlert, SikshyaLicensing, SikshyaReactConfig } from '../types';

/**
 * When PHP injects a partial `window.sikshyaReact` (or a filter strips keys), derive
 * `page` / `query` from the current admin URL so the shell never reads `undefined.page`.
 */
function viewFromAdminLocation(): { page: string; query: Record<string, string> } {
  try {
    const url = new URL(window.location.href);
    if (url.pathname.split('/').pop() === 'admin.php' && url.searchParams.get('page') === 'sikshya') {
      const page = (url.searchParams.get('view') || 'dashboard').trim() || 'dashboard';
      const query: Record<string, string> = {};
      url.searchParams.forEach((v, k) => {
        if (k === 'page' || k === 'view') return;
        query[k] = v;
      });
      return { page, query };
    }
  } catch {
    /* ignore */
  }
  return { page: 'dashboard', query: {} };
}

function coerceUser(raw: unknown): SikshyaReactConfig['user'] {
  if (!raw || typeof raw !== 'object') {
    return { name: 'Admin', avatarUrl: '' };
  }
  const u = raw as {
    name?: unknown;
    avatarUrl?: unknown;
    email?: unknown;
    profileUrl?: unknown;
    logoutUrl?: unknown;
  };
  const name = typeof u.name === 'string' && u.name.trim() !== '' ? u.name.trim() : 'Admin';
  const avatarUrl = typeof u.avatarUrl === 'string' ? u.avatarUrl : '';
  const email = typeof u.email === 'string' && u.email.trim() !== '' ? u.email.trim() : undefined;
  const profileUrl =
    typeof u.profileUrl === 'string' && u.profileUrl.trim() !== '' ? u.profileUrl.trim() : undefined;
  const logoutUrl =
    typeof u.logoutUrl === 'string' && u.logoutUrl.trim() !== '' ? u.logoutUrl.trim() : undefined;
  return { name, avatarUrl, email, profileUrl, logoutUrl };
}

/**
 * PHP `wp_json_encode` turns sequential arrays into JSON arrays, but associative arrays
 * become objects. The sidebar expects an array; normalize so `.map` never runs on a plain object.
 */
export function normalizeNavigation(raw: unknown): NavItem[] {
  if (Array.isArray(raw)) {
    return raw as NavItem[];
  }
  if (raw && typeof raw === 'object') {
    const vals = Object.values(raw as Record<string, unknown>);
    if (vals.length && vals.every((v) => Boolean(v) && typeof v === 'object')) {
      return vals as NavItem[];
    }
  }
  return [];
}

export function normalizeLicensing(raw: unknown): SikshyaLicensing | undefined {
  if (!raw || typeof raw !== 'object') {
    return undefined;
  }
  const lic = raw as SikshyaLicensing & { catalog?: unknown };
  const catalog = Array.isArray(lic.catalog) ? lic.catalog : [];
  return { ...lic, catalog };
}

function isShellAlertRow(v: unknown): v is ShellAlert {
  if (!v || typeof v !== 'object') return false;
  const o = v as Record<string, unknown>;
  const id = o.id;
  const variant = o.variant;
  const title = o.title;
  if (typeof id !== 'string' || id.trim() === '') return false;
  if (typeof title !== 'string' || title.trim() === '') return false;
  if (variant !== 'info' && variant !== 'success' && variant !== 'warning' && variant !== 'error') return false;
  return true;
}

export function normalizeShellAlerts(raw: unknown): ShellAlert[] {
  if (!Array.isArray(raw)) return [];
  const out: ShellAlert[] = [];
  for (const row of raw) {
    if (!isShellAlertRow(row)) continue;
    const actionsRaw = row.actions;
    let actions: ShellAlert['actions'];
    if (Array.isArray(actionsRaw)) {
      actions = [];
      for (const ar of actionsRaw) {
        if (!ar || typeof ar !== 'object') continue;
        const a = ar as Record<string, unknown>;
        const label = typeof a.label === 'string' ? a.label : '';
        const href = typeof a.href === 'string' ? a.href : '';
        if (!label || !href) continue;
        actions.push({
          label,
          href,
          external: a.external === true,
        });
      }
      if (actions.length === 0) actions = undefined;
    }
    out.push({
      id: row.id,
      variant: row.variant,
      title: row.title,
      message: typeof row.message === 'string' && row.message.trim() !== '' ? row.message : undefined,
      actions,
    });
  }
  return out;
}

export function getConfig(): SikshyaReactConfig {
  const c = window.sikshyaReact;
  if (!c || typeof c !== 'object') {
    // Fallback: render shell instead of crashing when a WP admin screen
    // outputs the React root but fails to inject inline bootstrap config.
    const { page: view, query } = viewFromAdminLocation();

    return {
      page: view,
      version: 'unknown',
      restUrl: '/?rest_route=/sikshya/v1/',
      wpRestUrl: '/?rest_route=/wp/v2/',
      restNonce: '',
      adminUrl: '/wp-admin/',
      appAdminBase: '/wp-admin/admin.php?page=sikshya',
      siteUrl: '/',
      pluginUrl: '',
      user: { name: 'Admin', avatarUrl: '' },
      navigation: [],
      initialData: {},
      query,
      shellAlerts: [
        {
          id: 'missing-react-bootstrap',
          variant: 'error',
          title: 'React admin bootstrap is missing.',
          message:
            'The admin screen loaded the React app but did not inject `window.sikshyaReact`. Please reload; if it persists, check that the `sikshya-react-admin` script handle is enqueued and `ReactAdminView::render()` runs for this view.',
        },
      ],
    };
  }

  const fromUrl = viewFromAdminLocation();
  const rawPage = (c as { page?: unknown }).page;
  const page =
    typeof rawPage === 'string' && rawPage.trim() !== '' ? rawPage.trim() : fromUrl.page;

  const rawQuery = (c as { query?: unknown }).query;
  const queryFromConfig =
    rawQuery && typeof rawQuery === 'object' && !Array.isArray(rawQuery)
      ? (rawQuery as Record<string, string>)
      : {};
  const query = Object.keys(queryFromConfig).length > 0 ? queryFromConfig : fromUrl.query;

  const rawVersion = (c as { version?: unknown }).version;
  const version = typeof rawVersion === 'string' && rawVersion.trim() !== '' ? rawVersion.trim() : 'unknown';

  const rawRest = (c as { restUrl?: unknown }).restUrl;
  const restUrl =
    typeof rawRest === 'string' && rawRest.trim() !== '' ? rawRest.trim() : '/?rest_route=/sikshya/v1/';

  const rawWpRest = (c as { wpRestUrl?: unknown }).wpRestUrl;
  const wpRestUrl =
    typeof rawWpRest === 'string' && rawWpRest.trim() !== '' ? rawWpRest.trim() : '/?rest_route=/wp/v2/';

  const rawNonce = (c as { restNonce?: unknown }).restNonce;
  const restNonce = typeof rawNonce === 'string' ? rawNonce : '';

  const rawAdminUrl = (c as { adminUrl?: unknown }).adminUrl;
  const adminUrl =
    typeof rawAdminUrl === 'string' && rawAdminUrl.trim() !== '' ? rawAdminUrl.trim() : '/wp-admin/';

  const rawAppBase = (c as { appAdminBase?: unknown }).appAdminBase;
  const appAdminBase =
    typeof rawAppBase === 'string' && rawAppBase.trim() !== ''
      ? rawAppBase.trim()
      : `${adminUrl.replace(/\/?$/, '/')}admin.php?page=sikshya`;

  const rawSiteUrl = (c as { siteUrl?: unknown }).siteUrl;
  const siteUrl = typeof rawSiteUrl === 'string' && rawSiteUrl.trim() !== '' ? rawSiteUrl.trim() : '/';

  const rawPluginUrl = (c as { pluginUrl?: unknown }).pluginUrl;
  const pluginUrl = typeof rawPluginUrl === 'string' ? rawPluginUrl : '';

  const rawInitial = (c as { initialData?: unknown }).initialData;
  const initialData =
    rawInitial && typeof rawInitial === 'object' && !Array.isArray(rawInitial)
      ? (rawInitial as Record<string, unknown>)
      : {};

  const rawProPv = (c as { proPluginVersion?: unknown }).proPluginVersion;
  const proPluginVersion =
    typeof rawProPv === 'string' && rawProPv.trim() !== '' ? rawProPv.trim() : undefined;

  const partialAlerts = normalizeShellAlerts((c as { shellAlerts?: unknown }).shellAlerts);
  const pageWasMissing = !(typeof rawPage === 'string' && rawPage.trim() !== '');
  const shellAlerts = [...partialAlerts];
  if (pageWasMissing && !shellAlerts.some((a) => a.id === 'react-config-partial')) {
    shellAlerts.push({
      id: 'react-config-partial',
      variant: 'warning',
      title: 'Admin bootstrap was incomplete.',
      message:
        '`window.sikshyaReact` did not include a `page` key; the current URL was used. If navigation looks wrong after an update, hard-refresh or clear opcode cache so PHP outputs the full config.',
    });
  }

  return {
    ...(c as SikshyaReactConfig),
    page,
    query,
    version,
    restUrl,
    wpRestUrl,
    restNonce,
    adminUrl,
    appAdminBase,
    siteUrl,
    pluginUrl,
    user: coerceUser((c as { user?: unknown }).user),
    navigation: normalizeNavigation((c as { navigation?: unknown }).navigation),
    initialData,
    licensing: normalizeLicensing((c as { licensing?: unknown }).licensing),
    shellAlerts,
    ...(proPluginVersion !== undefined ? { proPluginVersion } : {}),
  };
}
