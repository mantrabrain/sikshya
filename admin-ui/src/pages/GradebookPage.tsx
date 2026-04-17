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
  course_id: number;
  quizzes_taken: number;
  avg_quiz_score: number;
  assignments_graded: number;
  avg_assignment_grade: number | null;
  overall_score: number | null;
};

type Resp = { ok?: boolean; rows?: Row[] };
type ExportResp = { ok?: boolean; csv?: string; filename?: string };

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
  const [exporting, setExporting] = useState(false);

  const exportCsv = async () => {
    if (!enabled) return;
    setExporting(true);
    try {
      const cid = parseInt(courseFilter, 10);
      const path = SIKSHYA_ENDPOINTS.pro.gradebookExport(Number.isFinite(cid) && cid > 0 ? cid : undefined);
      const r = await getSikshyaApi().get<ExportResp>(path);
      const csv = r.csv || '';
      const name = r.filename || 'sikshya-gradebook.csv';
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
      setExporting(false);
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
      subtitle="Quiz scores and attempts for reporting and learner outcomes."
      pageActions={
        enabled ? (
          <div className="flex gap-2">
            <ButtonPrimary type="button" disabled={loading || exporting} onClick={() => refetch()}>
              Refresh
            </ButtonPrimary>
            <ButtonPrimary type="button" disabled={loading || exporting} onClick={() => void exportCsv()}>
              {exporting ? 'Exporting…' : 'Export CSV'}
            </ButtonPrimary>
          </div>
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
                      <th className="px-5 py-3.5">Quizzes</th>
                      <th className="px-5 py-3.5">Avg quiz %</th>
                      <th className="px-5 py-3.5">Assignments</th>
                      <th className="px-5 py-3.5">Avg assignment</th>
                      <th className="px-5 py-3.5">Overall</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {rows.map((r, i) => (
                      <tr key={`${r.user_id}-${r.course_id}-${i}`} className="bg-white dark:bg-slate-900">
                        <td className="px-5 py-3.5">{r.user_id}</td>
                        <td className="px-5 py-3.5">{r.course_id}</td>
                        <td className="px-5 py-3.5 tabular-nums">{r.quizzes_taken}</td>
                        <td className="px-5 py-3.5 tabular-nums">{Number(r.avg_quiz_score).toFixed(2)}</td>
                        <td className="px-5 py-3.5 tabular-nums">{r.assignments_graded}</td>
                        <td className="px-5 py-3.5 tabular-nums">
                          {r.avg_assignment_grade === null ? '—' : Number(r.avg_assignment_grade).toFixed(2)}
                        </td>
                        <td className="px-5 py-3.5 tabular-nums font-semibold">
                          {r.overall_score === null ? '—' : Number(r.overall_score).toFixed(2)}
                        </td>
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
