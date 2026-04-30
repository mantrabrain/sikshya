import { useCallback, useEffect, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { RowActionsMenu, type RowActionItem } from '../components/shared/list/RowActionsMenu';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
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

type ReportsAdvancedExportResp = {
  ok?: boolean;
  csv?: string;
  filename?: string;
  truncated?: boolean;
  row_count?: number;
  notice?: string;
};

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

type EnterpriseScheduleRow = {
  id: number;
  status: 'active' | 'paused' | string;
  label?: string;
  report_type?: string;
  frequency?: 'daily' | 'weekly' | 'monthly' | string;
  day_of_week?: number;
  day_of_month?: number;
  hour?: number;
  recipients?: string;
  last_status?: string;
  last_run_at?: string | null;
};

type EnterpriseRunRow = {
  id: number;
  schedule_id?: number | null;
  trigger_source?: string;
  status?: string;
  report_type?: string;
  row_count?: number;
  truncated?: number;
  error_message?: string | null;
  created_at?: string;
  started_at?: string | null;
  finished_at?: string | null;
};

type EnterpriseDashboardV2Resp = {
  ok?: boolean;
  schedules?: EnterpriseScheduleRow[];
  runs?: EnterpriseRunRow[];
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
  const dialog = useSikshyaDialog();
  const reportsAdvFeature = isFeatureEnabled(config, 'reports_advanced');
  const reportsAdvAddon = useAddonEnabled('reports_advanced');
  const reportsAdvMode = resolveGatedWorkspaceMode(
    reportsAdvFeature,
    reportsAdvAddon.enabled,
    reportsAdvAddon.loading
  );
  const reportsExportEnabled = reportsAdvMode === 'full';
  const [exportBusy, setExportBusy] = useState(false);
  const [expType, setExpType] = useState<'summary' | 'enrollments' | 'quiz_attempts'>('summary');
  const [expCourseId, setExpCourseId] = useState('');
  const [expStatus, setExpStatus] = useState('');
  const [expSearch, setExpSearch] = useState('');
  const [expDateFrom, setExpDateFrom] = useState('');
  const [expDateTo, setExpDateTo] = useState('');
  const [expUserId, setExpUserId] = useState('');
  const [expQuizId, setExpQuizId] = useState('');
  const [exportError, setExportError] = useState<string | null>(null);
  const [exportHint, setExportHint] = useState<string | null>(null);

  const enterpriseFeature = isFeatureEnabled(config, 'enterprise_reports');
  const enterpriseAddon = useAddonEnabled('enterprise_reports');
  const enterpriseMode = resolveGatedWorkspaceMode(
    enterpriseFeature,
    enterpriseAddon.enabled,
    enterpriseAddon.loading
  );
  const enterpriseEnabled = enterpriseMode === 'full';
  const [enterpriseStatus, setEnterpriseStatus] = useState<EnterpriseStatusResp | null>(null);
  const [enterpriseDash, setEnterpriseDash] = useState<EnterpriseDashboardV2Resp | null>(null);
  const [enterpriseBusy, setEnterpriseBusy] = useState(false);
  const [enterpriseSendBusy, setEnterpriseSendBusy] = useState(false);
  const [enterpriseMsg, setEnterpriseMsg] = useState<string | null>(null);
  const [enterpriseRecipients, setEnterpriseRecipients] = useState('');
  const [enterpriseDow, setEnterpriseDow] = useState(1);
  const [enterpriseHour, setEnterpriseHour] = useState(9);
  const [enterpriseSaveBusy, setEnterpriseSaveBusy] = useState(false);
  const [scheduleCreateBusy, setScheduleCreateBusy] = useState(false);
  const [scheduleLabel, setScheduleLabel] = useState('Weekly executive summary');
  const [scheduleFrequency, setScheduleFrequency] = useState<'daily' | 'weekly' | 'monthly'>('weekly');
  const [scheduleReportType, setScheduleReportType] = useState<'executive_summary'>('executive_summary');

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

  const refreshEnterpriseDashboard = useCallback(async () => {
    if (!enterpriseEnabled) return;
    try {
      const r = await getSikshyaApi().get<EnterpriseDashboardV2Resp>(SIKSHYA_ENDPOINTS.pro.enterpriseReportsDashboardV2);
      setEnterpriseDash(r);
    } catch {
      /* ignore; keep legacy status UI usable */
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
      void refreshEnterpriseDashboard();
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

  useEffect(() => {
    if (enterpriseEnabled && !enterpriseDash) {
      void refreshEnterpriseDashboard();
    }
  }, [enterpriseEnabled, enterpriseDash, refreshEnterpriseDashboard]);

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
      void refreshEnterpriseDashboard();
    } catch (e) {
      setEnterpriseMsg(e instanceof Error ? e.message : 'Failed to save settings');
    } finally {
      setEnterpriseSaveBusy(false);
    }
  };

  const createEnterpriseSchedule = async () => {
    if (!enterpriseEnabled) return;
    setEnterpriseMsg(null);
    setScheduleCreateBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.enterpriseReportsSchedulesV2, {
        label: scheduleLabel,
        report_type: scheduleReportType,
        frequency: scheduleFrequency,
        day_of_week: enterpriseDow,
        hour: enterpriseHour,
        recipients: enterpriseRecipients,
        delivery: { email_html: true, email_csv: true, webhook: true },
        export: { format: 'csv' },
      });
      setEnterpriseMsg('Created schedule.');
      void refreshEnterpriseDashboard();
    } catch (e) {
      setEnterpriseMsg(e instanceof Error ? e.message : 'Failed to create schedule');
    } finally {
      setScheduleCreateBusy(false);
    }
  };

  const runScheduleNow = async (scheduleId: number) => {
    if (!enterpriseEnabled) return;
    setEnterpriseMsg(null);
    setEnterpriseSendBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.enterpriseReportsRunV2, { schedule_id: scheduleId });
      setEnterpriseMsg('Run queued. It may take a moment to generate and email.');
      void refreshEnterpriseDashboard();
    } catch (e) {
      setEnterpriseMsg(e instanceof Error ? e.message : 'Failed to queue run');
    } finally {
      setEnterpriseSendBusy(false);
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

  const resetAttemptTimer = useCallback(
    async (attemptId: number) => {
      if (!attemptId) return;
      const ok = await dialog.confirm({
        title: 'Reset attempt timer?',
        message:
          'This will restart the countdown and clear any in-progress answers for this attempt.',
        confirmLabel: 'Reset timer',
        variant: 'danger',
      });
      if (!ok) return;
      setAttemptsBusy(true);
      try {
        await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.quizAttemptResetTimer(attemptId), {});
        await refreshAttempts(attemptsPage);
      } catch {
        // keep current rows
      } finally {
        setAttemptsBusy(false);
      }
    },
    [attemptsPage, dialog, refreshAttempts]
  );

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
    setExportError(null);
    setExportHint(null);
    try {
      const courseId = parseInt(expCourseId.replace(/[^\d]/g, ''), 10) || 0;
      const userId = parseInt(expUserId.replace(/[^\d]/g, ''), 10) || 0;
      const quizId = parseInt(expQuizId.replace(/[^\d]/g, ''), 10) || 0;
      const url = SIKSHYA_ENDPOINTS.pro.reportsAdvancedExport({
        type: expType,
        course_id: courseId > 0 ? courseId : undefined,
        status: expStatus || undefined,
        search: expSearch.trim() || undefined,
        date_from: expDateFrom.trim() || undefined,
        date_to: expDateTo.trim() || undefined,
        user_id: userId > 0 ? userId : undefined,
        quiz_id: quizId > 0 ? quizId : undefined,
      });
      const r = await getSikshyaApi().get<ReportsAdvancedExportResp>(url);
      const csv = r.csv || '';
      const name = r.filename || 'sikshya-export.csv';
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
      const dl = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = dl;
      a.download = name;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(dl);
      const hints: string[] = [];
      if (r.notice) hints.push(r.notice);
      if (r.truncated) {
        hints.push(
          `Export stopped at the row cap (${typeof r.row_count === 'number' ? r.row_count : ''} rows). Narrow filters or raise the cap in Add-ons → Advanced analytics & exports → settings.`
        );
      } else if (typeof r.row_count === 'number' && expType !== 'summary') {
        hints.push(`${r.row_count} row(s) exported.`);
      }
      setExportHint(hints.length ? hints.join(' ') : null);
    } catch (e) {
      setExportError(e instanceof Error ? e.message : 'Export failed');
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
                  <th className="px-4 py-3 text-right">Actions</th>
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
                    <td className="px-4 py-3 text-right">
                      {(() => {
                        const items: RowActionItem[] = [
                          {
                            key: 'reset-timer',
                            label: 'Reset timer',
                            onClick: () => void resetAttemptTimer(Number(r.id) || 0),
                            disabled: attemptsBusy,
                          },
                        ];
                        return <RowActionsMenu items={items} ariaLabel={`Quiz attempt actions for #${r.id}`} />;
                      })()}
                    </td>
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
          featureTitle="Advanced analytics & exports"
          featureDescription="Download CSVs for finance, accreditation, or instructor reviews: site summary, row-level enrollments, or quiz attempts. Instructors only see courses they teach."
          previewVariant="generic"
          addonEnableTitle="Advanced export is not enabled"
          addonEnableDescription="Enable the Advanced analytics add-on to unlock CSV exports alongside your on-screen charts."
          canEnable={Boolean(reportsAdvAddon.licenseOk)}
          enableBusy={reportsAdvAddon.loading}
          onEnable={() => reportsAdvAddon.enable()}
          addonError={reportsAdvAddon.error}
        >
          <ListPanel>
            <div className="space-y-5 p-6">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 className="text-base font-semibold text-slate-900 dark:text-white">Spreadsheet exports</h2>
                  <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Pick a report type, optionally narrow by course or dates, then download. Privacy and row limits live under{' '}
                    <span className="font-medium text-slate-700 dark:text-slate-200">Add-ons → Advanced analytics & exports</span> settings.
                  </p>
                </div>
                {reportsExportEnabled ? (
                  <ButtonPrimary type="button" disabled={exportBusy} onClick={() => void exportAdvancedCsv()}>
                    {exportBusy ? 'Preparing…' : 'Download CSV'}
                  </ButtonPrimary>
                ) : null}
              </div>

              {reportsExportEnabled ? (
                <>
                  <div className="grid gap-4 lg:grid-cols-12">
                    <label className="block lg:col-span-4">
                      <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Report type
                      </span>
                      <select
                        title="Summary is one row of headline metrics. Enrollments and quiz attempts export individual rows (subject to your row cap)."
                        value={expType}
                        onChange={(e) => setExpType(e.target.value as typeof expType)}
                        className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                      >
                        <option value="summary">Site summary (headline metrics)</option>
                        <option value="enrollments">Enrollments (one row per seat)</option>
                        <option value="quiz_attempts">Quiz attempts (one row per attempt)</option>
                      </select>
                    </label>
                    <label className="block lg:col-span-2">
                      <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Course ID
                      </span>
                      <input
                        title="Optional. Limits exports to a single course you can manage."
                        value={expCourseId}
                        onChange={(e) => setExpCourseId(e.target.value.replace(/[^\d]/g, ''))}
                        placeholder="Any"
                        inputMode="numeric"
                        disabled={expType === 'summary'}
                        className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-brand-400 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                      />
                    </label>
                    <label className="block lg:col-span-2">
                      <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        From (date)
                      </span>
                      <input
                        type="date"
                        title="Filters by enrolled date for enrollments, or started date for quiz attempts (stored server time)."
                        value={expDateFrom}
                        onChange={(e) => setExpDateFrom(e.target.value)}
                        disabled={expType === 'summary'}
                        className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-400 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                      />
                    </label>
                    <label className="block lg:col-span-2">
                      <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        To (date)
                      </span>
                      <input
                        type="date"
                        title="Inclusive end of day for the stored datetime column (server time)."
                        value={expDateTo}
                        onChange={(e) => setExpDateTo(e.target.value)}
                        disabled={expType === 'summary'}
                        className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-400 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                      />
                    </label>
                    <label className="block lg:col-span-2">
                      <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Status
                      </span>
                      <select
                        value={expStatus}
                        onChange={(e) => setExpStatus(e.target.value)}
                        disabled={expType === 'summary'}
                        className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-400 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                      >
                        <option value="">All</option>
                        {expType === 'enrollments' ? (
                          <>
                            <option value="enrolled">Enrolled</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                          </>
                        ) : null}
                        {expType === 'quiz_attempts' ? (
                          <>
                            <option value="completed">Completed</option>
                            <option value="in_progress">In progress</option>
                            <option value="passed">Passed</option>
                            <option value="failed">Failed</option>
                          </>
                        ) : null}
                      </select>
                    </label>
                  </div>

                  {expType !== 'summary' ? (
                    <div className="grid gap-4 lg:grid-cols-12">
                      <label className="block lg:col-span-6">
                        <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                          Search
                        </span>
                        <input
                          value={expSearch}
                          onChange={(e) => setExpSearch(e.target.value)}
                          placeholder={
                            expType === 'enrollments'
                              ? 'Learner name, email, login, or course title…'
                              : 'Learner, quiz title, or course title…'
                          }
                          className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-brand-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                      </label>
                      {expType === 'quiz_attempts' ? (
                        <>
                          <label className="block lg:col-span-3">
                            <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                              User ID
                            </span>
                            <input
                              value={expUserId}
                              onChange={(e) => setExpUserId(e.target.value.replace(/[^\d]/g, ''))}
                              placeholder="Any"
                              inputMode="numeric"
                              className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-brand-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                          </label>
                          <label className="block lg:col-span-3">
                            <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                              Quiz ID
                            </span>
                            <input
                              value={expQuizId}
                              onChange={(e) => setExpQuizId(e.target.value.replace(/[^\d]/g, ''))}
                              placeholder="Any"
                              inputMode="numeric"
                              className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-brand-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                          </label>
                        </>
                      ) : null}
                    </div>
                  ) : null}

                  {exportError ? (
                    <p className="text-sm text-red-700 dark:text-red-300" role="alert">
                      {exportError}
                    </p>
                  ) : null}
                  {exportHint ? <p className="text-sm text-slate-600 dark:text-slate-300">{exportHint}</p> : null}
                </>
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
          onEnable={() => enterpriseAddon.enable()}
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
                  <button
                    type="button"
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    onClick={() => void refreshEnterpriseDashboard()}
                    disabled={enterpriseBusy}
                  >
                    Refresh schedules
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
                      placeholder="ceo@company.com, it@company.com"
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

                <div className="mt-6 rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                  <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Enterprise schedules (v2)</h3>
                  <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Create multiple schedules and queue runs without touching server cron settings. This is admin-only.
                  </p>

                  <div className="mt-4 grid gap-3 lg:grid-cols-12">
                    <label className="block text-sm lg:col-span-5">
                      <span className="font-medium text-slate-900 dark:text-white">Label</span>
                      <input
                        value={scheduleLabel}
                        onChange={(e) => setScheduleLabel(e.target.value)}
                        className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-950"
                        placeholder="Weekly executive summary"
                      />
                    </label>
                    <label className="block text-sm lg:col-span-3">
                      <span className="font-medium text-slate-900 dark:text-white">Frequency</span>
                      <select
                        value={scheduleFrequency}
                        onChange={(e) => setScheduleFrequency(e.target.value as typeof scheduleFrequency)}
                        className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-950"
                      >
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                      </select>
                    </label>
                    <label className="block text-sm lg:col-span-4">
                      <span className="font-medium text-slate-900 dark:text-white">Report type</span>
                      <select
                        value={scheduleReportType}
                        onChange={(e) => setScheduleReportType(e.target.value as typeof scheduleReportType)}
                        className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-950"
                      >
                        <option value="executive_summary">Executive summary</option>
                      </select>
                    </label>
                    <div className="flex items-end lg:col-span-12">
                      <ButtonPrimary type="button" disabled={scheduleCreateBusy} onClick={() => void createEnterpriseSchedule()}>
                        {scheduleCreateBusy ? 'Creating…' : 'Create schedule'}
                      </ButtonPrimary>
                    </div>
                  </div>

                  {enterpriseDash?.schedules?.length ? (
                    <div className="mt-4 overflow-auto rounded-xl border border-slate-100 bg-white dark:border-slate-800 dark:bg-slate-950">
                      <table className="min-w-[980px] w-full border-collapse text-sm">
                        <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                          <tr>
                            <th className="px-4 py-3">ID</th>
                            <th className="px-4 py-3">Label</th>
                            <th className="px-4 py-3">Type</th>
                            <th className="px-4 py-3">Cadence</th>
                            <th className="px-4 py-3">Recipients</th>
                            <th className="px-4 py-3">Last status</th>
                            <th className="px-4 py-3">Actions</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                          {enterpriseDash.schedules.map((s) => (
                            <tr key={s.id} className="text-slate-700 dark:text-slate-200">
                              <td className="px-4 py-3 tabular-nums">{s.id}</td>
                              <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">{s.label || '—'}</td>
                              <td className="px-4 py-3">{s.report_type || '—'}</td>
                              <td className="px-4 py-3">
                                {s.frequency || '—'} {typeof s.hour === 'number' ? `@ ${String(s.hour).padStart(2, '0')}:00` : ''}
                              </td>
                              <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                {s.recipients?.trim() ? s.recipients : 'Default admin email'}
                              </td>
                              <td className="px-4 py-3 text-xs">{s.last_status || '—'}</td>
                              <td className="px-4 py-3">
                                <button
                                  type="button"
                                  onClick={() => void runScheduleNow(s.id)}
                                  className="rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                                  disabled={enterpriseSendBusy}
                                >
                                  Run now
                                </button>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  ) : (
                    <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">No schedules yet. Create one above.</p>
                  )}

                  {enterpriseDash?.runs?.length ? (
                    <div className="mt-4">
                      <h4 className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Recent runs
                      </h4>
                      <div className="mt-2 overflow-auto rounded-xl border border-slate-100 bg-white dark:border-slate-800 dark:bg-slate-950">
                        <table className="min-w-[980px] w-full border-collapse text-sm">
                          <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                            <tr>
                              <th className="px-4 py-3">Run</th>
                              <th className="px-4 py-3">Schedule</th>
                              <th className="px-4 py-3">Status</th>
                              <th className="px-4 py-3">Type</th>
                              <th className="px-4 py-3">Created</th>
                              <th className="px-4 py-3">Error</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {enterpriseDash.runs.map((r) => (
                              <tr key={r.id} className="text-slate-700 dark:text-slate-200">
                                <td className="px-4 py-3 tabular-nums">{r.id}</td>
                                <td className="px-4 py-3 tabular-nums">{r.schedule_id || '—'}</td>
                                <td className="px-4 py-3">{r.status || '—'}</td>
                                <td className="px-4 py-3">{r.report_type || '—'}</td>
                                <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{r.created_at || '—'}</td>
                                <td className="px-4 py-3 text-xs text-rose-700 dark:text-rose-300">{r.error_message || ''}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  ) : null}
                </div>
              </div>
            ) : null}
          </ListPanel>
        </GatedFeatureWorkspace>
      </div>
    </EmbeddableShell>
  );
}
