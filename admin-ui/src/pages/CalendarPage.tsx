import { useCallback, useMemo } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { DataTable, type Column } from '../components/shared/DataTable';
import { DataTableSkeleton } from '../components/shared/Skeleton';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { appViewHref } from '../lib/appUrl';
import type { SikshyaReactConfig } from '../types';

type Event = {
  type: string;
  id?: number;
  title: string;
  subtitle?: string;
  date?: string;
  datetime?: string;
  unix?: number;
  course_id?: number;
};

type Resp = { ok?: boolean; scope?: string; description?: string; events?: Event[] };

export function CalendarPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const featureOk = isFeatureEnabled(config, 'calendar');
  const addon = useAddonEnabled('calendar');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const loader = useCallback(async () => {
    if (!enabled) return { ok: true, events: [] as Event[] };
    return getSikshyaApi().get<Resp>(SIKSHYA_ENDPOINTS.pro.calendarFeed);
  }, [enabled]);
  const { loading, data, error, refetch } = useAsyncData(loader, [enabled]);
  const rows = data?.events ?? [];
  const description = data?.description;

  const columns: Column<Event>[] = useMemo(
    () => [
      {
        id: 'date',
        header: 'Date',
        render: (r) => (
          <span className="whitespace-nowrap text-xs text-slate-600 dark:text-slate-400" title={r.datetime || ''}>
            {r.date || '—'}
          </span>
        ),
      },
      {
        id: 'type',
        header: 'Type',
        render: (r) => (
          <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            {r.type.replace(/_/g, ' ')}
          </span>
        ),
      },
      {
        id: 'title',
        header: 'Title',
        render: (r) => (
          <div>
            <div className="font-medium text-slate-900 dark:text-white">{r.title || '—'}</div>
            {r.subtitle ? <div className="text-xs text-slate-500 dark:text-slate-400">{r.subtitle}</div> : null}
          </div>
        ),
      },
      {
        id: 'course',
        header: 'Course',
        render: (r) => {
          const cid = r.course_id ?? r.id ?? 0;
          if (!cid) return <span className="text-slate-500">—</span>;
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
    ],
    [config]
  );

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Staff view lists recent published course dates. Learners see enrollments, drip unlocks, due dates, and live sessions on My account → Overview when the Calendar add-on is on."
      pageActions={
        enabled ? (
          <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
            {loading ? 'Refreshing…' : 'Refresh'}
          </ButtonPrimary>
        ) : null
      }
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="calendar"
        config={config}
        featureTitle="Calendar"
        featureDescription="Staff calendar shows published course dates. Learner schedules (enrollments, unlocks, assignments, live classes) appear on the account dashboard and via the learner calendar REST endpoint."
        previewVariant="table"
        addonEnableTitle="Calendar is not enabled"
        addonEnableDescription="Enable the Calendar add-on to compute dated events and expose feed routes for staff."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
        {error ? <ApiErrorPanel error={error} title="Could not load calendar" onRetry={() => refetch()} /> : null}

        {description ? (
          <p className="mb-4 text-xs text-slate-500 dark:text-slate-400">{description}</p>
        ) : null}

        <ListPanel>
          {loading ? (
            <DataTableSkeleton headers={['Date', 'Type', 'Title', 'Course']} rows={8} />
          ) : (
            <DataTable<Event>
              columns={columns}
              rows={rows}
              rowKey={(r) => `${r.type}-${r.id ?? r.course_id ?? ''}-${r.unix ?? r.date ?? r.title}`}
              emptyContent={
                rows.length === 0 ? (
                  <ListEmptyState
                    title="No published courses in this feed"
                    description="This table reflects course publish dates. Add or publish courses to see rows. For per-learner schedules (drip, due dates, live sessions), open My account on the frontend or use the learner calendar API."
                  />
                ) : undefined
              }
              wrapInCard={false}
            />
          )}
        </ListPanel>
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}
