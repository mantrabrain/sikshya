import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AddonSettingsPage } from './AddonSettingsPage';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { DataTable, type Column } from '../components/shared/DataTable';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPaginationBar, DEFAULT_LIST_PER_PAGE } from '../components/shared/list/ListPaginationBar';
import { DataTableSkeleton } from '../components/shared/Skeleton';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { SingleCoursePicker } from '../components/shared/SingleCoursePicker';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { appViewHref } from '../lib/appUrl';
import type { SikshyaReactConfig } from '../types';

type LogRow = {
  id?: number;
  user_id?: number;
  user_name?: string;
  user_email?: string;
  course_id?: number;
  course_title?: string;
  action?: string;
  action_label?: string;
  object_type?: string;
  object_id?: number;
  meta?: string | null;
  created_at?: string;
};

type Resp = {
  ok?: boolean;
  rows?: LogRow[];
  total?: number;
  page?: number;
  per_page?: number;
  pages?: number;
  table_missing?: boolean;
};

const ACTION_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'All actions' },
  { value: 'enrolled', label: 'Enrolled' },
  { value: 'unenrolled', label: 'Unenrolled' },
  { value: 'course_completed', label: 'Course completed' },
  { value: 'lesson_completed', label: 'Lesson completed' },
  { value: 'quiz_completed', label: 'Quiz completed' },
  { value: 'assignment_submitted', label: 'Assignment submitted' },
  { value: 'order_fulfilled', label: 'Order fulfilled' },
  { value: 'certificate_issued', label: 'Certificate issued' },
];

export function ActivityLogPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const adminBase = config.adminUrl.replace(/\/?$/, '/');
  const featureOk = isFeatureEnabled(config, 'activity_log');
  const addon = useAddonEnabled('activity_log');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';
  const [page, setPage] = useState(1);
  const perPage = DEFAULT_LIST_PER_PAGE;
  const [workspaceTab, setWorkspaceTab] = useState<'timeline' | 'settings'>('timeline');

  const [filterCourseId, setFilterCourseId] = useState(0);
  const [filterUserId, setFilterUserId] = useState('');
  const [filterAction, setFilterAction] = useState('');
  const [filterSearch, setFilterSearch] = useState('');
  const [filterDateFrom, setFilterDateFrom] = useState('');
  const [filterDateTo, setFilterDateTo] = useState('');

  const loader = useCallback(async () => {
    if (!enabled) {
      return {
        ok: true,
        rows: [] as LogRow[],
        total: 0,
        page: 1,
        per_page: perPage,
        pages: 0,
        table_missing: false,
      };
    }
    const uid = parseInt(String(filterUserId).trim(), 10);
    const path = SIKSHYA_ENDPOINTS.pro.activityLog({
      per_page: perPage,
      page,
      course_id: filterCourseId > 0 ? filterCourseId : undefined,
      user_id: Number.isFinite(uid) && uid > 0 ? uid : undefined,
      action: filterAction || undefined,
      search: filterSearch.trim() || undefined,
      date_from: filterDateFrom.trim() || undefined,
      date_to: filterDateTo.trim() || undefined,
    });
    return getSikshyaApi().get<Resp>(path);
  }, [
    enabled,
    page,
    perPage,
    filterCourseId,
    filterUserId,
    filterAction,
    filterSearch,
    filterDateFrom,
    filterDateTo,
  ]);

  const { loading, data, error, refetch } = useAsyncData(loader, [
    page,
    enabled,
    perPage,
    filterCourseId,
    filterUserId,
    filterAction,
    filterSearch,
    filterDateFrom,
    filterDateTo,
  ]);
  const rows = data?.rows ?? [];
  const total = data?.total ?? null;
  const totalPages = data?.pages ?? null;
  const tableMissing = Boolean(data?.table_missing);

  const columns: Column<LogRow>[] = useMemo(
    () => [
      {
        id: 'time',
        header: 'Time',
        render: (r) => (
          <span className="whitespace-nowrap text-xs text-slate-600 dark:text-slate-400" title={r.created_at || ''}>
            {r.created_at || '—'}
          </span>
        ),
      },
      {
        id: 'user',
        header: 'Learner',
        render: (r) => {
          const uid = r.user_id ?? 0;
          if (uid <= 0) {
            return <span className="text-slate-500">—</span>;
          }
          const label = (r.user_name || '').trim() || `User #${uid}`;
          return (
            <div>
              <a
                href={`${adminBase}user-edit.php?user_id=${uid}`}
                className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
              >
                {label}
              </a>
              {r.user_email ? <div className="text-xs text-slate-500 dark:text-slate-400">{r.user_email}</div> : null}
            </div>
          );
        },
      },
      {
        id: 'action',
        header: 'Action',
        render: (r) => (
          <span className="font-medium text-slate-900 dark:text-white">{r.action_label || r.action || '—'}</span>
        ),
      },
      {
        id: 'course',
        header: 'Course',
        render: (r) => {
          const cid = r.course_id ?? 0;
          if (cid <= 0) {
            return <span className="text-slate-500">Site-wide</span>;
          }
          const t = (r.course_title || '').trim() || `Course #${cid}`;
          return (
            <a
              href={appViewHref(config, 'add-course', { course_id: String(cid) })}
              className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
            >
              {t}
            </a>
          );
        },
      },
      {
        id: 'object',
        header: 'Object',
        render: (r) => (
          <span className="text-xs text-slate-700 dark:text-slate-300">
            {(r.object_type || '—') + (r.object_id != null ? ` #${r.object_id}` : '')}
          </span>
        ),
      },
      {
        id: 'meta',
        header: 'Meta',
        cellClassName: 'max-w-[min(20rem,40vw)]',
        render: (r) => (
          <span className="truncate text-xs text-slate-500 dark:text-slate-400" title={r.meta || ''}>
            {r.meta || '—'}
          </span>
        ),
      },
    ],
    [adminBase, config]
  );

  const emptyContent = (
    <ListEmptyState
      title="No activity matches"
      description="Try widening the date range or clearing filters. New events appear as learners enroll, progress, and complete purchases."
    />
  );

  const resetFilters = () => {
    setFilterCourseId(0);
    setFilterUserId('');
    setFilterAction('');
    setFilterSearch('');
    setFilterDateFrom('');
    setFilterDateTo('');
    setPage(1);
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Filterable timeline of enrollments, progress, submissions, certificates, and fulfilled orders."
      pageActions={
        enabled ? (
          <div className="flex flex-wrap gap-2">
            <ButtonSecondary type="button" onClick={() => setWorkspaceTab('settings')}>
              Activity log settings
            </ButtonSecondary>
            <ButtonPrimary type="button" disabled={loading} onClick={() => void refetch()}>
              {loading ? 'Refreshing…' : 'Refresh'}
            </ButtonPrimary>
          </div>
        ) : null
      }
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="activity_log"
        config={config}
        featureTitle="Student activity log"
        featureDescription="Answer “what happened?” for a learner or course: filter by user, course, action, or free-text search. Site managers see commerce-wide rows; instructors only see their own courses."
        previewVariant="table"
        addonEnableTitle="Activity log is not enabled"
        addonEnableDescription="Enable the Student activity log add-on to record Sikshya events and unlock this audit view plus the My account strip."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => addon.enable()}
        addonError={addon.error}
      >
        {enabled ? (
          <div className="mb-4 flex flex-wrap gap-2 border-b border-slate-200 pb-3 dark:border-slate-700">
            <button
              type="button"
              className={`rounded-lg px-3 py-1.5 text-sm font-semibold ${
                workspaceTab === 'timeline'
                  ? 'bg-brand-600 text-white'
                  : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'
              }`}
              onClick={() => setWorkspaceTab('timeline')}
            >
              Timeline
            </button>
            <button
              type="button"
              className={`rounded-lg px-3 py-1.5 text-sm font-semibold ${
                workspaceTab === 'settings'
                  ? 'bg-brand-600 text-white'
                  : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'
              }`}
              onClick={() => setWorkspaceTab('settings')}
            >
              Add-on defaults
            </button>
          </div>
        ) : null}

        {enabled && workspaceTab === 'settings' ? (
          <AddonSettingsPage
            embedded
            config={config}
            title="Activity log settings"
            addonId="activity_log"
            subtitle="Retention, learner dashboard strip size, and whether to honor per-course logging opt-out."
            featureTitle="Activity log settings"
            featureDescription="Every control here changes stored data or what learners and instructors see."
            nextSteps={[
              {
                label: 'Open the timeline',
                href: appViewHref(config, 'activity-log'),
                description: 'Filter and review events across your catalog.',
              },
              {
                label: 'Turn off logging for a pilot course',
                href: appViewHref(config, 'courses'),
                description: 'Course builder → Course info → Activity log → disable logging for that course.',
              },
            ]}
          />
        ) : null}

        {enabled && workspaceTab === 'timeline' && error ? (
          <ApiErrorPanel error={error} title="Could not load activity log" onRetry={() => void refetch()} />
        ) : null}

        {enabled && workspaceTab === 'timeline' && tableMissing ? (
          <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
            The activity log table is not installed yet. Activate Sikshya Pro or run database updates so Pro migrations can create it.
          </div>
        ) : null}

        {enabled && workspaceTab === 'timeline' && !error && !tableMissing ? (
          <ListPanel>
            <div className="space-y-4 border-b border-slate-100 p-4 dark:border-slate-800">
              <p className="text-xs text-slate-500 dark:text-slate-400">
                Filters apply immediately. Use learner WordPress ID for the user field. Instructors only see courses they author (plus co-instructor courses when Multi-instructor is on).
              </p>
              <div className="grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
                <SingleCoursePicker
                  value={filterCourseId}
                  onChange={(id) => {
                    setFilterCourseId(id);
                    setPage(1);
                  }}
                  placeholder="All courses"
                  hint="Optional — limit events to one course."
                  className="w-full max-w-full"
                />
                <label className="block text-sm text-slate-600 dark:text-slate-400">
                  Learner user ID
                  <input
                    type="number"
                    min={0}
                    value={filterUserId}
                    onChange={(e) => {
                      setFilterUserId(e.target.value);
                      setPage(1);
                    }}
                    placeholder="e.g. 12"
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                  />
                </label>
                <label className="block text-sm text-slate-600 dark:text-slate-400">
                  Action
                  <select
                    value={filterAction}
                    onChange={(e) => {
                      setFilterAction(e.target.value);
                      setPage(1);
                    }}
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                  >
                    {ACTION_OPTIONS.map((o) => (
                      <option key={o.value || 'all'} value={o.value}>
                        {o.label}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block text-sm text-slate-600 dark:text-slate-400 lg:col-span-2">
                  Search (action, type, meta…)
                  <input
                    type="search"
                    value={filterSearch}
                    onChange={(e) => {
                      setFilterSearch(e.target.value);
                      setPage(1);
                    }}
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    placeholder="Text contained in the event"
                  />
                </label>
                <label className="block text-sm text-slate-600 dark:text-slate-400">
                  From date
                  <input
                    type="date"
                    value={filterDateFrom}
                    onChange={(e) => {
                      setFilterDateFrom(e.target.value);
                      setPage(1);
                    }}
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                  />
                </label>
                <label className="block text-sm text-slate-600 dark:text-slate-400">
                  To date
                  <input
                    type="date"
                    value={filterDateTo}
                    onChange={(e) => {
                      setFilterDateTo(e.target.value);
                      setPage(1);
                    }}
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                  />
                </label>
              </div>
              <div className="flex flex-wrap gap-2">
                <ButtonSecondary type="button" onClick={() => resetFilters()}>
                  Reset filters
                </ButtonSecondary>
              </div>
            </div>
            {loading ? (
              <DataTableSkeleton headers={['Time', 'Learner', 'Action', 'Course', 'Object', 'Meta']} rows={8} />
            ) : (
              <>
                <div className="border-b border-slate-100 px-4 py-2 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                  {total != null && total > 0 ? (
                    <span>
                      Showing {rows.length} row{rows.length === 1 ? '' : 's'} · {total} total events
                    </span>
                  ) : (
                    <span>Learner and course events captured by Sikshya Pro</span>
                  )}
                </div>
                <ListPaginationBar
                  placement="top"
                  page={page}
                  total={total}
                  totalPages={totalPages}
                  perPage={perPage}
                  onPageChange={(p) => setPage(p)}
                  disabled={loading}
                />
                <DataTable<LogRow>
                  columns={columns}
                  rows={rows}
                  rowKey={(r) => String(r.id ?? `${r.created_at}-${r.user_id}-${r.action}-${r.object_id}`)}
                  emptyContent={rows.length === 0 ? emptyContent : undefined}
                  emptyMessage="No rows to display."
                  wrapInCard={false}
                />
                <ListPaginationBar
                  placement="bottom"
                  page={page}
                  total={total}
                  totalPages={totalPages}
                  perPage={perPage}
                  onPageChange={(p) => setPage(p)}
                  disabled={loading}
                />
              </>
            )}
          </ListPanel>
        ) : null}
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}
