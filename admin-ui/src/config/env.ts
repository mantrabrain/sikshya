import type { NavItem, ShellAlert, SikshyaLicensing, SikshyaReactConfig } from '../types';

/**
 * PHP `wp_json_encode` turns sequential arrays into JSON arrays, but associative arrays
 * become objects. The sidebar expects an array; normalize so `.map` never runs on a plain object.
 */
function normalizeNavigation(raw: unknown): NavItem[] {
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
  if (!c) {
    throw new Error('sikshyaReact is not defined');
  }
  const rawProPv = (c as { proPluginVersion?: unknown }).proPluginVersion;
  const proPluginVersion =
    typeof rawProPv === 'string' && rawProPv.trim() !== '' ? rawProPv.trim() : undefined;

  return {
    ...c,
    navigation: normalizeNavigation(c.navigation),
    licensing: normalizeLicensing(c.licensing),
    shellAlerts: normalizeShellAlerts((c as { shellAlerts?: unknown }).shellAlerts),
    ...(proPluginVersion !== undefined ? { proPluginVersion } : {}),
  };
}
