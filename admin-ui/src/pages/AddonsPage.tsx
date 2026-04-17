import { useEffect, useMemo, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import type { NavItem, SikshyaReactConfig } from '../types';

type AddonTier = 'free' | 'starter' | 'pro' | 'elite';

type AddonRow = {
  id: string;
  label: string;
  description: string;
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
  licensing?: { isProActive?: boolean; siteTier?: string; upgradeUrl?: string; siteTierLabel?: string };
};

function tierBadge(tier: AddonTier): { label: string; className: string } {
  if (tier === 'elite') {
    return {
      label: 'Agency',
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
  if (tier === 'elite') return 'Agency';
  if (tier === 'pro') return 'Growth';
  if (tier === 'starter') return 'Starter';
  return 'Free';
}

function addonLicenseLocked(a: AddonRow): boolean {
  return (a.tier === 'starter' || a.tier === 'pro' || a.tier === 'elite') && !a.licenseOk;
}

export function AddonsPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const [data, setData] = useState<AddonsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [bulkBusy, setBulkBusy] = useState(false);
  const [error, setError] = useState<unknown>(null);
  const [q, setQ] = useState('');
  const [groupFilter, setGroupFilter] = useState<string>('all');
  const [tierFilter, setTierFilter] = useState<'all' | AddonTier>('all');
  const [sort, setSort] = useState<'name_asc' | 'name_desc' | 'tier' | 'status'>('name_asc');
  const [selected, setSelected] = useState<Record<string, boolean>>({});

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
      const s = `${a.label} ${a.description} ${a.group} ${a.tier} ${a.id}`.toLowerCase();
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
    if (sort === 'name_desc') {
      list.sort((a, b) => b.label.localeCompare(a.label));
    } else if (sort === 'tier') {
      const w = (t: AddonTier) => (t === 'free' ? 0 : t === 'starter' ? 1 : t === 'pro' ? 2 : 3);
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
    data?.licensing?.upgradeUrl || config.licensing?.upgradeUrl || 'https://store.mantrabrain.com/downloads/sikshya-pro/';

  const toggle = async (addon: AddonRow) => {
    setBusyId(addon.id);
    setError(null);
    try {
      const path = addon.enabled ? SIKSHYA_ENDPOINTS.admin.addonsDisable(addon.id) : SIKSHYA_ENDPOINTS.admin.addonsEnable(addon.id);
      const res = await getSikshyaApi().post<AddonsResponse>(path, {});
      setData(res);
    } catch (e) {
      setError(e);
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
      }
      setSelected({});
    } catch (e) {
      setError(e);
    } finally {
      setBulkBusy(false);
    }
  };

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle="Enable modules to load their settings, routes, and UI. Disabled addons do not execute."
      pageActions={null}
    >
      {error ? <ApiErrorPanel error={error} title="Could not load addons" onRetry={() => void refetch()} /> : null}

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
              Select visible
            </label>

            <button
              type="button"
              disabled={bulkBusy || selectedIds.length === 0}
              onClick={() => void bulkUpdate('enable')}
              className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
            >
              Enable selected
            </button>
            <button
              type="button"
              disabled={bulkBusy || selectedIds.length === 0}
              onClick={() => void bulkUpdate('disable')}
              className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
            >
              Disable selected
            </button>

            <span className="ml-1 text-sm text-slate-500 dark:text-slate-400">
              Showing <span className="font-semibold text-slate-700 dark:text-slate-200">{rows.length}</span> modules
            </span>
          </div>

          <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
            <input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Search modules…"
              className="w-full max-w-[360px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            />
            <select
              value={groupFilter}
              onChange={(e) => setGroupFilter(e.target.value)}
              className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 sm:w-[190px]"
              aria-label="Filter by category"
            >
              <option value="all">All categories</option>
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
              aria-label="Filter by tier"
            >
              <option value="all">All tiers</option>
              <option value="free">Free</option>
              <option value="starter">Starter</option>
              <option value="pro">Growth</option>
              <option value="elite">Agency</option>
            </select>
            <select
              value={sort}
              onChange={(e) => setSort(e.target.value as typeof sort)}
              className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 sm:w-[160px]"
              aria-label="Sort modules"
            >
              <option value="name_asc">Name A → Z</option>
              <option value="name_desc">Name Z → A</option>
              <option value="status">Enabled first</option>
              <option value="tier">Tier</option>
            </select>
            <button
              type="button"
              onClick={() => void refetch()}
              className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
              disabled={loading}
            >
              {loading ? 'Refreshing…' : 'Refresh'}
            </button>
          </div>
        </div>
      </div>

      {loading && !data ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">
          Loading addons…
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
                className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600"
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
                        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-600 dark:bg-slate-800"
                        aria-label={`Select ${a.label}`}
                      />
                      <div className="min-w-0 truncate text-sm font-semibold text-slate-900 dark:text-white">{a.label}</div>
                      <span
                        className={`shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${badge.className}`}
                      >
                        {badge.label}
                      </span>
                    </div>

                    {a.description ? (
                      <div className="mt-2 line-clamp-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        {a.description}
                      </div>
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
                  <div className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    <a className="font-semibold underline" href={upgradeUrl} target="_blank" rel="noreferrer noopener">
                      Upgrade
                    </a>{' '}
                    to enable this module.
                  </div>
                ) : null}
              </div>
            );
          })}
        </div>
      )}
    </AppShell>
  );
}

