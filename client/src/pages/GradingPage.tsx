import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { Modal } from '../components/shared/Modal';
import { SingleCoursePicker } from '../components/shared/SingleCoursePicker';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { appViewHref } from '../lib/appUrl';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import type { NavItem, SikshyaReactConfig } from '../types';

type GradeScale = {
  id: number;
  name: string;
  use_points: number;
  gpa_scale_limit: string | number;
  gpa_separator: string;
};

type GradeScaleRow = {
  label: string;
  points: number;
  min_percent: number;
  max_percent: number;
  color?: string;
  sort_order?: number;
};

type GradeScaleListResp = { ok?: boolean; scales?: GradeScale[] };
type GradeScaleGetResp = { ok?: boolean; scale?: GradeScale; rows?: GradeScaleRow[] };

export function GradingPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const dialog = useSikshyaDialog();
  const featureOk = isFeatureEnabled(config, 'gradebook');
  const addon = useAddonEnabled('gradebook');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const gradeScaleListLoader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, scales: [] as GradeScale[] };
    }
    return getSikshyaApi().get<GradeScaleListResp>(SIKSHYA_ENDPOINTS.pro.gradeScales);
  }, [enabled]);

  const { data: scaleListData, loading: scaleListLoading, error: scaleListError, refetch: refetchScales } = useAsyncData(
    gradeScaleListLoader,
    [enabled]
  );

  const scales = scaleListData?.scales ?? [];
  const [activeScaleId, setActiveScaleId] = useState<number>(0);

  const activeScaleIdResolved = useMemo(() => {
    if (activeScaleId > 0) return activeScaleId;
    return scales[0]?.id || 0;
  }, [activeScaleId, scales]);

  const gradeScaleLoader = useCallback(async () => {
    if (!enabled || activeScaleIdResolved <= 0) {
      return { ok: true, scale: undefined, rows: [] as GradeScaleRow[] };
    }
    return getSikshyaApi().get<GradeScaleGetResp>(SIKSHYA_ENDPOINTS.pro.gradeScale(activeScaleIdResolved));
  }, [activeScaleIdResolved, enabled]);

  const { data: scaleData, loading: scaleLoading, error: scaleError, refetch: refetchScale } = useAsyncData(
    gradeScaleLoader,
    [activeScaleIdResolved, enabled]
  );

  const [scaleModalOpen, setScaleModalOpen] = useState(false);
  const [scaleModalMode, setScaleModalMode] = useState<'create' | 'edit'>('create');
  const [scaleName, setScaleName] = useState('');
  const [scaleUsePoints, setScaleUsePoints] = useState(true);
  const [scaleGpaLimit, setScaleGpaLimit] = useState('4.0');
  const [scaleGpaSep, setScaleGpaSep] = useState('/');
  const [scaleRows, setScaleRows] = useState<GradeScaleRow[]>([
    { label: 'A', min_percent: 90, max_percent: 100, points: 4, color: '#22c55e', sort_order: 10 },
    { label: 'B', min_percent: 80, max_percent: 89.99, points: 3, color: '#84cc16', sort_order: 20 },
    { label: 'C', min_percent: 70, max_percent: 79.99, points: 2, color: '#f59e0b', sort_order: 30 },
    { label: 'D', min_percent: 60, max_percent: 69.99, points: 1, color: '#f97316', sort_order: 40 },
    { label: 'F', min_percent: 0, max_percent: 59.99, points: 0, color: '#ef4444', sort_order: 50 },
  ]);
  const [savingScale, setSavingScale] = useState(false);
  const [filterCourseId, setFilterCourseId] = useState(0);

  const openCreateScale = () => {
    setScaleModalMode('create');
    setScaleName('');
    setScaleUsePoints(true);
    setScaleGpaLimit('4.0');
    setScaleGpaSep('/');
    setScaleRows([
      { label: 'A', min_percent: 90, max_percent: 100, points: 4, color: '#22c55e', sort_order: 10 },
      { label: 'B', min_percent: 80, max_percent: 89.99, points: 3, color: '#84cc16', sort_order: 20 },
      { label: 'C', min_percent: 70, max_percent: 79.99, points: 2, color: '#f59e0b', sort_order: 30 },
      { label: 'D', min_percent: 60, max_percent: 69.99, points: 1, color: '#f97316', sort_order: 40 },
      { label: 'F', min_percent: 0, max_percent: 59.99, points: 0, color: '#ef4444', sort_order: 50 },
    ]);
    setScaleModalOpen(true);
  };

  const openEditScale = () => {
    const s = scaleData?.scale;
    if (!s) return;
    setScaleModalMode('edit');
    setScaleName(s.name || '');
    setScaleUsePoints(Boolean(Number(s.use_points || 0)));
    setScaleGpaLimit(String(s.gpa_scale_limit ?? '0'));
    setScaleGpaSep(String(s.gpa_separator ?? '/'));
    setScaleRows((scaleData?.rows ?? []).map((r, i) => ({ ...r, sort_order: r.sort_order ?? i * 10 })));
    setScaleModalOpen(true);
  };

  const saveScale = async () => {
    if (!enabled) return;
    setSavingScale(true);
    try {
      const payload = {
        name: scaleName.trim(),
        use_points: scaleUsePoints,
        gpa_scale_limit: Number(scaleGpaLimit || 0),
        gpa_separator: scaleGpaSep || '/',
        rows: scaleRows.map((r, i) => ({
          label: r.label,
          min_percent: Number(r.min_percent || 0),
          max_percent: Number(r.max_percent || 0),
          points: Number(r.points || 0),
          color: r.color || '',
          sort_order: r.sort_order ?? i * 10,
        })),
      };

      if (scaleModalMode === 'create') {
        await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.gradeScales, payload);
      } else {
        await getSikshyaApi().put(SIKSHYA_ENDPOINTS.pro.gradeScale(activeScaleIdResolved), payload);
      }

      setScaleModalOpen(false);
      await refetchScales();
      await refetchScale();
    } finally {
      setSavingScale(false);
    }
  };

  const deleteScale = async () => {
    if (!enabled || activeScaleIdResolved <= 0) return;
    const ok = await dialog.confirm({
      title: 'Delete this grade scale?',
      message: 'This cannot be undone.',
      confirmLabel: 'Delete',
      variant: 'danger',
    });
    if (!ok) return;
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.pro.gradeScale(activeScaleIdResolved));
      setActiveScaleId(0);
      await refetchScales();
      await refetchScale();
    } catch (e) {
      void dialog.alert({
        title: 'Could not delete',
        message: e instanceof Error ? e.message : 'Delete failed.',
      });
    }
  };

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle="Letter scales are site-wide. Optionally pick a course to open its builder tab for per-course weights, scale mapping, and visibility."
      pageActions={
        enabled ? (
          <div className="flex gap-2">
            <ButtonSecondary type="button" disabled={scaleListLoading} onClick={() => void refetchScales()}>
              Refresh
            </ButtonSecondary>
            <ButtonPrimary type="button" onClick={openCreateScale}>
              New scale
            </ButtonPrimary>
          </div>
        ) : null
      }
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="gradebook"
        config={config}
        featureTitle="Grading"
        featureDescription="Define grade scales and reuse them across courses. Use Reports → Gradebook for exports, grids, and final-grade overrides."
        previewVariant="table"
        addonEnableTitle="Gradebook is not enabled"
        addonEnableDescription="Enable the Gradebook add-on for scales, course weights in the builder, and the full admin gradebook."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => addon.enable()}
        addonError={addon.error}
      >
        {scaleListError ? (
          <ApiErrorPanel error={scaleListError} title="Could not load grade scales" onRetry={() => refetchScales()} />
        ) : (
          <ListPanel className="p-5">
            <div className="mb-5 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-900/35">
              <div className="min-w-[min(100%,280px)] max-w-md flex-1">
                <SingleCoursePicker
                  value={filterCourseId}
                  onChange={setFilterCourseId}
                  placeholder="All courses (optional filter)"
                  hint="Does not change the scales list—use it to jump to one course’s Grading settings in the builder."
                  className="w-full"
                />
              </div>
              <ButtonSecondary
                type="button"
                disabled={filterCourseId <= 0}
                onClick={() => {
                  if (filterCourseId > 0) {
                    window.location.href = appViewHref(config, 'add-course', {
                      course_id: String(filterCourseId),
                    });
                  }
                }}
              >
                Open course grading
              </ButtonSecondary>
            </div>
            <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
              <div className="lg:col-span-1">
                <div className="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                  {scaleListLoading ? (
                    <div className="p-3 text-sm text-slate-500">Loading…</div>
                  ) : scales.length === 0 ? (
                    <div className="p-3 text-sm text-slate-500">No grade scales yet.</div>
                  ) : (
                    <div className="space-y-1">
                      {scales.map((s) => {
                        const active = s.id === activeScaleIdResolved;
                        return (
                          <button
                            key={s.id}
                            type="button"
                            onClick={() => setActiveScaleId(s.id)}
                            className={`w-full rounded-lg px-3 py-2 text-left text-sm ${
                              active
                                ? 'bg-brand-50 text-brand-800 dark:bg-brand-950/40 dark:text-brand-200'
                                : 'hover:bg-slate-50 dark:hover:bg-slate-800'
                            }`}
                          >
                            <div className="font-medium">{s.name || `Scale #${s.id}`}</div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                              {Number(s.use_points) ? 'Points + GPA' : 'Letter only'}
                            </div>
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>
              </div>

              <div className="lg:col-span-2">
                {scaleError ? (
                  <ApiErrorPanel error={scaleError} title="Could not load scale" onRetry={() => refetchScale()} />
                ) : !activeScaleIdResolved ? (
                  <div className="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">
                    Create a grade scale to get started.
                  </div>
                ) : (
                  <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <div className="text-lg font-semibold text-slate-900 dark:text-white">
                          {scaleData?.scale?.name || `Scale #${activeScaleIdResolved}`}
                        </div>
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                          {Number(scaleData?.scale?.use_points || 0)
                            ? `GPA ${scaleData?.scale?.gpa_scale_limit || ''}${scaleData?.scale?.gpa_separator || '/'}4`
                            : 'Letter scale only'}
                        </div>
                      </div>
                      <div className="flex gap-2">
                        <ButtonSecondary type="button" disabled={scaleLoading} onClick={openEditScale}>
                          Edit
                        </ButtonSecondary>
                        <ButtonSecondary type="button" disabled={scaleLoading} onClick={() => void deleteScale()}>
                          Delete
                        </ButtonSecondary>
                      </div>
                    </div>

                    <div className="mt-4 overflow-x-auto">
                      <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                          <tr>
                            <th className="px-4 py-3">Grade</th>
                            <th className="px-4 py-3">Min %</th>
                            <th className="px-4 py-3">Max %</th>
                            <th className="px-4 py-3">Points</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                          {(scaleData?.rows ?? []).map((r, i) => (
                            <tr key={`${r.label}-${i}`}>
                              <td className="px-4 py-3 font-semibold text-slate-900 dark:text-white">{r.label}</td>
                              <td className="px-4 py-3 tabular-nums">{Number(r.min_percent).toFixed(2)}</td>
                              <td className="px-4 py-3 tabular-nums">{Number(r.max_percent).toFixed(2)}</td>
                              <td className="px-4 py-3 tabular-nums">{Number(r.points).toFixed(2)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </ListPanel>
        )}

        <Modal
          open={scaleModalOpen}
          title={scaleModalMode === 'create' ? 'Create grade scale' : 'Edit grade scale'}
          description="Define grade ranges by percent. Optionally attach points (GPA)."
          onClose={() => setScaleModalOpen(false)}
          size="xl"
          footer={
            <div className="flex w-full items-center justify-end gap-2">
              <ButtonSecondary type="button" onClick={() => setScaleModalOpen(false)} disabled={savingScale}>
                Cancel
              </ButtonSecondary>
              <ButtonPrimary type="button" onClick={() => void saveScale()} disabled={savingScale || !scaleName.trim()}>
                {savingScale ? 'Saving…' : 'Save scale'}
              </ButtonPrimary>
            </div>
          }
        >
          <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
            <label className="block text-sm text-slate-700 dark:text-slate-300">
              Name
              <input
                value={scaleName}
                onChange={(e) => setScaleName(e.target.value)}
                placeholder="e.g. Default A–F"
                className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
              />
            </label>

            <div className="space-y-2">
              <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input
                  type="checkbox"
                  checked={scaleUsePoints}
                  onChange={(e) => setScaleUsePoints(e.target.checked)}
                  className="h-4 w-4"
                />
                Include points (for GPA)
              </label>
              <div className="grid grid-cols-2 gap-2">
                <label className="block text-sm text-slate-700 dark:text-slate-300">
                  GPA limit
                  <input
                    value={scaleGpaLimit}
                    onChange={(e) => setScaleGpaLimit(e.target.value)}
                    disabled={!scaleUsePoints}
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900"
                  />
                </label>
                <label className="block text-sm text-slate-700 dark:text-slate-300">
                  Separator
                  <input
                    value={scaleGpaSep}
                    onChange={(e) => setScaleGpaSep(e.target.value)}
                    disabled={!scaleUsePoints}
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900"
                  />
                </label>
              </div>
            </div>
          </div>

          <div className="mt-5">
            <div className="mb-2 flex items-center justify-between">
              <div className="text-sm font-semibold text-slate-900 dark:text-white">Rows</div>
              <ButtonSecondary
                type="button"
                onClick={() =>
                  setScaleRows((prev) => [
                    ...prev,
                    { label: '', min_percent: 0, max_percent: 0, points: 0, color: '', sort_order: prev.length * 10 },
                  ])
                }
              >
                Add row
              </ButtonSecondary>
            </div>

            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                  <tr>
                    <th className="px-3 py-2">Label</th>
                    <th className="px-3 py-2">Min %</th>
                    <th className="px-3 py-2">Max %</th>
                    <th className="px-3 py-2">Points</th>
                    <th className="px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {scaleRows.map((r, idx) => (
                    <tr key={idx}>
                      <td className="px-3 py-2">
                        <input
                          value={r.label}
                          onChange={(e) =>
                            setScaleRows((prev) => prev.map((x, i) => (i === idx ? { ...x, label: e.target.value } : x)))
                          }
                          className="w-24 rounded-md border border-slate-200 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900"
                          placeholder="A+"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="number"
                          value={r.min_percent}
                          onChange={(e) =>
                            setScaleRows((prev) =>
                              prev.map((x, i) => (i === idx ? { ...x, min_percent: Number(e.target.value) } : x))
                            )
                          }
                          className="w-28 rounded-md border border-slate-200 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="number"
                          value={r.max_percent}
                          onChange={(e) =>
                            setScaleRows((prev) =>
                              prev.map((x, i) => (i === idx ? { ...x, max_percent: Number(e.target.value) } : x))
                            )
                          }
                          className="w-28 rounded-md border border-slate-200 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="number"
                          value={r.points}
                          onChange={(e) =>
                            setScaleRows((prev) =>
                              prev.map((x, i) => (i === idx ? { ...x, points: Number(e.target.value) } : x))
                            )
                          }
                          disabled={!scaleUsePoints}
                          className="w-28 rounded-md border border-slate-200 px-2 py-1 text-sm disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900"
                        />
                      </td>
                      <td className="px-3 py-2 text-right">
                        <button
                          type="button"
                          className="text-xs font-semibold text-rose-600 hover:text-rose-800 dark:text-rose-300"
                          onClick={() => setScaleRows((prev) => prev.filter((_, i) => i !== idx))}
                        >
                          Remove
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </Modal>
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}

