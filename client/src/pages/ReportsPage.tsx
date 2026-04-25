import { useCallback, useEffect, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import type { SikshyaReactConfig } from '../types';

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

type QuizAttemptRow = {
  id: number;
  user_id: number;
  user_name: string;
  user_email: string;
  quiz_id: number;
  quiz_title: string;
  course_id: number;
  course_title: string;
  attempt_number: number;
  score: number;
  status: string;
  started_at: string;
  completed_at: string;
  attempts_used: number;
  attempts_limit: number;
  attempts_remaining: number | null;
  is_locked: boolean;
};

type QuizAttemptsResponse = {
  success?: boolean;
  attempts?: QuizAttemptRow[];
  total?: number;
  pages?: number;
  page?: number;
  per_page?: number;
  table_missing?: boolean;
};

type AdvancedExportResp = { ok?: boolean; csv?: string; filename?: string };

type EnterpriseStatusResp = {
  ok?: boolean;
  enabled?: boolean;
  recipient?: string;
  day_of_week?: number;
  hour?: number;
  next_run_unix?: number;
  next_run_iso?: string;
  last_run_unix?: number;
  last_run_iso?: string;
  last_status?: string;
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

export function ReportsPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const reportsAdvFeature = isFeatureEnabled(config, 'reports_advanced');
  const reportsAdvAddon = useAddonEnabled('reports_advanced');
  const reportsAdvMode = resolveGatedWorkspaceMode(
    reportsAdvFeature,
    reportsAdvAddon.enabled,
    reportsAdvAddon.loading
  );
  const reportsExportEnabled = reportsAdvMode === 'full';
  const [exportBusy, setExportBusy] = useState(false);

  const enterpriseFeature = isFeatureEnabled(config, 'enterprise_reports');
  const enterpriseAddon = useAddonEnabled('enterprise_reports');
  const enterpriseMode = resolveGatedWorkspaceMode(
    enterpriseFeature,
    enterpriseAddon.enabled,
    enterpriseAddon.loading
  );
  const enterpriseEnabled = enterpriseMode === 'full';
  const [enterpriseStatus, setEnterpriseStatus] = useState<EnterpriseStatusResp | null>(null);
  const [enterpriseBusy, setEnterpriseBusy] = useState(false);
  const [enterpriseSendBusy, setEnterpriseSendBusy] = useState(false);
  const [enterpriseMsg, setEnterpriseMsg] = useState<string | null>(null);
  const [enterpriseRecipients, setEnterpriseRecipients] = useState('');
  const [enterpriseDow, setEnterpriseDow] = useState(1);
  const [enterpriseHour, setEnterpriseHour] = useState(9);
  const [enterpriseSaveBusy, setEnterpriseSaveBusy] = useState(false);

  const refreshEnterprise = useCallback(async () => {
    if (!enterpriseEnabled) return;
    setEnterpriseBusy(true);
    try {
      const r = await getSikshyaApi().get<EnterpriseStatusResp>(SIKSHYA_ENDPOINTS.pro.enterpriseReportsStatus);
      setEnterpriseStatus(r);
      if (typeof r.recipient === 'string') setEnterpriseRecipients(r.recipient);
      if (typeof r.day_of_week === 'number') setEnterpriseDow(r.day_of_week);
      if (typeof r.hour === 'number') setEnterpriseHour(r.hour);
    } catch (e) {
      setEnterpriseMsg(e instanceof Error ? e.message : 'Could not load status');
    } finally {
      setEnterpriseBusy(false);
    }
  }, [enterpriseEnabled]);

  const sendEnterprise = async () => {
    if (!enterpriseEnabled) return;
    setEnterpriseMsg(null);
    setEnterpriseSendBusy(true);
    try {
      const r = await getSikshyaApi().post<{ ok?: boolean; message?: string }>(
        SIKSHYA_ENDPOINTS.pro.enterpriseReportsRun,
        {}
      );
      setEnterpriseMsg(r.message || 'Sent.');
      void refreshEnterprise();
    } catch (e) {
      setEnterpriseMsg(e instanceof Error ? e.message : 'Failed to send');
    } finally {
      setEnterpriseSendBusy(false);
    }
  };

  useEffect(() => {
    if (enterpriseEnabled && !enterpriseStatus) {
      void refreshEnterprise();
    }
  }, [enterpriseEnabled, enterpriseStatus, refreshEnterprise]);

  const saveEnterpriseSettings = async () => {
    if (!enterpriseEnabled) return;
    setEnterpriseMsg(null);
    setEnterpriseSaveBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.enterpriseReportsSettings, {
        enabled: true,
        recipients: enterpriseRecipients,
        day_of_week: enterpriseDow,
        hour: enterpriseHour,
      });
      setEnterpriseMsg('Saved scheduling settings.');
      void refreshEnterprise();
    } catch (e) {
      setEnterpriseMsg(e instanceof Error ? e.message : 'Failed to save settings');
    } finally {
      setEnterpriseSaveBusy(false);
    }
  };

  const boot = useMemo(() => bootSnapshot(config), [config.initialData]);
  const [snap, setSnap] = useState<Snapshot>(() => boot);
  const [busy, setBusy] = useState(false);
  const [attemptsBusy, setAttemptsBusy] = useState(false);
  const [attemptsResp, setAttemptsResp] = useState<QuizAttemptsResponse>({});
  const [attemptsPage, setAttemptsPage] = useState(1);
  const [attemptsPerPage] = useState(30);
  const [attemptsSearch, setAttemptsSearch] = useState('');
  const [attemptsStatus, setAttemptsStatus] = useState<'all' | 'completed' | 'in_progress' | 'passed' | 'failed'>('all');
  const [attemptsUserId, setAttemptsUserId] = useState('');
  const [attemptsCourseId, setAttemptsCourseId] = useState('');
  const [attemptsQuizId, setAttemptsQuizId] = useState('');

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

  const refreshAttempts = useCallback(async (page = 1) => {
    setAttemptsBusy(true);
    try {
      const params = new URLSearchParams();
      params.set('per_page', String(attemptsPerPage));
      params.set('page', String(page));
      if (attemptsSearch.trim()) params.set('search', attemptsSearch.trim());
      if (attemptsStatus !== 'all') params.set('status', attemptsStatus);
      if (attemptsUserId.trim()) params.set('user_id', attemptsUserId.trim());
      if (attemptsCourseId.trim()) params.set('course_id', attemptsCourseId.trim());
      if (attemptsQuizId.trim()) params.set('quiz_id', attemptsQuizId.trim());

      const d = await getSikshyaApi().get<QuizAttemptsResponse>(`${SIKSHYA_ENDPOINTS.admin.quizAttempts}?${params.toString()}`);
      setAttemptsResp(d || {});
      setAttemptsPage(page);
    } catch {
      setAttemptsResp((prev) => prev || {});
    } finally {
      setAttemptsBusy(false);
    }
  }, [attemptsCourseId, attemptsPerPage, attemptsQuizId, attemptsSearch, attemptsStatus, attemptsUserId]);

  useEffect(() => {
    setSnap(boot);
  }, [boot]);

  useEffect(() => {
    void refresh();
  }, [refresh, boot]);

  useEffect(() => {
    void refreshAttempts(1);
  }, [refreshAttempts, boot]);

  useEffect(() => {
    void refreshAttempts(1);
  }, [attemptsSearch, attemptsStatus, attemptsUserId, attemptsCourseId, attemptsQuizId, refreshAttempts]);

  const exportAdvancedCsv = async () => {
    if (!reportsExportEnabled) return;
    setExportBusy(true);
    try {
      const r = await getSikshyaApi().get<AdvancedExportResp>(SIKSHYA_ENDPOINTS.pro.reportsExport);
      const csv = r.csv || '';
      const name = r.filename || 'sikshya-advanced-report.csv';
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = name;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    } finally {
      setExportBusy(false);
    }
  };

  const { chart, stats } = snap;
  const labels = chart?.labels ?? [];
  const counts = chart?.counts ?? [];
  const maxCount = counts.length ? Math.max(...counts, 1) : 1;
  const attemptRows = attemptsResp.attempts ?? [];
  const attemptsPages = attemptsResp.pages ?? 0;
  const attemptsTotal = attemptsResp.total ?? 0;

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
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

      <div className="mt-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-slate-900 dark:text-white">Quiz attempts</h2>
            {attemptsTotal > 0 ? (
              <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {attemptsTotal} total • Page {attemptsPage}
                {attemptsPages > 0 ? ` of ${attemptsPages}` : ''}
              </p>
            ) : null}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
              disabled={attemptsBusy || attemptsPage <= 1}
              onClick={() => void refreshAttempts(Math.max(1, attemptsPage - 1))}
            >
              Prev
            </button>
            <button
              type="button"
              className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
              disabled={attemptsBusy || (attemptsPages > 0 ? attemptsPage >= attemptsPages : attemptRows.length < attemptsPerPage)}
              onClick={() => void refreshAttempts(attemptsPage + 1)}
            >
              Next
            </button>
            <ButtonPrimary type="button" disabled={attemptsBusy} onClick={() => void refreshAttempts(attemptsPage)}>
              {attemptsBusy ? 'Refreshing…' : 'Refresh'}
            </ButtonPrimary>
          </div>
        </div>

        <div className="mt-4 grid gap-3 lg:grid-cols-12">
          <div className="lg:col-span-5">
            <label className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Search</label>
            <input
              value={attemptsSearch}
              onChange={(e) => setAttemptsSearch(e.target.value)}
              placeholder="Learner name/email, quiz, course…"
              className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none ring-0 placeholder:text-slate-400 focus:border-brand-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
          </div>
          <div className="lg:col-span-2">
            <label className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</label>
            <select
              value={attemptsStatus}
              onChange={(e) => setAttemptsStatus(e.target.value as typeof attemptsStatus)}
              className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none ring-0 focus:border-brand-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            >
              <option value="all">All</option>
              <option value="completed">Completed</option>
              <option value="in_progress">In progress</option>
              <option value="passed">Passed</option>
              <option value="failed">Failed</option>
            </select>
          </div>
          <div className="lg:col-span-1">
            <label className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">User ID</label>
            <input
              value={attemptsUserId}
              onChange={(e) => setAttemptsUserId(e.target.value.replace(/[^\d]/g, ''))}
              placeholder="#"
              inputMode="numeric"
              className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none ring-0 placeholder:text-slate-400 focus:border-brand-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
          </div>
          <div className="lg:col-span-2">
            <label className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Course ID</label>
            <input
              value={attemptsCourseId}
              onChange={(e) => setAttemptsCourseId(e.target.value.replace(/[^\d]/g, ''))}
              placeholder="#"
              inputMode="numeric"
              className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none ring-0 placeholder:text-slate-400 focus:border-brand-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
          </div>
          <div className="lg:col-span-2">
            <label className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Quiz ID</label>
            <input
              value={attemptsQuizId}
              onChange={(e) => setAttemptsQuizId(e.target.value.replace(/[^\d]/g, ''))}
              placeholder="#"
              inputMode="numeric"
              className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none ring-0 placeholder:text-slate-400 focus:border-brand-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
          </div>
        </div>

        {attemptsResp.table_missing ? (
          <p className="mt-4 text-sm text-amber-800 dark:text-amber-200/90">
            Quiz attempts table not detected yet. Run plugin migrations and record at least one attempt to see rows here.
          </p>
        ) : attemptRows.length ? (
          <div className="mt-4 overflow-auto rounded-xl border border-slate-100 dark:border-slate-800">
            <table className="min-w-[980px] w-full border-collapse text-sm">
              <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                <tr>
                  <th className="px-4 py-3">Learner</th>
                  <th className="px-4 py-3">Quiz</th>
                  <th className="px-4 py-3">Course</th>
                  <th className="px-4 py-3">Attempt #</th>
                  <th className="px-4 py-3">Used / Limit</th>
                  <th className="px-4 py-3">Remaining</th>
                  <th className="px-4 py-3">Score</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Completed</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {attemptRows.map((r) => (
                  <tr key={r.id} className="text-slate-700 dark:text-slate-200">
                    <td className="px-4 py-3">
                      <div className="font-medium text-slate-900 dark:text-white">{r.user_name || `User #${r.user_id}`}</div>
                      <div className="text-xs text-slate-500 dark:text-slate-400">{r.user_email}</div>
                    </td>
                    <td className="px-4 py-3">{r.quiz_title || `Quiz #${r.quiz_id}`}</td>
                    <td className="px-4 py-3">{r.course_title || (r.course_id ? `Course #${r.course_id}` : '—')}</td>
                    <td className="px-4 py-3 tabular-nums">{r.attempt_number || '—'}</td>
                    <td className="px-4 py-3 tabular-nums">
                      {r.attempts_limit > 0 ? `${r.attempts_used} / ${r.attempts_limit}` : `${r.attempts_used} / ∞`}
                    </td>
                    <td className="px-4 py-3 tabular-nums">{typeof r.attempts_remaining === 'number' ? r.attempts_remaining : '—'}</td>
                    <td className="px-4 py-3 tabular-nums">{Number.isFinite(r.score) ? String(r.score) : '—'}</td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-flex rounded-full px-2 py-1 text-[11px] font-semibold ${
                          r.is_locked
                            ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
                            : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
                        }`}
                      >
                        {r.is_locked ? 'Locked' : r.status || '—'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{r.completed_at || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="mt-4 text-sm text-slate-500 dark:text-slate-400">No quiz attempts recorded yet.</p>
        )}
      </div>

      <div className="mt-4">
        <GatedFeatureWorkspace
          mode={reportsAdvMode}
          featureId="reports_advanced"
          config={config}
          featureTitle="Advanced analytics export"
          featureDescription="Download a CSV snapshot of headline metrics (courses, enrollments, learners, revenue) for spreadsheets and offline planning."
          previewVariant="generic"
          addonEnableTitle="Advanced export is not enabled"
          addonEnableDescription="Enable the Advanced analytics add-on to unlock the downloadable export alongside your on-screen charts."
          canEnable={Boolean(reportsAdvAddon.licenseOk)}
          enableBusy={reportsAdvAddon.loading}
          onEnable={() => void reportsAdvAddon.enable()}
          addonError={reportsAdvAddon.error}
        >
          <ListPanel>
            <div className="flex flex-wrap items-start justify-between gap-3 p-6">
              <div>
                <h2 className="text-base font-semibold text-slate-900 dark:text-white">Spreadsheet export (Growth+)</h2>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                  Download a CSV of key totals for Excel or Google Sheets. This does not replace the live charts above—it adds
                  an offline-friendly summary.
                </p>
              </div>
              {reportsExportEnabled ? (
                <ButtonPrimary type="button" disabled={exportBusy} onClick={() => void exportAdvancedCsv()}>
                  {exportBusy ? 'Preparing…' : 'Download CSV'}
                </ButtonPrimary>
              ) : null}
            </div>
          </ListPanel>
        </GatedFeatureWorkspace>
      </div>

      <div className="mt-4">
        <GatedFeatureWorkspace
          mode={enterpriseMode}
          featureId="enterprise_reports"
          config={config}
          featureTitle="Enterprise weekly summary"
          featureDescription="Schedule a weekly summary email to the site administrator with revenue, enrollments, and completion totals."
          previewVariant="generic"
          addonEnableTitle="Enterprise reports is not enabled"
          addonEnableDescription="Enable the Enterprise reports add-on to schedule the weekly summary email cron."
          canEnable={Boolean(enterpriseAddon.licenseOk)}
          enableBusy={enterpriseAddon.loading}
          onEnable={() => void enterpriseAddon.enable()}
          addonError={enterpriseAddon.error}
        >
          <ListPanel>
            <div className="flex flex-wrap items-start justify-between gap-3 p-6">
              <div>
                <h2 className="text-base font-semibold text-slate-900 dark:text-white">Weekly summary email</h2>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                  Sends to{' '}
                  <span className="font-medium text-slate-700 dark:text-slate-200">
                    {enterpriseStatus?.recipient || 'site admin email'}
                  </span>
                  {enterpriseStatus?.next_run_iso ? (
                    <>
                      . Next scheduled run:{' '}
                      <span className="font-medium">{enterpriseStatus.next_run_iso}</span>
                    </>
                  ) : (
                    '.'
                  )}
                </p>
                {enterpriseStatus?.last_run_iso ? (
                  <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Last run: <span className="font-medium">{enterpriseStatus.last_run_iso}</span>
                    {enterpriseStatus.last_status ? (
                      <>
                        {' '}
                        · status: <span className="font-medium">{enterpriseStatus.last_status}</span>
                      </>
                    ) : null}
                  </p>
                ) : null}
                {enterpriseMsg ? (
                  <p className="mt-1 text-xs text-slate-600 dark:text-slate-400">{enterpriseMsg}</p>
                ) : null}
              </div>
              {enterpriseEnabled ? (
                <div className="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    onClick={() => void refreshEnterprise()}
                    disabled={enterpriseBusy}
                  >
                    {enterpriseBusy ? 'Refreshing…' : 'Refresh status'}
                  </button>
                  <ButtonPrimary type="button" disabled={enterpriseSendBusy} onClick={() => void sendEnterprise()}>
                    {enterpriseSendBusy ? 'Sending…' : 'Send a summary now'}
                  </ButtonPrimary>
                </div>
              ) : null}
            </div>

            {enterpriseEnabled ? (
              <div className="border-t border-slate-100 px-6 py-5 dark:border-slate-800">
                <div className="grid gap-4 lg:grid-cols-3">
                  <label className="block text-sm lg:col-span-2">
                    <span className="font-medium text-slate-900 dark:text-white">Recipients</span>
                    <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                      Comma-separated emails. Leave empty to default to the WordPress admin email.
                    </span>
                    <input
                      type="text"
                      value={enterpriseRecipients}
                      onChange={(e) => setEnterpriseRecipients(e.target.value)}
                      className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-950"
                      placeholder="ceo@school.edu, it@school.edu"
                    />
                  </label>

                  <label className="block text-sm">
                    <span className="font-medium text-slate-900 dark:text-white">Day</span>
                    <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">Weekday (site timezone).</span>
                    <select
                      value={enterpriseDow}
                      onChange={(e) => setEnterpriseDow(Number(e.target.value))}
                      className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-950"
                    >
                      <option value={0}>Sunday</option>
                      <option value={1}>Monday</option>
                      <option value={2}>Tuesday</option>
                      <option value={3}>Wednesday</option>
                      <option value={4}>Thursday</option>
                      <option value={5}>Friday</option>
                      <option value={6}>Saturday</option>
                    </select>
                  </label>

                  <label className="block text-sm">
                    <span className="font-medium text-slate-900 dark:text-white">Hour</span>
                    <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">24h time (site timezone).</span>
                    <select
                      value={enterpriseHour}
                      onChange={(e) => setEnterpriseHour(Number(e.target.value))}
                      className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-950"
                    >
                      {Array.from({ length: 24 }).map((_, i) => (
                        <option key={i} value={i}>
                          {String(i).padStart(2, '0')}:00
                        </option>
                      ))}
                    </select>
                  </label>

                  <div className="flex items-end">
                    <ButtonPrimary type="button" disabled={enterpriseSaveBusy} onClick={() => void saveEnterpriseSettings()}>
                      {enterpriseSaveBusy ? 'Saving…' : 'Save schedule'}
                    </ButtonPrimary>
                  </div>
                </div>
              </div>
            ) : null}
          </ListPanel>
        </GatedFeatureWorkspace>
      </div>
    </EmbeddableShell>
  );
}
