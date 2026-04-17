import { useCallback, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { AddonEnablePanel } from '../components/AddonEnablePanel';
import { FeatureUpsell } from '../components/FeatureUpsell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { getLicensing, isFeatureEnabled } from '../lib/licensing';
import type { NavItem, SikshyaReactConfig } from '../types';

type Row = {
  user_id: number;
  quiz_id: number;
  course_id: number;
  score: number;
  status: string;
  completed_at: string | null;
};

type Resp = { ok?: boolean; rows?: Row[] };

export function GradebookPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const lic = getLicensing(config);
  const featureOk = isFeatureEnabled(config, 'gradebook');
  const addon = useAddonEnabled('gradebook');
  const enabled = featureOk && Boolean(addon.enabled);
  const [courseFilter, setCourseFilter] = useState('');

  const loader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, rows: [] as Row[] };
    }
    const cid = parseInt(courseFilter, 10);
    const path = SIKSHYA_ENDPOINTS.pro.gradebook(Number.isFinite(cid) && cid > 0 ? cid : undefined);
    return getSikshyaApi().get<Resp>(path);
  }, [courseFilter, enabled]);

  const { loading, data, error, refetch } = useAsyncData(loader, [courseFilter, enabled]);
  const rows = data?.rows ?? [];

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle="Quiz scores and attempts for reporting and learner outcomes."
      pageActions={
        enabled ? (
          <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
            Refresh
          </ButtonPrimary>
        ) : null
      }
    >
      {!featureOk ? (
        <FeatureUpsell
          title="Gradebook"
          description="View learner quiz outcomes across courses, filter by course, and use the data in your reports."
          licensing={lic}
        />
      ) : !enabled ? (
        <AddonEnablePanel
          title="Gradebook is not enabled"
          description="Enable the Gradebook addon to register reporting routes and unlock learner outcome tables."
          canEnable={Boolean(addon.licenseOk)}
          enableBusy={addon.loading}
          onEnable={() => void addon.enable()}
          upgradeUrl={lic.upgradeUrl}
          error={addon.error}
        />
      ) : error ? (
        <ApiErrorPanel error={error} title="Could not load gradebook" onRetry={() => refetch()} />
      ) : (
        <>
          <div className="mb-4 flex flex-wrap items-end gap-3">
            <label className="text-sm text-slate-600 dark:text-slate-400">
              Filter by course ID
              <input
                type="number"
                min={0}
                value={courseFilter}
                onChange={(e) => setCourseFilter(e.target.value)}
                placeholder="All courses"
                className="ml-2 mt-1 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
              />
            </label>
          </div>
          <ListPanel>
            {loading ? (
              <div className="p-8 text-center text-sm text-slate-500">Loading…</div>
            ) : rows.length === 0 ? (
              <ListEmptyState title="No attempts yet" description="Completed quiz attempts will list here." />
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                  <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                    <tr>
                      <th className="px-5 py-3.5">User</th>
                      <th className="px-5 py-3.5">Course</th>
                      <th className="px-5 py-3.5">Quiz</th>
                      <th className="px-5 py-3.5">Score %</th>
                      <th className="px-5 py-3.5">Completed</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {rows.map((r, i) => (
                      <tr key={`${r.user_id}-${r.quiz_id}-${i}`} className="bg-white dark:bg-slate-900">
                        <td className="px-5 py-3.5">{r.user_id}</td>
                        <td className="px-5 py-3.5">{r.course_id}</td>
                        <td className="px-5 py-3.5">{r.quiz_id}</td>
                        <td className="px-5 py-3.5 tabular-nums">{Number(r.score).toFixed(1)}</td>
                        <td className="px-5 py-3.5 text-slate-600 dark:text-slate-400">{r.completed_at || '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </ListPanel>
        </>
      )}
    </AppShell>
  );
}
