import { useCallback, useMemo, useRef, useState, type ChangeEvent } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { useAddonEnabled } from '../hooks/useAddons';
import { useAsyncData } from '../hooks/useAsyncData';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { appViewHref } from '../lib/appUrl';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { DataTable, type Column } from '../components/shared/DataTable';
import { SkeletonCard } from '../components/shared/Skeleton';
import { ConfirmDialog } from '../components/shared/ConfirmDialog';
import { HorizontalEditorTabs } from '../components/shared/HorizontalEditorTabs';
import { AddonSettingsPage } from './AddonSettingsPage';
import type { SikshyaReactConfig } from '../types';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';

type ScormPackage = {
  id: number;
  uuid: string;
  title: string;
  description?: string | null;
  scorm_version?: string;
  launch_path?: string;
  manifest_identifier?: string;
  mastery_score?: number | null;
  file_size_bytes?: number;
  asset_count?: number;
  status?: string;
  uploaded_by?: number;
  created_at?: string;
  updated_at?: string;
  lesson_reference_count?: number;
  launch_url?: string;
};

type PackagesResp = {
  ok?: boolean;
  rows?: ScormPackage[];
  total?: number;
  page?: number;
  per_page?: number;
};

type UploadResp = {
  ok?: boolean;
  warnings?: string[];
  package?: ScormPackage;
};

type PanelTab = 'packages' | 'reports' | 'settings';

const TAB_LIST: { id: PanelTab; label: string; icon?: string }[] = [
  { id: 'packages', label: 'Package library', icon: 'layers' },
  { id: 'reports', label: 'Reports', icon: 'chart' },
  { id: 'settings', label: 'Add-on defaults', icon: 'settings' },
];

function formatBytes(n: number | undefined | null): string {
  if (!n || n <= 0) return '—';
  const units = ['B', 'KB', 'MB', 'GB'];
  let v = n;
  let i = 0;
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024;
    i++;
  }
  return `${v.toFixed(v >= 10 || i === 0 ? 0 : 1)} ${units[i]}`;
}

function formatDate(s: string | undefined | null): string {
  if (!s) return '—';
  const d = new Date(s.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return s;
  return d.toLocaleString();
}

export function ScormH5pWorkspacePage(props: {
  config: SikshyaReactConfig;
  title: string;
  embedded?: boolean;
}) {
  const { config, title, embedded } = props;
  const featureOk = isFeatureEnabled(config, 'scorm_h5p_pro');
  const addon = useAddonEnabled('scorm_h5p_pro');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [tab, setTab] = useState<PanelTab>('packages');

  const inner = (
    <GatedFeatureWorkspace
      mode={mode}
      featureId="scorm_h5p_pro"
      config={config}
      featureTitle="SCORM / H5P"
      featureDescription="Manage SCORM packages, run H5P interactives, capture attempts and scores, and ship reports — all native to Sikshya."
      previewVariant="cards"
      addonEnableTitle="SCORM / H5P is not enabled"
      addonEnableDescription="Enable the add-on to upload SCORM packages, run the runtime tracker, and unlock attempt reporting."
      canEnable={Boolean(addon.licenseOk)}
      enableBusy={addon.loading}
      onEnable={() => addon.enable()}
      addonError={addon.error}
    >
      {enabled ? (
        <div className="space-y-6">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <HorizontalEditorTabs
              ariaLabel="SCORM / H5P sections"
              tabs={TAB_LIST}
              value={tab}
              onChange={(id) => setTab(id as PanelTab)}
            />
          </div>
          {tab === 'packages' ? <PackageLibrary config={config} /> : null}
          {tab === 'reports' ? <ReportsPanel config={config} /> : null}
          {tab === 'settings' ? <SettingsPanel config={config} /> : null}
        </div>
      ) : null}
    </GatedFeatureWorkspace>
  );

  return (
    <EmbeddableShell embedded={embedded} config={config} title={title}>
      {inner}
    </EmbeddableShell>
  );
}

// ============================================================================
//  PACKAGE LIBRARY (upload, manage, attach to lessons)
// ============================================================================

function PackageLibrary({ config }: { config: SikshyaReactConfig }) {
  void config;
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [reloadTick, setReloadTick] = useState(0);
  const [uploading, setUploading] = useState(false);
  const [uploadErr, setUploadErr] = useState<unknown>(null);
  const [uploadWarnings, setUploadWarnings] = useState<string[]>([]);
  const [confirmDelete, setConfirmDelete] = useState<ScormPackage | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);
  const toast = useTopRightToast(2600);
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const loader = useCallback(async () => {
    return getSikshyaApi().get<PackagesResp>(
      SIKSHYA_ENDPOINTS.pro.scormPackages({ page, per_page: 20, search: search.trim() }),
    );
  }, [page, search, reloadTick]);
  const { loading, data, error, refetch } = useAsyncData(loader, [page, search, reloadTick]);

  const rows = data?.rows ?? [];
  const total = data?.total ?? 0;

  const onUpload = async (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    setUploading(true);
    setUploadErr(null);
    setUploadWarnings([]);
    try {
      const fd = new FormData();
      fd.append('file', file);
      const resp = await getSikshyaApi().request<UploadResp>(
        SIKSHYA_ENDPOINTS.pro.scormPackages(),
        { method: 'POST', body: fd },
      );
      setUploadWarnings(resp.warnings ?? []);
      toast.success('Uploaded', 'Package uploaded.');
      setReloadTick((n) => n + 1);
    } catch (err) {
      setUploadErr(err);
    } finally {
      setUploading(false);
    }
  };

  const onDelete = async (pkg: ScormPackage, force: boolean) => {
    setBusyId(pkg.id);
    try {
      await getSikshyaApi().delete(
        `${SIKSHYA_ENDPOINTS.pro.scormPackage(pkg.id)}${force ? '?force=1' : ''}`,
      );
      toast.success('Deleted', 'Package deleted.');
      setConfirmDelete(null);
      setReloadTick((n) => n + 1);
    } catch (err) {
      toast.error('Delete failed', err instanceof Error ? err.message : 'Delete failed');
    } finally {
      setBusyId(null);
    }
  };

  const columns: Column<ScormPackage>[] = useMemo(
    () => [
      {
        id: 'title',
        header: 'Package',
        render: (p) => (
          <div className="flex flex-col">
            <span className="font-medium text-slate-900 dark:text-white">{p.title || `Package #${p.id}`}</span>
            <span className="text-xs text-slate-500">
              SCORM {p.scorm_version || '1.2'} · {p.asset_count ?? 0} files · {formatBytes(p.file_size_bytes)}
            </span>
            {p.description ? (
              <span className="mt-1 text-xs text-slate-500 line-clamp-2">{p.description}</span>
            ) : null}
          </div>
        ),
      },
      {
        id: 'launch',
        header: 'Launch entry',
        render: (p) => <code className="text-xs text-slate-600 dark:text-slate-300">{p.launch_path || '—'}</code>,
      },
      {
        id: 'usage',
        header: 'Used by',
        headerClassName: 'text-right',
        cellClassName: 'text-right tabular-nums',
        render: (p) => `${p.lesson_reference_count ?? 0} lesson${(p.lesson_reference_count ?? 0) === 1 ? '' : 's'}`,
      },
      {
        id: 'created',
        header: 'Uploaded',
        cellClassName: 'text-xs text-slate-500',
        render: (p) => formatDate(p.created_at),
      },
      {
        id: 'actions',
        header: '',
        headerClassName: 'text-right',
        cellClassName: 'text-right',
        render: (p) => (
          <div className="flex justify-end gap-2">
            <ButtonSecondary
              type="button"
              onClick={() => {
                if (p.launch_url) window.open(p.launch_url, '_blank', 'noopener,noreferrer');
              }}
            >
              Preview
            </ButtonSecondary>
            <ButtonSecondary type="button" onClick={() => setConfirmDelete(p)} disabled={busyId === p.id}>
              {busyId === p.id ? 'Working…' : 'Delete'}
            </ButtonSecondary>
          </div>
        ),
      },
    ],
    [busyId],
  );

  return (
    <div className="space-y-4">
      {/* Upload card */}
      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-slate-900 dark:text-white">Upload a SCORM package</h2>
            <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
              Drop a SCORM 1.2 or 2004 zip. Sikshya validates the manifest, extracts files into a sandboxed folder, and
              keeps every launch behind an auth-gated proxy.
            </p>
          </div>
          <div className="flex gap-2">
            <input
              ref={fileInputRef}
              type="file"
              accept=".zip,application/zip"
              className="hidden"
              onChange={(e) => void onUpload(e)}
            />
            <ButtonPrimary type="button" onClick={() => fileInputRef.current?.click()} disabled={uploading}>
              {uploading ? 'Uploading…' : 'Upload .zip'}
            </ButtonPrimary>
          </div>
        </div>
        {uploadWarnings.length > 0 ? (
          <ul className="mt-3 list-disc space-y-1 pl-5 text-xs text-amber-700 dark:text-amber-300">
            {uploadWarnings.map((w, idx) => (
              <li key={idx}>{w}</li>
            ))}
          </ul>
        ) : null}
        {uploadErr ? (
          <div className="mt-3">
            <ApiErrorPanel error={uploadErr} title="Upload failed" />
          </div>
        ) : null}
      </section>

      {/* Search + table */}
      <section className="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
        <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 p-4 dark:border-slate-800">
          <input
            type="search"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder="Search packages…"
            className="w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
          />
          <span className="ml-auto text-xs text-slate-500">{total} package{total === 1 ? '' : 's'}</span>
        </div>
        <div className="p-3">
          {loading ? (
            <SkeletonCard rows={4} />
          ) : error ? (
            <ApiErrorPanel error={error} title="Could not load packages" onRetry={() => refetch()} />
          ) : rows.length === 0 ? (
            <div className="rounded-lg border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-slate-700">
              No packages yet. Upload a zip to get started.
            </div>
          ) : (
            <DataTable<ScormPackage> columns={columns} rows={rows} rowKey={(r) => r.id} wrapInCard={false} />
          )}
        </div>
      </section>

      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />

      <ConfirmDialog
        open={Boolean(confirmDelete)}
        title="Delete package?"
        confirmLabel={
          confirmDelete && (confirmDelete.lesson_reference_count ?? 0) > 0 ? 'Force delete' : 'Delete'
        }
        variant="danger"
        busy={busyId !== null}
        onClose={() => setConfirmDelete(null)}
        onConfirm={async () => {
          if (confirmDelete) {
            await onDelete(confirmDelete, (confirmDelete.lesson_reference_count ?? 0) > 0);
          }
        }}
      >
        {confirmDelete && (confirmDelete.lesson_reference_count ?? 0) > 0
          ? `This package is attached to ${confirmDelete.lesson_reference_count} lesson(s). Force delete will detach it from every lesson.`
          : 'This will permanently remove the package files and database row. This cannot be undone.'}
      </ConfirmDialog>
    </div>
  );
}

// ============================================================================
//  REPORTS PANEL (per-course rollup, lesson drilldown, CSV export)
// ============================================================================

type CourseSummary = {
  course_id: number;
  totals: { attempts: number; learners: number; completed: number; passed: number };
  lessons: Array<{
    lesson_id: number;
    lesson_title: string;
    package_id: number;
    package_title: string;
    attempts: number;
    learners: number;
    completed: number;
    passed: number;
    avg_score: number | null;
    avg_time_seconds: number;
  }>;
};

function ReportsPanel({ config }: { config: SikshyaReactConfig }) {
  const [courseId, setCourseId] = useState('');

  const courseIdNum = Number(courseId.trim()) || 0;

  const loader = useCallback(async () => {
    if (courseIdNum <= 0) return null;
    const resp = await getSikshyaApi().get<{ ok?: boolean; data?: CourseSummary }>(
      SIKSHYA_ENDPOINTS.pro.scormCourseSummary(courseIdNum),
    );
    return resp.data ?? null;
  }, [courseIdNum]);
  const { loading, data, error, refetch } = useAsyncData(loader, [courseIdNum]);

  const [exportBusy, setExportBusy] = useState(false);
  const handleExport = async () => {
    if (courseIdNum <= 0) return;
    setExportBusy(true);
    try {
      const url = `${config.restUrl.replace(/\/$/, '')}${SIKSHYA_ENDPOINTS.pro.scormCourseExport(courseIdNum)}`;
      const res = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': config.restNonce, Accept: 'text/csv' },
      });
      if (!res.ok) throw new Error(`Export failed (${res.status})`);
      const blob = await res.blob();
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `sikshya-scorm-course-${courseIdNum}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(link.href);
    } catch (e) {
      console.error('SCORM CSV export failed', e);
    } finally {
      setExportBusy(false);
    }
  };

  const courseLessonColumns: Column<CourseSummary['lessons'][number]>[] = useMemo(
    () => [
      {
        id: 'title',
        header: 'Lesson',
        render: (l) => (
          <div>
            <div className="font-medium text-slate-900 dark:text-white">{l.lesson_title || `Lesson #${l.lesson_id}`}</div>
            {l.package_title ? <div className="text-xs text-slate-500">{l.package_title}</div> : null}
          </div>
        ),
      },
      {
        id: 'attempts',
        header: 'Attempts',
        headerClassName: 'text-right',
        cellClassName: 'text-right tabular-nums',
        render: (l) => l.attempts.toString(),
      },
      {
        id: 'learners',
        header: 'Learners',
        headerClassName: 'text-right',
        cellClassName: 'text-right tabular-nums',
        render: (l) => l.learners.toString(),
      },
      {
        id: 'completed',
        header: 'Completed',
        headerClassName: 'text-right',
        cellClassName: 'text-right tabular-nums',
        render: (l) => l.completed.toString(),
      },
      {
        id: 'passed',
        header: 'Passed',
        headerClassName: 'text-right',
        cellClassName: 'text-right tabular-nums',
        render: (l) => l.passed.toString(),
      },
      {
        id: 'avg_score',
        header: 'Avg score',
        headerClassName: 'text-right',
        cellClassName: 'text-right tabular-nums',
        render: (l) => (l.avg_score === null ? '—' : `${l.avg_score.toFixed(1)}%`),
      },
      {
        id: 'avg_time',
        header: 'Avg time',
        headerClassName: 'text-right',
        cellClassName: 'text-right tabular-nums',
        render: (l) => (l.avg_time_seconds > 0 ? `${Math.round(l.avg_time_seconds / 60)}m` : '—'),
      },
    ],
    [],
  );

  return (
    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
      <h2 className="text-base font-semibold text-slate-900 dark:text-white">Course report</h2>
      <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
        Pull a SCORM/H5P attempt summary for a single course. Use the CSV export to share with stakeholders.
      </p>
      <div className="mt-4 flex flex-wrap items-end gap-2">
        <div>
          <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400" htmlFor="scorm-course-id">
            Course ID
          </label>
          <input
            id="scorm-course-id"
            className="w-40 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
            value={courseId}
            onChange={(e) => setCourseId(e.target.value.replace(/[^0-9]/g, ''))}
            placeholder="e.g. 142"
          />
        </div>
        <ButtonPrimary type="button" onClick={() => void refetch()} disabled={courseIdNum <= 0}>
          Load report
        </ButtonPrimary>
        <ButtonSecondary type="button" onClick={() => void handleExport()} disabled={courseIdNum <= 0 || exportBusy}>
          {exportBusy ? 'Preparing…' : 'Export CSV'}
        </ButtonSecondary>
      </div>

      <div className="mt-5">
        {courseIdNum <= 0 ? (
          <p className="text-sm text-slate-500">Enter a course ID above to see attempt rollups.</p>
        ) : loading ? (
          <SkeletonCard rows={4} />
        ) : error ? (
          <ApiErrorPanel error={error} title="Could not load report" onRetry={() => refetch()} />
        ) : !data || data.lessons.length === 0 ? (
          <div className="rounded-lg border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-slate-700">
            No interactive lessons (with managed packages) found for this course yet.
          </div>
        ) : (
          <>
            <ul className="mb-4 grid gap-3 text-sm sm:grid-cols-4">
              <SummaryStat label="Attempts" value={data.totals.attempts} />
              <SummaryStat label="Learners" value={data.totals.learners} />
              <SummaryStat label="Completed" value={data.totals.completed} />
              <SummaryStat label="Passed" value={data.totals.passed} />
            </ul>
            <DataTable
              columns={courseLessonColumns}
              rows={data.lessons}
              rowKey={(r) => r.lesson_id}
              wrapInCard={false}
            />
          </>
        )}
      </div>
    </section>
  );
}

function SummaryStat({ label, value }: { label: string; value: number }) {
  return (
    <li className="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/60">
      <div className="text-xs uppercase tracking-wide text-slate-500">{label}</div>
      <div className="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{value}</div>
    </li>
  );
}

// ============================================================================
//  SETTINGS PANEL — re-uses the schema-driven form for now
// ============================================================================

function SettingsPanel({ config }: { config: SikshyaReactConfig }) {
  return (
    <AddonSettingsPage
      embedded
      config={config}
      title="SCORM / H5P settings"
      addonId="scorm_h5p_pro"
      subtitle="Player presentation, attempts & completion, storage limits, reporting retention, and debug logging."
      featureTitle="SCORM / H5P defaults"
      featureDescription="These options apply site-wide for this add-on. Per-course overrides live in the course builder; per-lesson overrides live in the lesson editor."
      relatedCoreSettingsTab="lessons"
      relatedCoreSettingsLabel="Lessons"
      nextSteps={[
        {
          label: 'Author a SCORM or H5P lesson',
          href: appViewHref(config, 'content-library', { tab: 'lessons' }),
          description: 'Create or open a lesson, set type to SCORM or H5P, then attach a package or paste an embed.',
        },
        {
          label: 'Course builder',
          href: appViewHref(config, 'courses'),
          description: 'Disable embeds for a single course or override default attempts/completion rules.',
        },
      ]}
    />
  );
}
