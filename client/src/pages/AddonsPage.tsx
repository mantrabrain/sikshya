import { useEffect, useMemo, useState } from 'react';
import { useShellState } from '../context/ShellStateContext';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { getErrorSummary, getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { appViewHref } from '../lib/appUrl';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import type { NavItem, SikshyaReactConfig } from '../types';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';
import { t } from '../lib/i18n';

type AddonTier = 'free' | 'starter' | 'pro' | 'scale';

type AddonRow = {
  id: string;
  label: string;
  description: string;
  /** Longer help for hover/focus popover (plain language). */
  detailDescription?: string;
  tier: AddonTier;
  group: string;
  dependencies: string[];
  enabled: boolean;
  licenseOk: boolean;
};

type AddonsResponse = {
  success: boolean;
  addons: AddonRow[];
  enabled: string[];
  licensing?: {
    isProActive?: boolean;
    proPluginInstalled?: boolean;
    siteTier?: string;
    upgradeUrl?: string;
    siteTierLabel?: string;
  };
};

/**
 * Default list order (most → least important). Must match
 * {@see \Sikshya\Api\AdminAddonsRestRoutes::addonImportanceOrder}.
 */
const ADDON_IMPORTANCE_ORDER: readonly string[] = [
  // Commerce essentials (most used on selling sites).
  'subscriptions',
  'coupons_advanced',
  // Email + automation (commonly enabled early).
  'email_advanced_customization',
  'email_marketing',
  'webhooks',
  'zapier',
  'public_api_keys',
  // Learning experience + access rules (core LMS upgrades).
  'prerequisites',
  'content_drip',
  'drip_notifications',
  'community_discussions',
  // Teaching operations.
  'multi_instructor',
  'gradebook',
  'certificates_advanced',
  // Assessment + assignments.
  'quiz_advanced',
  'assignments_advanced',
  // Reporting + audit.
  'reports_advanced',
  'activity_log',
  'calendar',
  // Content formats / delivery.
  'live_classes',
  'scorm_h5p_pro',
  // Storefront / marketplace / packaging.
  'course_bundles',
  'marketplace_multivendor',
  // Identity + theming.
  'social_login',
  'white_label',
  // Niche / enterprise / scale.
  'instructor_dashboard',
  'enterprise_reports',
  'multilingual_enterprise',
  'multisite_scale',
];

function addonImportanceRank(id: string): number {
  const i = ADDON_IMPORTANCE_ORDER.indexOf(id);
  return i === -1 ? ADDON_IMPORTANCE_ORDER.length : i;
}

function tierBadge(tier: AddonTier): { label: string; className: string } {
  if (tier === 'scale') {
    return {
      label: 'Scale',
      className:
        'bg-purple-50 text-purple-800 ring-1 ring-purple-200 dark:bg-purple-950/40 dark:text-purple-200 dark:ring-purple-900/40',
    };
  }
  if (tier === 'pro') {
    return {
      label: 'Growth',
      className:
        'bg-brand-50 text-brand-800 ring-1 ring-brand-200 dark:bg-brand-950/40 dark:text-brand-200 dark:ring-brand-900/40',
    };
  }
  if (tier === 'starter') {
    return {
      label: 'Starter',
      className:
        'bg-amber-50 text-amber-900 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-100 dark:ring-amber-900/40',
    };
  }
  return {
    label: 'Free',
    className:
      'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900/40',
  };
}

function addonRequiresPlanLabel(tier: AddonTier): string {
  if (tier === 'scale') return 'Scale';
  if (tier === 'pro') return 'Growth';
  if (tier === 'starter') return 'Starter';
  return 'Free';
}

function addonLicenseLocked(a: AddonRow): boolean {
  return (a.tier === 'starter' || a.tier === 'pro' || a.tier === 'scale') && !a.licenseOk;
}

/** Human label for which commercial plan unlocks this catalog tier (matches PHP FeatureRegistry tiers). */
function tierPlanLabel(tier: AddonTier): string {
  if (tier === 'starter') return 'Starter';
  if (tier === 'pro') return 'Growth';
  if (tier === 'scale') return 'Scale';
  return 'Free';
}

/**
 * Card preview: PHP sends blocks separated by blank lines; showing that with `pre-line` created a large gap
 * between “what it is” and “when to enable”. We join blocks with spaces for a tight 3-line clamp.
 */
function addonCardPreviewText(raw: string): string {
  return raw
    .replace(/\r\n/g, '\n')
    .split(/\n\s*\n/)
    .map((block) => block.replace(/\s+/g, ' ').trim())
    .filter(Boolean)
    .join(' ');
}

/** Hover the short description to read the full add-on guide (popover). */
function AddonDescriptionWithHelp(props: { addonId: string; label: string; description: string; detailDescription?: string }) {
  const panelId = `sikshya-addon-detail-${props.addonId}`;
  const longText = (props.detailDescription && props.detailDescription.trim()) || props.description;
  const paragraphs = longText
    .split(/\n\n/)
    .map((p) => p.trim())
    .filter(Boolean);
  const preview = addonCardPreviewText(props.description);
  const hasPopover = paragraphs.length > 0;

  return (
    <div className="group/addonhelp relative mt-2 w-full min-w-0">
      <p
        className={`min-w-0 text-xs leading-snug text-slate-600 dark:text-slate-300 ${
          hasPopover ? 'line-clamp-3 cursor-help' : 'line-clamp-3'
        }`}
        aria-describedby={hasPopover ? panelId : undefined}
      >
        {preview}
      </p>
      {hasPopover ? (
        <div
          id={panelId}
          role="tooltip"
          className="pointer-events-none invisible absolute left-0 top-full z-50 -mt-1 w-full max-w-[min(calc(100vw-2rem),24rem)] max-h-[min(70vh,24rem)] translate-y-1 overflow-y-auto rounded-2xl border border-slate-300/90 bg-white p-4 text-left text-[13px] leading-relaxed text-slate-900 opacity-0 shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)] transition duration-150 group-hover/addonhelp:visible group-hover/addonhelp:translate-y-0 group-hover/addonhelp:opacity-100 group-hover/addonhelp:pointer-events-auto group-focus-within/addonhelp:visible group-focus-within/addonhelp:translate-y-0 group-focus-within/addonhelp:opacity-100 group-focus-within/addonhelp:pointer-events-auto dark:border-slate-500 dark:bg-slate-900 dark:text-slate-100 dark:shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)]"
        >
          <div className="mb-3 border-b border-slate-200 pb-2 dark:border-slate-600">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
              About this add-on
            </p>
          </div>
          <div className="space-y-3 text-slate-900 dark:text-slate-100">
            {paragraphs.map((p, i) => (
              <p key={i} className="text-slate-900 dark:text-slate-100">
                {p}
              </p>
            ))}
          </div>
        </div>
      ) : null}
    </div>
  );
}

export function AddonsPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const { refreshShell } = useShellState();
  const [data, setData] = useState<AddonsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [bulkBusy, setBulkBusy] = useState(false);
  const [error, setError] = useState<unknown>(null);
  const [q, setQ] = useState('');
  const [groupFilter, setGroupFilter] = useState<string>('all');
  const [tierFilter, setTierFilter] = useState<'all' | AddonTier>('all');
  const [sort, setSort] = useState<'importance' | 'name_asc' | 'name_desc' | 'tier' | 'status'>('importance');
  const [selected, setSelected] = useState<Record<string, boolean>>({});
  const toast = useTopRightToast(3200);

  const refetch = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getSikshyaApi().get<AddonsResponse>(SIKSHYA_ENDPOINTS.admin.addons);
      setData(res);
      setSelected((prev) => {
        // Drop selections for addons that no longer exist.
        const next: Record<string, boolean> = {};
        const list = Array.isArray(res.addons) ? res.addons : [];
        list.forEach((a) => {
          if (prev[a.id]) next[a.id] = true;
        });
        return next;
      });
    } catch (e) {
      setError(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void refetch();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const allRows = useMemo(() => {
    const all = Array.isArray(data?.addons) ? data.addons : [];
    const needle = q.trim().toLowerCase();
    return all.filter((a) => {
      if (tierFilter !== 'all' && a.tier !== tierFilter) return false;
      if (groupFilter !== 'all' && (a.group || 'general') !== groupFilter) return false;
      if (!needle) return true;
      const s =
        `${a.label} ${a.description} ${a.detailDescription ?? ''} ${a.group} ${a.tier} ${a.id}`.toLowerCase();
      return s.includes(needle);
    });
  }, [data, q, groupFilter, tierFilter]);

  const groups = useMemo(() => {
    const all = Array.isArray(data?.addons) ? data.addons : [];
    const set = new Set<string>();
    all.forEach((r) => set.add(r.group || 'general'));
    const list = Array.from(set).sort((a, b) => a.localeCompare(b));
    return list;
  }, [data]);

  const rows = useMemo(() => {
    const list = [...allRows];
    if (sort === 'importance') {
      list.sort((a, b) => addonImportanceRank(a.id) - addonImportanceRank(b.id) || a.label.localeCompare(b.label));
    } else if (sort === 'name_desc') {
      list.sort((a, b) => b.label.localeCompare(a.label));
    } else if (sort === 'tier') {
      const w = (t: AddonTier) =>
        t === 'free' ? 0 : t === 'starter' ? 1 : t === 'pro' ? 2 : t === 'scale' ? 3 : 0;
      list.sort((a, b) => w(a.tier) - w(b.tier) || a.label.localeCompare(b.label));
    } else if (sort === 'status') {
      // Enabled first, then locked, then name.
      const key = (a: AddonRow) => {
        const locked = addonLicenseLocked(a);
        return `${a.enabled ? '0' : '1'}-${locked ? '0' : '1'}-${a.label.toLowerCase()}`;
      };
      list.sort((a, b) => key(a).localeCompare(key(b)));
    } else {
      list.sort((a, b) => a.label.localeCompare(b.label));
    }
    return list;
  }, [allRows, sort]);

  const upgradeUrl =
    data?.licensing?.upgradeUrl ||
    config.licensing?.upgradeUrl ||
    'https://mantrabrain.com/plugins/sikshya/#pricing';

  const licensing = data?.licensing;
  const isProActive = licensing?.isProActive === true;
  const proPluginInstalled = licensing?.proPluginInstalled === true;
  const siteTierLabel = licensing?.siteTierLabel || config.licensing?.siteTierLabel || 'Free';

  const lockedCount = useMemo(() => {
    const list = Array.isArray(data?.addons) ? data.addons : [];
    return list.filter((a) => addonLicenseLocked(a)).length;
  }, [data?.addons]);

  const toggle = async (addon: AddonRow) => {
    setBusyId(addon.id);
    setError(null);
    toast.clear();
    try {
      const path = addon.enabled ? SIKSHYA_ENDPOINTS.admin.addonsDisable(addon.id) : SIKSHYA_ENDPOINTS.admin.addonsEnable(addon.id);
      const res = await getSikshyaApi().post<AddonsResponse>(path, {});
      setData(res);
      await refreshShell();
      if (addon.enabled) {
        toast.success('Disabled', `${addon.label} disabled.`);
      } else {
        toast.success('Enabled', `${addon.label} enabled.`);
      }
    } catch (e) {
      setError(e);
      toast.error('Action failed', getErrorSummary(e));
    } finally {
      setBusyId(null);
    }
  };

  const selectedIds = useMemo(() => Object.keys(selected).filter((k) => selected[k]), [selected]);
  const allVisibleSelected = useMemo(() => {
    if (!rows.length) return false;
    return rows.every((r) => !!selected[r.id]);
  }, [rows, selected]);

  const setAllVisibleSelected = (next: boolean) => {
    setSelected((prev) => {
      const out = { ...prev };
      rows.forEach((r) => {
        if (next) out[r.id] = true;
        else delete out[r.id];
      });
      return out;
    });
  };

  const bulkUpdate = async (mode: 'enable' | 'disable') => {
    const ids = selectedIds;
    if (!ids.length) return;
    setBulkBusy(true);
    setError(null);
    toast.clear();
    let updated = 0;
    try {
      // Sequential to keep server load predictable and responses consistent.
      for (const id of ids) {
        const row = (Array.isArray(data?.addons) ? data.addons : []).find((a) => a.id === id);
        if (!row) continue;
        const locked = addonLicenseLocked(row);
        if (locked) continue;
        if (mode === 'enable' && row.enabled) continue;
        if (mode === 'disable' && !row.enabled) continue;
        const path = mode === 'enable' ? SIKSHYA_ENDPOINTS.admin.addonsEnable(id) : SIKSHYA_ENDPOINTS.admin.addonsDisable(id);
        const res = await getSikshyaApi().post<AddonsResponse>(path, {});
        setData(res);
        updated++;
      }
      setSelected({});
      if (updated > 0) {
        await refreshShell();
        toast.success(mode === 'enable' ? 'Enabled' : 'Disabled', `${updated} add-on(s) updated.`);
      } else {
        toast.info('No changes', 'Nothing to update for the selected add-ons.');
      }
    } catch (e) {
      setError(e);
      toast.error('Bulk update failed', getErrorSummary(e));
    } finally {
      setBulkBusy(false);
    }
  };

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle="Sikshya Free always runs the core LMS. Optional add-ons extend it — turn each switch on only when you need that feature."
    >
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />
      {error ? <ApiErrorPanel error={error} title="Could not load addons" onRetry={() => void refetch()} /> : null}

      <div className="mb-4 rounded-2xl border border-sky-200 bg-sky-50/90 p-4 text-sm text-sky-950 shadow-sm dark:border-sky-900/50 dark:bg-sky-950/35 dark:text-sky-50">
        <p className="font-semibold text-sky-950 dark:text-sky-100">How Sikshya Free, Pro, and add-ons fit together</p>
        <ul className="mt-2 list-disc space-y-1.5 pl-5 leading-relaxed text-sky-950/95 dark:text-sky-100/95">
          <li>
            <span className="font-medium">Sikshya (free)</span> — courses, lessons, quizzes, enrollments, and basic
            checkout. Nothing on this page can “turn off” the core plugin.
          </li>
          <li>
            <span className="font-medium">Sikshya Pro (paid license)</span> — unlocks commercial plans (Starter, Growth,
            Scale). Install and activate the Pro plugin, then activate your license under{' '}
            <a className="font-semibold text-brand-700 underline hover:text-brand-800 dark:text-brand-300" href={appViewHref(config, 'license')}>
              License
            </a>
            .
          </li>
          <li>
            <span className="font-medium">Add-ons (this page)</span> — optional modules (subscriptions, drip, gradebook,
            …). Enable one, then open its menu in the sidebar to configure it. Site-wide defaults often live under{' '}
            <span className="font-medium">Settings</span>; per-feature tabs may say <span className="font-medium">Add-on defaults</span>.
          </li>
        </ul>
        <p className="mt-2 text-xs text-sky-900/85 dark:text-sky-200/85">
          Tip: in filters and badges, <span className="font-semibold">Growth</span> is the catalog name for the mid-tier
          Pro plan (technical tier <code className="rounded bg-white/60 px-1 py-0.5 text-[11px] dark:bg-slate-900/60">pro</code>
          ). <span className="font-semibold">Scale</span> is the highest tier.
        </p>
      </div>

      {data && !isProActive ? (
        <div className="mb-4 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-4 shadow-sm dark:border-amber-900/50 dark:from-amber-950/40 dark:to-slate-900">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <p className="text-sm font-semibold text-amber-950 dark:text-amber-100">Upgrade to Sikshya Pro</p>
              <p className="mt-1 text-sm leading-relaxed text-amber-900/90 dark:text-amber-200/90">
                Starter, Growth, and Scale add-ons on this page need an active Sikshya Pro license. After you purchase a
                plan, install and activate the Sikshya Pro plugin — then you can turn each add-on on here and use its admin
                screens and learner-facing features.
              </p>
              {!proPluginInstalled ? (
                <p className="mt-2 text-xs text-amber-800/80 dark:text-amber-300/80">
                  The Sikshya Pro plugin is not detected on this site yet. Use the download from your purchase email or
                  account to install it.
                </p>
              ) : null}
            </div>
            <a
              href={upgradeUrl}
              target="_blank"
              rel="noreferrer noopener"
              className="inline-flex shrink-0 items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"
            >
              View plans &amp; upgrade
            </a>
          </div>
        </div>
      ) : null}

      {data && isProActive && lockedCount > 0 ? (
        <div className="mb-4 rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
          Your site is on <span className="font-semibold">{siteTierLabel}</span>. Add-ons marked “Requires …” need a
          higher plan — use <span className="font-semibold">Upgrade to unlock</span> on each card or visit the store to
          upgrade.
        </div>
      ) : null}

      <div className="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-wrap items-center gap-2">
            <label className="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
              <input
                type="checkbox"
                checked={allVisibleSelected}
                onChange={(e) => setAllVisibleSelected(e.target.checked)}
                className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-600 dark:bg-slate-800"
              />
              {t('Select visible')}
            </label>

            <button
              type="button"
              disabled={bulkBusy || selectedIds.length === 0}
              onClick={() => void bulkUpdate('enable')}
              className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
            >
              {t('Enable selected')}
            </button>
            <button
              type="button"
              disabled={bulkBusy || selectedIds.length === 0}
              onClick={() => void bulkUpdate('disable')}
              className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
            >
              {t('Disable selected')}
            </button>

            <span className="ml-1 text-sm text-slate-500 dark:text-slate-400">
              {t('Showing')}{' '}
              <span className="font-semibold text-slate-700 dark:text-slate-200">{rows.length}</span>{' '}
              {t('add-ons')}
            </span>
          </div>

          <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
            <input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder={t('Search add-ons…')}
              className="w-full max-w-[360px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            />
            <select
              value={groupFilter}
              onChange={(e) => setGroupFilter(e.target.value)}
              className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 sm:w-[190px]"
              aria-label={t('Filter by category')}
            >
              <option value="all">{t('All categories')}</option>
              {groups.map((g) => (
                <option key={g} value={g}>
                  {g}
                </option>
              ))}
            </select>
            <select
              value={tierFilter}
              onChange={(e) => setTierFilter(e.target.value as 'all' | AddonTier)}
              className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 sm:w-[140px]"
              aria-label={t('Filter by tier')}
            >
              <option value="all">{t('All tiers')}</option>
              <option value="starter">{t('Starter')}</option>
              <option value="pro">{t('Growth')}</option>
              <option value="scale">{t('Scale')}</option>
            </select>
            <select
              value={sort}
              onChange={(e) => setSort(e.target.value as typeof sort)}
              className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 sm:w-[180px]"
              aria-label={t('Sort add-ons')}
            >
              <option value="importance">{t('Priority (most → least)')}</option>
              <option value="name_asc">{t('Name A → Z')}</option>
              <option value="name_desc">{t('Name Z → A')}</option>
              <option value="status">{t('Enabled first')}</option>
              <option value="tier">{t('Tier')}</option>
            </select>
            <button
              type="button"
              onClick={() => void refetch()}
              className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
              disabled={loading}
            >
              {loading ? t('Refreshing…') : t('Refresh')}
            </button>
          </div>
        </div>
      </div>

      {loading && !data ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">
          {t('Loading add-ons…')}
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
          {rows.map((a) => {
            const badge = tierBadge(a.tier);
            const locked = addonLicenseLocked(a);
            const busy = busyId === a.id || bulkBusy;
            const isSelected = !!selected[a.id];
            return (
              <div
                key={a.id}
                className={`rounded-2xl border p-4 shadow-sm transition dark:bg-slate-900 ${
                  locked
                    ? 'border-amber-200/90 bg-amber-50/50 hover:border-amber-300 dark:border-amber-800/60 dark:bg-amber-950/25 dark:hover:border-amber-700'
                    : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600'
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <input
                        type="checkbox"
                        checked={isSelected}
                        onChange={(e) =>
                          setSelected((prev) => {
                            const next = { ...prev };
                            if (e.target.checked) next[a.id] = true;
                            else delete next[a.id];
                            return next;
                          })
                        }
                        className="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-600 dark:bg-slate-800"
                        aria-label={`Select ${a.label}`}
                      />
                      <div className="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900 dark:text-white">
                        {a.label}
                      </div>
                      <span
                        className={`shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${badge.className}`}
                      >
                        {badge.label}
                      </span>
                    </div>

                    {a.description ? (
                      <AddonDescriptionWithHelp
                        addonId={a.id}
                        label={a.label}
                        description={a.description}
                        detailDescription={a.detailDescription}
                      />
                    ) : null}

                    <div className="mt-3 flex flex-wrap items-center gap-2">
                      <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {a.group || 'general'}
                      </span>
                      {a.dependencies?.length ? (
                        <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                          Depends: {a.dependencies.length}
                        </span>
                      ) : null}
                      {locked ? (
                        <span className="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/30 dark:text-amber-200 dark:ring-amber-900/40">
                          Requires {addonRequiresPlanLabel(a.tier)}
                        </span>
                      ) : null}
                    </div>
                  </div>

                  <button
                    type="button"
                    disabled={busy || locked}
                    onClick={() => toggle(a)}
                    className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition ${
                      a.enabled
                        ? 'bg-brand-600'
                        : 'bg-slate-200 dark:bg-slate-700'
                    } ${busy || locked ? 'opacity-60' : ''}`}
                    aria-label={`${a.enabled ? 'Disable' : 'Enable'} ${a.label}`}
                  >
                    <span
                      className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${
                        a.enabled ? 'translate-x-5' : 'translate-x-1'
                      }`}
                    />
                  </button>
                </div>

                {locked ? (
                  <div className="mt-3 flex flex-col gap-2 border-t border-amber-200/70 pt-3 dark:border-amber-900/50">
                    <p className="text-xs font-medium text-amber-950 dark:text-amber-100">
                      {isProActive
                        ? `Requires ${tierPlanLabel(a.tier)} plan or higher — upgrade your subscription to enable.`
                        : 'Requires Sikshya Pro — purchase a plan, install the Pro plugin, then enable this add-on.'}
                    </p>
                    <a
                      href={upgradeUrl}
                      target="_blank"
                      rel="noreferrer noopener"
                      className="inline-flex w-fit items-center rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"
                    >
                      Upgrade to unlock
                    </a>
                  </div>
                ) : null}
              </div>
            );
          })}
        </div>
      )}
    </EmbeddableShell>
  );
}

