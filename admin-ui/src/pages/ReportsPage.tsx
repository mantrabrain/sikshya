import { useCallback, useEffect, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { ButtonPrimary } from '../components/shared/buttons';
import type { NavItem, SikshyaReactConfig } from '../types';

type ChartPayload = { labels?: string[]; counts?: number[] };

type StatsPayload = {
  published_courses?: number;
  total_enrollments?: number;
  distinct_learners?: number;
  completed_enrollments?: number;
  completion_rate?: number;
  revenue_html?: string;
  has_enrollment_table?: boolean;
  has_payments_table?: boolean;
};

type Snapshot = {
  chart: ChartPayload;
  stats: StatsPayload;
};

function StatCard(props: { label: string; value: string; hint?: string }) {
  const { label, value, hint } = props;
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{label}</p>
      <p className="mt-2 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{value}</p>
      {hint ? <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p> : null}
    </div>
  );
}

function bootSnapshot(config: SikshyaReactConfig): Snapshot {
  return {
    chart: (config.initialData.chart as ChartPayload) || {},
    stats: (config.initialData.stats as StatsPayload) || {},
  };
}

export function ReportsPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const boot = useMemo(() => bootSnapshot(config), [config.initialData]);
  const [snap, setSnap] = useState<Snapshot>(() => boot);
  const [busy, setBusy] = useState(false);

  const refresh = useCallback(async () => {
    setBusy(true);
    try {
      const d = await getSikshyaApi().get<Partial<Snapshot>>(SIKSHYA_ENDPOINTS.admin.reportsSnapshot);
      setSnap((prev) => ({
        chart: d.chart ?? prev.chart,
        stats: { ...prev.stats, ...(d.stats || {}) },
      }));
    } catch {
      /* keep current snapshot */
    } finally {
      setBusy(false);
    }
  }, []);

  useEffect(() => {
    setSnap(boot);
  }, [boot]);

  useEffect(() => {
    void refresh();
  }, [refresh, boot]);

  const { chart, stats } = snap;
  const labels = chart?.labels ?? [];
  const counts = chart?.counts ?? [];
  const maxCount = counts.length ? Math.max(...counts, 1) : 1;

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle="Enrollment overview and completion trends"
      pageActions={
        <ButtonPrimary type="button" disabled={busy} onClick={() => void refresh()}>
          {busy ? 'Refreshing…' : 'Refresh report'}
        </ButtonPrimary>
      }
    >
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Published courses" value={String(stats.published_courses ?? 0)} />
        <StatCard
          label="Total enrollments"
          value={String(stats.total_enrollments ?? 0)}
          hint={
            stats.has_enrollment_table
              ? 'From enrollments table'
              : 'Enrollments table not found — counts may stay at zero until migrations run.'
          }
        />
        <StatCard label="Active learners" value={String(stats.distinct_learners ?? 0)} />
        <StatCard
          label="Completion rate"
          value={`${stats.completion_rate ?? 0}%`}
          hint={`${stats.completed_enrollments ?? 0} completed`}
        />
      </div>

      <div className="mt-4 grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <h2 className="text-base font-semibold text-slate-900 dark:text-white">Enrollments by month</h2>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Last twelve months (UTC month buckets).</p>
          {labels.length ? (
            <div className="mt-6 flex h-48 items-end gap-1 sm:gap-2">
              {labels.map((label, i) => {
                const c = counts[i] ?? 0;
                const h = Math.round((c / maxCount) * 100);
                return (
                  <div key={`${label}-${i}`} className="flex min-w-0 flex-1 flex-col items-center gap-2">
                    <div className="flex w-full flex-1 items-end justify-center">
                      <div
                        className="w-full max-w-[2.5rem] rounded-t-md bg-brand-500/90 dark:bg-brand-400/90"
                        style={{ height: `${Math.max(h, c > 0 ? 8 : 2)}%` }}
                        title={`${c} enrollments`}
                      />
                    </div>
                    <span className="text-[10px] font-medium text-slate-500 dark:text-slate-400">{label}</span>
                  </div>
                );
              })}
            </div>
          ) : (
            <p className="mt-6 text-sm text-slate-500 dark:text-slate-400">
              No chart data yet. When the enrollments table is available and students enroll, bars will appear here.
            </p>
          )}
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <h2 className="text-base font-semibold text-slate-900 dark:text-white">Revenue</h2>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Completed payments total (if payments table exists).</p>
          <p className="mt-6 text-3xl font-bold tabular-nums text-slate-900 dark:text-white">
            {stats.revenue_html ? String(stats.revenue_html) : '—'}
          </p>
          {!stats.has_payments_table ? (
            <p className="mt-2 text-xs text-amber-800 dark:text-amber-200/90">
              Payments table not detected; revenue may show as zero until payments are recorded.
            </p>
          ) : null}
        </div>
      </div>
    </AppShell>
  );
}
