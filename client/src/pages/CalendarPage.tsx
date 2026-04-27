import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { DataTable, type Column } from '../components/shared/DataTable';
import { DataTableSkeleton } from '../components/shared/Skeleton';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { FieldHint } from '../components/shared/FieldHint';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { appViewHref } from '../lib/appUrl';
import { AddonSettingsPage } from './AddonSettingsPage';
import type { SikshyaReactConfig } from '../types';

type TabId = 'staff' | 'settings';

type Event = {
  type: string;
  id?: number;
  title: string;
  subtitle?: string;
  date?: string;
  datetime?: string;
  unix?: number;
  course_id?: number;
  deep_link?: string;
};

type Resp = {
  ok?: boolean;
  scope?: string;
  description?: string;
  events?: Event[];
  meta?: { count?: number; from?: string | null; to?: string | null; limit?: number; generated_at?: string };
};

function typeLabel(type: string): string {
  switch (type) {
    case 'course_published':
      return 'Course published';
    case 'lesson_unlock':
      return 'Lesson opens';
    case 'quiz_unlock':
      return 'Quiz opens';
    case 'assignment_unlock':
      return 'Assignment opens';
    case 'assignment_due':
      return 'Assignment due';
    case 'live_class':
      return 'Live session';
    case 'enrollment':
      return 'Enrollment';
    default:
      return type.replace(/_/g, ' ');
  }
}

export function CalendarPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const featureOk = isFeatureEnabled(config, 'calendar');
  const addon = useAddonEnabled('calendar');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [tab, setTab] = useState<TabId>('staff');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  const staffUrl = useMemo(() => {
    const q = new URLSearchParams();
    const f = from.trim();
    const t = to.trim();
    if (f) q.set('from', f);
    if (t) q.set('to', t);
    const s = q.toString();
    return s ? `${SIKSHYA_ENDPOINTS.pro.calendarFeed}?${s}` : SIKSHYA_ENDPOINTS.pro.calendarFeed;
  }, [from, to]);

  const loader = useCallback(async () => {
    if (!enabled || tab !== 'staff') return { ok: true, events: [] as Event[] } as Resp;
    return getSikshyaApi().get<Resp>(staffUrl);
  }, [enabled, tab, staffUrl]);
  const { loading, data, error, refetch } = useAsyncData(loader, [enabled, tab, staffUrl]);
  const rows = data?.events ?? [];
  const description = data?.description;
  const meta = data?.meta;

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
            {typeLabel(r.type)}
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
        id: 'links',
        header: 'Open',
        render: (r) => {
          const cid = r.course_id ?? r.id ?? 0;
          const site = r.deep_link?.trim();
          return (
            <div className="flex flex-wrap gap-2">
              {site ? (
                <a
                  href={site}
                  target="_blank"
                  rel="noreferrer"
                  className="text-xs font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                >
                  View on site
                </a>
              ) : null}
              {cid ? (
                <a
                  href={appViewHref(config, 'add-course', { course_id: String(cid) })}
                  className="text-xs font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                >
                  In admin
                </a>
              ) : null}
            </div>
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
      subtitle="Staff catalog dates, learner schedules on My account, REST feeds, and optional teasers on course + learn pages."
      pageActions={
        enabled && tab === 'staff' ? (
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
        featureDescription="Learners see enrollments, drip unlocks, live sessions, and assignment due dates. Staff can browse published course dates, filter exports, and tune what appears in feeds."
        previewVariant="table"
        addonEnableTitle="Calendar is not enabled"
        addonEnableDescription="Enable the Calendar add-on to expose staff and learner schedule endpoints and dashboard blocks."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
        {enabled ? (
          <div className="mb-4 flex flex-wrap gap-2 border-b border-slate-200 pb-3 dark:border-slate-700">
            <ButtonSecondary type="button" className={tab === 'staff' ? 'ring-2 ring-brand-500/40' : ''} onClick={() => setTab('staff')}>
              Staff catalog
            </ButtonSecondary>
            <ButtonSecondary type="button" className={tab === 'settings' ? 'ring-2 ring-brand-500/40' : ''} onClick={() => setTab('settings')}>
              Add-on defaults
            </ButtonSecondary>
          </div>
        ) : null}

        {tab === 'settings' && enabled ? (
          <AddonSettingsPage
            embedded
            config={config}
            title="Calendar settings"
            addonId="calendar"
            subtitle="Control how many events load, what is included, and whether the My account strip is shown."
            featureTitle="Calendar settings"
            featureDescription="Every toggle and number here changes what learners see on the account dashboard and what the REST API returns."
            nextSteps={[
              {
                label: 'Wire drip rules',
                description: 'Unlock dates come from Content drip — configure rules per course under Learning rules.',
                href: appViewHref(config, 'content-drip'),
              },
              {
                label: 'Preview learner view',
                description: 'Open My account as a test student to confirm the schedule strip and links.',
              },
            ]}
          />
        ) : null}

        {tab === 'staff' ? (
          <>
            {enabled ? (
              <div className="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/40 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                  <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300" htmlFor="sik-cal-from">
                    Published from (optional)
                  </label>
                  <input
                    id="sik-cal-from"
                    type="text"
                    placeholder="YYYY-MM-DD"
                    value={from}
                    onChange={(e) => setFrom(e.target.value)}
                    className="w-full rounded-md border border-slate-200 bg-white px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-950"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300" htmlFor="sik-cal-to">
                    Published through (optional)
                  </label>
                  <input
                    id="sik-cal-to"
                    type="text"
                    placeholder="YYYY-MM-DD"
                    value={to}
                    onChange={(e) => setTo(e.target.value)}
                    className="w-full rounded-md border border-slate-200 bg-white px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-950"
                  />
                </div>
                <div className="flex items-end gap-2">
                  <ButtonSecondary type="button" onClick={() => void refetch()}>
                    Apply filters
                  </ButtonSecondary>
                  <ButtonSecondary
                    type="button"
                    onClick={() => {
                      setFrom('');
                      setTo('');
                    }}
                  >
                    Clear
                  </ButtonSecondary>
                </div>
                <div className="sm:col-span-2 lg:col-span-3">
                  <FieldHint>Use ISO dates (YYYY-MM-DD) in the site timezone context; invalid values are ignored server-side.</FieldHint>
                </div>
              </div>
            ) : null}

            {error ? <ApiErrorPanel error={error} title="Could not load calendar" onRetry={() => refetch()} /> : null}

            {description ? (
              <p className="mb-4 text-xs text-slate-500 dark:text-slate-400">{description}</p>
            ) : null}

            {meta?.count !== undefined ? (
              <p className="mb-2 text-xs text-slate-500 dark:text-slate-400">
                Showing {meta.count} row{meta.count === 1 ? '' : 's'}
                {meta.limit ? ` (limit ${meta.limit})` : ''}
                {meta.generated_at ? ` · generated ${meta.generated_at}` : ''}
              </p>
            ) : null}

            <ListPanel>
              {loading && tab === 'staff' ? (
                <DataTableSkeleton headers={['Date', 'Type', 'Title', 'Open']} rows={8} />
              ) : (
                <DataTable<Event>
                  columns={columns}
                  rows={rows}
                  rowKey={(r) => `${r.type}-${r.id ?? r.course_id ?? ''}-${r.unix ?? r.date ?? r.title}`}
                  emptyContent={
                    rows.length === 0 ? (
                      <ListEmptyState
                        title="No courses in this range"
                        description="Adjust the publish-date filters or publish a course. Learner-specific schedules (drip, due dates, live classes) still appear on My account and via the learner calendar API."
                      />
                    ) : undefined
                  }
                  wrapInCard={false}
                />
              )}
            </ListPanel>
          </>
        ) : null}
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}
