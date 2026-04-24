import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { DataTable, type Column } from '../components/shared/DataTable';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPaginationBar, DEFAULT_LIST_PER_PAGE } from '../components/shared/list/ListPaginationBar';
import { DataTableSkeleton } from '../components/shared/Skeleton';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { appViewHref } from '../lib/appUrl';
import type { SikshyaReactConfig } from '../types';

type LogRow = {
  id?: number;
  user_id?: number;
  course_id?: number;
  action?: string;
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

export function ActivityLogPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const adminBase = config.adminUrl.replace(/\/?$/, '/');
  const featureOk = isFeatureEnabled(config, 'activity_log');
  const addon = useAddonEnabled('activity_log');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';
  const [page, setPage] = useState(1);
  const perPage = DEFAULT_LIST_PER_PAGE;

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
    const path = SIKSHYA_ENDPOINTS.pro.activityLog({ per_page: perPage, page });
    return getSikshyaApi().get<Resp>(path);
  }, [enabled, page, perPage]);

  const { loading, data, error, refetch } = useAsyncData(loader, [page, enabled, perPage]);
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
            return '—';
          }
          return (
            <a
              href={`${adminBase}user-edit.php?user_id=${uid}`}
              className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
            >
              User #{uid}
            </a>
          );
        },
      },
      {
        id: 'action',
        header: 'Action',
        render: (r) => <span className="font-medium text-slate-900 dark:text-white">{r.action || '—'}</span>,
      },
      {
        id: 'course',
        header: 'Course',
        render: (r) => {
          const cid = r.course_id ?? 0;
          if (cid <= 0) {
            return <span className="text-slate-500">—</span>;
          }
          return (
            <a
              href={appViewHref(config, 'add-course', { course_id: String(cid) })}
              className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
            >
              Course #{cid}
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
      title="No activity yet"
      description="When learners enroll, complete lessons, or submit work, matching events appear here after the add-on is enabled and migrations have created the log table."
    />
  );

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Enrollment, completion, and commerce events recorded for support and audits."
      pageActions={
        enabled ? (
          <ButtonPrimary type="button" disabled={loading} onClick={() => void refetch()}>
            {loading ? 'Refreshing…' : 'Refresh'}
          </ButtonPrimary>
        ) : null
      }
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="activity_log"
        config={config}
        featureTitle="Student activity log"
        featureDescription="A dated timeline of enrollments, lesson progress, quiz results, and orders so you can answer what happened for a learner."
        previewVariant="table"
        addonEnableTitle="Activity log is not enabled"
        addonEnableDescription="Enable the Student activity log add-on to record events and unlock this audit view."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
        {error ? (
          <ApiErrorPanel error={error} title="Could not load activity log" onRetry={() => void refetch()} />
        ) : null}

        {enabled && tableMissing ? (
          <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
            The activity log table is not installed yet. Activate Sikshya Pro or run database updates so Pro migrations can create it.
          </div>
        ) : null}

        {enabled && !error && !tableMissing ? (
          <ListPanel>
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
