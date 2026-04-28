import { useCallback, useEffect, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AddonSettingsPage } from './AddonSettingsPage';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { SingleCoursePicker } from '../components/shared/SingleCoursePicker';
import { Modal } from '../components/shared/Modal';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { appViewHref } from '../lib/appUrl';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import type { NavItem, SikshyaReactConfig } from '../types';

type Row = {
  user_id: number;
  user_name?: string;
  user_email?: string;
  user_edit_url?: string;
  course_id: number;
  course_title?: string;
  course_edit_url?: string;
  quizzes_taken: number;
  avg_quiz_score: number;
  assignments_graded: number;
  avg_assignment_grade: number | null;
  overall_score: number | null;
  letter_grade?: string | null;
  gpa_display?: string | null;
  has_override?: boolean;
  override_percent?: number | null;
  override_note?: string | null;
};

type Resp = { ok?: boolean; rows?: Row[]; page?: number; per_page?: number; total?: number; total_pages?: number };
type ExportResp = { ok?: boolean; csv?: string; filename?: string };

type LearnerDetail = {
  ok?: boolean;
  user_id: number;
  course_id: number;
  course_weights?: { wq?: number; wa?: number };
  quizzes: Array<{ quiz_id: number; title: string; best_score: number; weight: number }>;
  assignments: Array<{ assignment_id: number; title: string; grade: number; weight: number }>;
  computed_percent: number | null;
  letter_grade?: string | null;
  gpa_display?: string | null;
  override?: { override_percent: unknown; note?: string | null } | null;
};

type GridItem = { type: 'quiz' | 'assignment'; id: number; title: string; weight: number };
type GridRow = {
  user: { id: number; name: string; email: string };
  overall_percent: number | null;
  letter_grade?: string | null;
  gpa_display?: string | null;
  has_override?: boolean;
  override_percent?: number | null;
  cells: Record<
    string,
    { value?: number | null; status?: string; submission_id?: number; submitted_at?: string; graded_at?: string }
  >;
};
type GridResp = {
  ok?: boolean;
  course_id: number;
  course_title?: string;
  course_weights?: { wq?: number; wa?: number };
  items: GridItem[];
  rows: GridRow[];
  page?: number;
  per_page?: number;
  total?: number;
  total_pages?: number;
};

type DrillQuiz = {
  ok?: boolean;
  course_id: number;
  user_id: number;
  item_type: 'quiz';
  item_id: number;
  item_title?: string;
  attempts: Array<{
    id: number;
    attempt_number: number;
    score: number;
    status: string;
    started_at: string;
    completed_at: string | null;
  }>;
};

type DrillAssignment = {
  ok?: boolean;
  course_id: number;
  user_id: number;
  item_type: 'assignment';
  item_id: number;
  item_title?: string;
  rubric_criteria?: Array<{ index: number; label: string; max_points: number }>;
  submission: null | {
    id: number;
    content: string | null;
    attachment_ids: string | null;
    status: string;
    grade: number | null;
    feedback: string | null;
    rubric_scores_json?: string | null;
    submitted_at: string;
    graded_at: string | null;
  };
};

function parseStoredRubricScores(
  raw: string | null | undefined,
  criteria?: Array<{ index: number }>,
): Record<number, string> {
  const out: Record<number, string> = {};
  if (!raw || !criteria?.length) return out;
  try {
    const v = JSON.parse(raw) as { scores?: Array<{ i?: number; e?: number }> };
    const scores = Array.isArray(v.scores) ? v.scores : [];
    for (const s of scores) {
      if (typeof s.i === 'number' && s.e !== undefined && s.e !== null) {
        out[s.i] = String(s.e);
      }
    }
  } catch {
    return out;
  }
  return out;
}

function formatPct(n: number | null | undefined): string {
  if (n === null || n === undefined || Number.isNaN(Number(n))) {
    return '—';
  }
  return Number(n).toFixed(2);
}

export function GradebookPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const featureOk = isFeatureEnabled(config, 'gradebook');
  const addon = useAddonEnabled('gradebook');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [courseId, setCourseId] = useState<number>(0);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [view, setView] = useState<'summary' | 'grid'>('summary');
  const [workspaceTab, setWorkspaceTab] = useState<'grades' | 'settings'>('grades');

  const loader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, rows: [] as Row[], page: 1, per_page: 50, total: 0, total_pages: 0 };
    }
    const q = new URLSearchParams();
    if (courseId > 0) q.set('course_id', String(courseId));
    if (search.trim()) q.set('search', search.trim());
    q.set('page', String(page));
    q.set('per_page', '50');
    const s = q.toString();
    const path = s ? `${SIKSHYA_ENDPOINTS.pro.gradebook()}?${s}` : SIKSHYA_ENDPOINTS.pro.gradebook();
    return getSikshyaApi().get<Resp>(path);
  }, [courseId, enabled, page, search]);

  const { loading, data, error, refetch } = useAsyncData(loader, [courseId, enabled, page, search]);
  const rows = data?.rows ?? [];
  const [exporting, setExporting] = useState(false);
  const totalPages = Math.max(1, Number(data?.total_pages) || 1);

  const gridLoader = useCallback(async () => {
    if (!enabled || courseId <= 0 || view !== 'grid') {
      return null;
    }
    const q = new URLSearchParams();
    if (search.trim()) q.set('search', search.trim());
    q.set('page', String(page));
    q.set('per_page', '50');
    const base = SIKSHYA_ENDPOINTS.pro.gradebookGrid(courseId);
    const s = q.toString();
    const path = s ? `${base}${base.includes('?') ? '&' : '?'}${s}` : base;
    return getSikshyaApi().get<GridResp>(path);
  }, [courseId, enabled, page, search, view]);

  const gridState = useAsyncData(gridLoader, [courseId, enabled, page, search, view]);
  const grid = gridState.data;

  const [drillOpen, setDrillOpen] = useState(false);
  const [drillBusy, setDrillBusy] = useState(false);
  const [drillErr, setDrillErr] = useState<unknown>(null);
  const [drillData, setDrillData] = useState<DrillQuiz | DrillAssignment | null>(null);
  const [drillCtx, setDrillCtx] = useState<{ user: GridRow['user']; item: GridItem } | null>(null);
  const [gradeInput, setGradeInput] = useState('');
  const [feedbackInput, setFeedbackInput] = useState('');
  const [rubricScoreInputs, setRubricScoreInputs] = useState<Record<number, string>>({});
  const [gradeInlineErr, setGradeInlineErr] = useState('');
  const [gradeSaving, setGradeSaving] = useState(false);

  const loadDrilldown = useCallback(
    async (u: GridRow['user'], item: GridItem) => {
      if (!enabled || courseId <= 0) {
        return;
      }
      setDrillBusy(true);
      setDrillErr(null);
      setDrillData(null);
      setGradeInput('');
      setFeedbackInput('');
      setRubricScoreInputs({});
      setGradeInlineErr('');
      try {
        const d = await getSikshyaApi().get<DrillQuiz | DrillAssignment>(
          SIKSHYA_ENDPOINTS.pro.gradebookDrilldown({
            course_id: courseId,
            user_id: u.id,
            item_type: item.type,
            item_id: item.id,
          })
        );
        setDrillData(d);
        if (d && typeof d === 'object' && 'item_type' in d && d.item_type === 'assignment') {
          const da = d as DrillAssignment;
          if (da.submission) {
            setGradeInput(da.submission.grade != null ? String(da.submission.grade) : '');
            setFeedbackInput(typeof da.submission.feedback === 'string' ? da.submission.feedback : '');
            const parsed = parseStoredRubricScores(da.submission.rubric_scores_json, da.rubric_criteria);
            const next: Record<number, string> = { ...parsed };
            (da.rubric_criteria || []).forEach((c) => {
              if (next[c.index] === undefined) next[c.index] = '';
            });
            setRubricScoreInputs(next);
          } else {
            const next: Record<number, string> = {};
            (da.rubric_criteria || []).forEach((c) => {
              next[c.index] = '';
            });
            setRubricScoreInputs(next);
          }
        }
      } catch (e) {
        setDrillErr(e);
      } finally {
        setDrillBusy(false);
      }
    },
    [courseId, enabled]
  );

  const openDrill = (u: GridRow['user'], item: GridItem) => {
    if (!enabled || courseId <= 0) {
      return;
    }
    setDrillCtx({ user: u, item });
    setDrillOpen(true);
    void loadDrilldown(u, item);
  };

  const closeDrill = useCallback(() => {
    setDrillOpen(false);
    setDrillCtx(null);
  }, []);

  const saveAssignmentGrade = async () => {
    if (!enabled || !drillData || drillData.item_type !== 'assignment' || !drillData.submission) return;
    const da = drillData as DrillAssignment;
    const submissionId = da.submission.id;
    const trimmed = gradeInput.trim();
    if (trimmed !== '' && !Number.isFinite(Number(trimmed))) return;
    const criteria = da.rubric_criteria ?? [];
    const body: Record<string, unknown> = {
      submission_id: submissionId,
      course_id: courseId,
      grade: trimmed === '' ? null : Number(trimmed),
      feedback: feedbackInput.trim(),
      status: 'graded',
    };
    if (criteria.length > 0) {
      const parts = criteria.map((c) => {
        const raw = (rubricScoreInputs[c.index] ?? '').trim();
        return { index: c.index, earned: raw };
      });
      const allFilled = parts.every((p) => p.earned !== '' && Number.isFinite(Number(p.earned)));
      const anyTouched = parts.some((p) => p.earned !== '');
      if (allFilled) {
        body.rubric_scores = parts.map((p) => ({ index: p.index, earned: Number(p.earned) }));
      } else if (trimmed === '' && anyTouched) {
        return;
      }
    }
    setGradeSaving(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.gradebookAssignmentGrade, body);
      closeDrill();
      await refetch();
      gridState.refetch();
    } finally {
      setGradeSaving(false);
    }
  };

  const [detailOpen, setDetailOpen] = useState(false);
  const [detailUserId, setDetailUserId] = useState(0);
  const [detailCourseId, setDetailCourseId] = useState(0);
  const [detailLabel, setDetailLabel] = useState('');
  const [detailFetchVersion, setDetailFetchVersion] = useState(0);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState<unknown>(null);
  const [detail, setDetail] = useState<LearnerDetail | null>(null);
  const [overridePctInput, setOverridePctInput] = useState('');
  const [overrideNoteInput, setOverrideNoteInput] = useState('');
  const [overrideSaving, setOverrideSaving] = useState(false);

  const openDetail = (r: Row) => {
    setDetailUserId(r.user_id);
    setDetailCourseId(r.course_id);
    setDetailLabel(`${r.user_name || `User #${r.user_id}`} · ${r.course_title || `Course #${r.course_id}`}`);
    setDetail(null);
    setDetailError(null);
    setOverridePctInput('');
    setOverrideNoteInput('');
    setDetailFetchVersion((v) => v + 1);
    setDetailOpen(true);
  };

  useEffect(() => {
    if (!detailOpen || detailUserId <= 0 || detailCourseId <= 0 || !enabled) {
      return;
    }
    let cancelled = false;
    setDetailLoading(true);
    setDetailError(null);
    void getSikshyaApi()
      .get<LearnerDetail>(
        SIKSHYA_ENDPOINTS.pro.gradebookLearner({ user_id: detailUserId, course_id: detailCourseId })
      )
      .then((d) => {
        if (cancelled) return;
        setDetail(d);
        const ov = d.override;
        const raw = ov && ov.override_percent !== null && ov.override_percent !== undefined ? ov.override_percent : '';
        const pct =
          raw === '' || raw === null
            ? ''
            : String(typeof raw === 'number' ? raw : Number(raw));
        setOverridePctInput(Number.isFinite(Number(pct)) ? String(Number(pct)) : '');
        setOverrideNoteInput(ov && typeof ov.note === 'string' ? ov.note : '');
      })
      .catch((e) => {
        if (!cancelled) {
          setDetailError(e);
          setDetail(null);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setDetailLoading(false);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [detailOpen, detailUserId, detailCourseId, enabled, detailFetchVersion]);

  const saveOverride = async () => {
    if (!enabled || detailUserId <= 0 || detailCourseId <= 0) return;
    const trimmed = overridePctInput.trim();
    if (trimmed !== '') {
      const n = Number(trimmed);
      if (!Number.isFinite(n)) {
        return;
      }
    }
    setOverrideSaving(true);
    try {
      if (trimmed === '') {
        await getSikshyaApi().post<{ ok?: boolean }>(SIKSHYA_ENDPOINTS.pro.gradebookOverride, {
          user_id: detailUserId,
          course_id: detailCourseId,
          override_percent: null,
        });
      } else {
        const n = Number(trimmed);
        await getSikshyaApi().post<{ ok?: boolean }>(SIKSHYA_ENDPOINTS.pro.gradebookOverride, {
          user_id: detailUserId,
          course_id: detailCourseId,
          override_percent: n,
          note: overrideNoteInput.trim(),
        });
      }
      setDetailOpen(false);
      await refetch();
    } finally {
      setOverrideSaving(false);
    }
  };

  const clearOverrideOnly = async () => {
    if (!enabled || detailUserId <= 0 || detailCourseId <= 0) return;
    setOverrideSaving(true);
    try {
      await getSikshyaApi().post<{ ok?: boolean }>(SIKSHYA_ENDPOINTS.pro.gradebookOverride, {
        user_id: detailUserId,
        course_id: detailCourseId,
        override_percent: null,
      });
      setDetailOpen(false);
      await refetch();
    } finally {
      setOverrideSaving(false);
    }
  };

  const exportCsv = async () => {
    if (!enabled) return;
    setExporting(true);
    try {
      const q = new URLSearchParams();
      if (courseId > 0) q.set('course_id', String(courseId));
      if (search.trim()) q.set('search', search.trim());
      const path =
        q.toString() !== ''
          ? `${SIKSHYA_ENDPOINTS.pro.gradebookExport()}?${q.toString()}`
          : SIKSHYA_ENDPOINTS.pro.gradebookExport();
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

  const wq = detail?.course_weights?.wq;
  const wa = detail?.course_weights?.wa;
  const courseWeightsLine = useMemo(() => {
    if (wq === undefined && wa === undefined) return null;
    const a = Number(wq);
    const b = Number(wa);
    if (!Number.isFinite(a) && !Number.isFinite(b)) return null;
    return `Course mix: quizzes ${Number.isFinite(a) ? a : '—'}% · assignments ${Number.isFinite(b) ? b : '—'}%`;
  }, [wq, wa]);

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle="Weighted quiz and assignment scores per learner and course—summary list, course grid, CSV export, and staff overrides."
      pageActions={
        enabled ? (
          <div className="flex flex-wrap gap-2">
            <ButtonSecondary type="button" onClick={() => setWorkspaceTab('settings')}>
              Gradebook settings
            </ButtonSecondary>
            <ButtonSecondary type="button" onClick={() => window.open(appViewHref(config, 'grading'), '_self')}>
              Grading &amp; scales
            </ButtonSecondary>
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
      <GatedFeatureWorkspace
        mode={mode}
        featureId="gradebook"
        config={config}
        featureTitle="Gradebook"
        featureDescription="See each learner’s combined quiz and assignment performance, open a per-course grid to drill into items, export CSVs for records, and set final-grade overrides when needed."
        previewVariant="table"
        addonEnableTitle="Gradebook is not enabled"
        addonEnableDescription="Enable the Gradebook add-on to load grade REST routes, the admin gradebook screen, and optional learner-facing grade snippets on My account."
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
                workspaceTab === 'grades'
                  ? 'bg-brand-600 text-white'
                  : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'
              }`}
              onClick={() => setWorkspaceTab('grades')}
            >
              Grades &amp; export
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
            title="Gradebook settings"
            addonId="gradebook"
            subtitle="CSV export defaults and the default page size for the course grid."
            featureTitle="Gradebook settings"
            featureDescription="These options apply site-wide. Instructors still manage weights and scales from each course (Grading tab) and from Grading in the sidebar."
            relatedCoreSettingsTab="progress"
            relatedCoreSettingsLabel="Progress"
            nextSteps={[
              {
                label: 'Open the gradebook',
                description: 'Review summaries, switch to course grid, and export when you need a spreadsheet.',
                href: appViewHref(config, 'gradebook'),
              },
              {
                label: 'Configure letter scales',
                description: 'Create or edit scales and map courses under Grading.',
                href: appViewHref(config, 'grading'),
              },
              {
                label: 'Hide a course from learners’ grade views',
                description: 'In Course builder → Grading, use “Hide this course from the learner gradebook” for pilots or private cohorts.',
                href: appViewHref(config, 'courses'),
              },
            ]}
          />
        ) : error ? (
          <ApiErrorPanel error={error} title="Could not load gradebook" onRetry={() => refetch()} />
        ) : (
          <>
            <ListPanel className="relative z-20 p-5" overflow="visible">
              <div className="flex flex-wrap items-end justify-between gap-4">
                <div className="w-full min-w-0 max-w-md shrink-0 sm:max-w-lg">
                  <SingleCoursePicker
                    value={courseId}
                    onChange={(id) => {
                      setCourseId(id);
                      setPage(1);
                      if (id <= 0) {
                        setView('summary');
                      }
                    }}
                    placeholder="All courses"
                    hint="Optional: filter the summary and CSV export. Choose a course to unlock the course grid (one column per quiz and assignment)."
                    className="w-full max-w-full"
                  />
                </div>
                <div className="w-full sm:w-[320px]">
                  <label className="block text-sm text-slate-600 dark:text-slate-400">
                    Search
                    <input
                      type="search"
                      value={search}
                      onChange={(e) => {
                        setSearch(e.target.value);
                        setPage(1);
                      }}
                      placeholder="Learner, email, course…"
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    />
                  </label>
                </div>
              </div>
              <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2 rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                  <button
                    type="button"
                    className={`rounded-lg px-3 py-1.5 text-sm font-semibold ${
                      view === 'summary'
                        ? 'bg-brand-600 text-white'
                        : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800'
                    }`}
                    onClick={() => setView('summary')}
                  >
                    Summary
                  </button>
                  <button
                    type="button"
                    className={`rounded-lg px-3 py-1.5 text-sm font-semibold ${
                      view === 'grid'
                        ? 'bg-brand-600 text-white'
                        : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800'
                    }`}
                    onClick={() => setView('grid')}
                    disabled={courseId <= 0}
                    title={courseId <= 0 ? 'Pick a course to open the grid.' : 'Course-centric grid'}
                  >
                    Course grid
                  </button>
                </div>
                {view === 'grid' && courseId > 0 ? (
                  <div className="text-xs text-slate-500 dark:text-slate-400">
                    Click a cell to view attempts / submissions and grade assignments.
                  </div>
                ) : null}
              </div>
            </ListPanel>
            {view === 'summary' ? (
              <ListPanel>
                {loading ? (
                  <div className="p-8 text-center text-sm text-slate-500">Loading…</div>
                ) : rows.length === 0 ? (
                  <ListEmptyState
                    title="No gradebook rows yet"
                    description="Rows appear after learners complete quizzes or receive assignment grades. Courses marked “hide from the learner gradebook” in Course builder → Grading are hidden from this list unless your role can bypass that setting."
                  />
                ) : (
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                      <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                        <tr>
                          <th className="px-5 py-3.5">Learner</th>
                          <th className="px-5 py-3.5">Course</th>
                          <th className="px-5 py-3.5">Quizzes</th>
                          <th className="px-5 py-3.5">Avg quiz %</th>
                          <th className="px-5 py-3.5">Assignments</th>
                          <th className="px-5 py-3.5">Avg assignment</th>
                          <th className="px-5 py-3.5">Overall %</th>
                          <th className="px-5 py-3.5">Letter</th>
                          <th className="px-5 py-3.5">GPA</th>
                          <th className="px-5 py-3.5"></th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {rows.map((r, i) => (
                          <tr key={`${r.user_id}-${r.course_id}-${i}`} className="bg-white dark:bg-slate-900">
                            <td className="px-5 py-3.5">
                              <div className="font-medium text-slate-900 dark:text-white">{r.user_name || `User #${r.user_id}`}</div>
                              {r.user_email ? <div className="text-xs text-slate-500 dark:text-slate-400">{r.user_email}</div> : null}
                            </td>
                            <td className="px-5 py-3.5">
                              <div className="font-medium text-slate-900 dark:text-white">{r.course_title || `Course #${r.course_id}`}</div>
                              <div className="text-xs text-slate-500 dark:text-slate-400">#{r.course_id}</div>
                            </td>
                            <td className="px-5 py-3.5 tabular-nums">{r.quizzes_taken}</td>
                            <td className="px-5 py-3.5 tabular-nums">{Number(r.avg_quiz_score).toFixed(2)}</td>
                            <td className="px-5 py-3.5 tabular-nums">{r.assignments_graded}</td>
                            <td className="px-5 py-3.5 tabular-nums">
                              {r.avg_assignment_grade === null ? '—' : Number(r.avg_assignment_grade).toFixed(2)}
                            </td>
                            <td className="px-5 py-3.5 tabular-nums">
                              <div className="flex flex-wrap items-center gap-2 font-semibold">
                                <span>{r.overall_score === null ? '—' : Number(r.overall_score).toFixed(2)}</span>
                                {r.has_override ? (
                                  <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                                    Override
                                  </span>
                                ) : null}
                              </div>
                            </td>
                            <td className="px-5 py-3.5 tabular-nums text-slate-800 dark:text-slate-100">
                              {r.letter_grade ? String(r.letter_grade) : '—'}
                            </td>
                            <td className="px-5 py-3.5 tabular-nums text-slate-600 dark:text-slate-300">
                              {r.gpa_display ? String(r.gpa_display) : '—'}
                            </td>
                            <td className="px-5 py-3.5 text-right text-xs">
                              <div className="flex flex-col items-end gap-2 sm:flex-row sm:justify-end">
                                <button
                                  type="button"
                                  className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-300"
                                  onClick={() => openDetail(r)}
                                >
                                  Details
                                </button>
                                <div className="flex flex-wrap justify-end gap-2">
                                  {r.course_edit_url ? (
                                    <a
                                      href={r.course_edit_url}
                                      target="_blank"
                                      rel="noopener noreferrer"
                                      className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-300"
                                    >
                                      Edit course
                                    </a>
                                  ) : null}
                                  {r.user_edit_url ? (
                                    <a
                                      href={r.user_edit_url}
                                      target="_blank"
                                      rel="noopener noreferrer"
                                      className="font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300"
                                    >
                                      User
                                    </a>
                                  ) : null}
                                </div>
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </ListPanel>
            ) : (
              <ListPanel>
                {courseId <= 0 ? (
                  <ListEmptyState title="Pick a course first" description="Course grid view is per-course so we can build columns for quizzes and assignments." />
                ) : gridState.loading ? (
                  <div className="p-8 text-center text-sm text-slate-500">Loading grid…</div>
                ) : gridState.error ? (
                  <div className="p-5">
                    <ApiErrorPanel error={gridState.error} title="Could not load grid" onRetry={() => gridState.refetch()} />
                  </div>
                ) : !grid || (grid.rows?.length ?? 0) === 0 ? (
                  <ListEmptyState title="No learners found" description="Enroll learners or clear your search filter." />
                ) : (
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                      <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                        <tr>
                          <th className="sticky left-0 z-10 bg-slate-50/80 px-5 py-3.5 dark:bg-slate-800">Learner</th>
                          <th className="px-5 py-3.5">Overall</th>
                          {(grid.items || []).map((it) => (
                            <th key={`${it.type}:${it.id}`} className="min-w-[14rem] px-5 py-3.5">
                              <div className="truncate">{it.title || `${it.type} #${it.id}`}</div>
                              <div className="mt-1 text-[10px] font-medium normal-case text-slate-400">
                                Weight {Number(it.weight || 1).toFixed(2)}
                              </div>
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {grid.rows.map((r) => (
                          <tr key={r.user.id} className="bg-white dark:bg-slate-900">
                            <td className="sticky left-0 z-10 bg-white px-5 py-3.5 dark:bg-slate-900">
                              <div className="font-medium text-slate-900 dark:text-white">{r.user.name || `User #${r.user.id}`}</div>
                              {r.user.email ? <div className="text-xs text-slate-500 dark:text-slate-400">{r.user.email}</div> : null}
                            </td>
                            <td className="px-5 py-3.5 tabular-nums">
                              <div className="flex flex-wrap items-center gap-2 font-semibold">
                                <span>{r.overall_percent === null ? '—' : Number(r.overall_percent).toFixed(2)}</span>
                                {r.has_override ? (
                                  <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                                    Override
                                  </span>
                                ) : null}
                              </div>
                              <div className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{r.letter_grade || '—'}</div>
                            </td>
                            {(grid.items || []).map((it) => {
                              const key = `${it.type}:${it.id}`;
                              const cell = r.cells?.[key] || {};
                              const v = cell.value;
                              const shown = v == null || Number.isNaN(Number(v)) ? '—' : `${Number(v).toFixed(2)}%`;
                              const subtitle = it.type === 'assignment' && cell.status ? String(cell.status) : '';
                              return (
                                <td key={key} className="px-5 py-3.5">
                                  <button
                                    type="button"
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-left text-sm font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800"
                                    onClick={() => openDrill(r.user, it)}
                                  >
                                    <div>{shown}</div>
                                    {subtitle ? <div className="mt-0.5 text-xs font-medium text-slate-500">{subtitle}</div> : null}
                                  </button>
                                </td>
                              );
                            })}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </ListPanel>
            )}

            {totalPages > 1 ? (
              <div className="mt-4 flex items-center justify-between text-sm text-slate-600 dark:text-slate-400">
                <div>
                  Page {page} of {totalPages}
                </div>
                <div className="flex gap-2">
                  <button
                    type="button"
                    disabled={page <= 1 || loading}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                    className="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40 dark:border-slate-700"
                  >
                    Previous
                  </button>
                  <button
                    type="button"
                    disabled={page >= totalPages || loading}
                    onClick={() => setPage((p) => p + 1)}
                    className="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40 dark:border-slate-700"
                  >
                    Next
                  </button>
                </div>
              </div>
            ) : null}

            <Modal
              open={detailOpen}
              title="Learner grade detail"
              description={detailLabel}
              onClose={() => setDetailOpen(false)}
              size="lg"
              footer={
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <ButtonSecondary type="button" disabled={overrideSaving} onClick={() => void clearOverrideOnly()}>
                    Clear override
                  </ButtonSecondary>
                  <div className="flex gap-2">
                    <ButtonSecondary type="button" disabled={overrideSaving} onClick={() => setDetailOpen(false)}>
                      Close
                    </ButtonSecondary>
                    <ButtonPrimary type="button" disabled={overrideSaving} onClick={() => void saveOverride()}>
                      {overrideSaving ? 'Saving…' : 'Save'}
                    </ButtonPrimary>
                  </div>
                </div>
              }
            >
              {detailLoading ? (
                <p className="text-sm text-slate-500">Loading detail…</p>
              ) : detailError ? (
                <ApiErrorPanel
                  error={detailError}
                  title="Could not load detail"
                  onRetry={() => {
                    setDetailFetchVersion((v) => v + 1);
                  }}
                />
              ) : detail ? (
                <div className="space-y-6">
                  {courseWeightsLine ? (
                    <p className="text-sm text-slate-600 dark:text-slate-400">{courseWeightsLine}</p>
                  ) : null}
                  <div className="grid gap-3 rounded-xl border border-slate-100 bg-slate-50/80 p-4 text-sm dark:border-slate-800 dark:bg-slate-800/40 sm:grid-cols-3">
                    <div>
                      <div className="text-xs font-semibold uppercase text-slate-500">Overall %</div>
                      <div className="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-white">
                        {formatPct(detail.computed_percent)}
                      </div>
                    </div>
                    <div>
                      <div className="text-xs font-semibold uppercase text-slate-500">Letter</div>
                      <div className="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                        {detail.letter_grade ? String(detail.letter_grade) : '—'}
                      </div>
                    </div>
                    <div>
                      <div className="text-xs font-semibold uppercase text-slate-500">GPA</div>
                      <div className="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                        {detail.gpa_display ? String(detail.gpa_display) : '—'}
                      </div>
                    </div>
                  </div>

                  <div className="grid gap-6 lg:grid-cols-2">
                    <div>
                      <h3 className="mb-2 text-sm font-semibold text-slate-900 dark:text-white">Quizzes</h3>
                      {detail.quizzes.length === 0 ? (
                        <p className="text-sm text-slate-500">No quiz attempts yet.</p>
                      ) : (
                        <div className="overflow-x-auto rounded-lg border border-slate-100 dark:border-slate-800">
                          <table className="min-w-full text-xs">
                            <thead className="bg-slate-50 text-left dark:bg-slate-800/80">
                              <tr>
                                <th className="px-3 py-2 font-semibold text-slate-600 dark:text-slate-400">Quiz</th>
                                <th className="px-3 py-2 font-semibold text-slate-600 dark:text-slate-400">Best %</th>
                                <th className="px-3 py-2 font-semibold text-slate-600 dark:text-slate-400">Weight</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                              {detail.quizzes.map((q) => (
                                <tr key={q.quiz_id}>
                                  <td className="px-3 py-2 text-slate-800 dark:text-slate-200">{q.title || `Quiz #${q.quiz_id}`}</td>
                                  <td className="px-3 py-2 tabular-nums">{q.best_score.toFixed(2)}</td>
                                  <td className="px-3 py-2 tabular-nums">{q.weight.toFixed(2)}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      )}
                    </div>
                    <div>
                      <h3 className="mb-2 text-sm font-semibold text-slate-900 dark:text-white">Assignments</h3>
                      {detail.assignments.length === 0 ? (
                        <p className="text-sm text-slate-500">No graded assignments yet.</p>
                      ) : (
                        <div className="overflow-x-auto rounded-lg border border-slate-100 dark:border-slate-800">
                          <table className="min-w-full text-xs">
                            <thead className="bg-slate-50 text-left dark:bg-slate-800/80">
                              <tr>
                                <th className="px-3 py-2 font-semibold text-slate-600 dark:text-slate-400">Assignment</th>
                                <th className="px-3 py-2 font-semibold text-slate-600 dark:text-slate-400">Grade %</th>
                                <th className="px-3 py-2 font-semibold text-slate-600 dark:text-slate-400">Weight</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                              {detail.assignments.map((a) => (
                                <tr key={a.assignment_id}>
                                  <td className="px-3 py-2 text-slate-800 dark:text-slate-200">{a.title || `Assignment #${a.assignment_id}`}</td>
                                  <td className="px-3 py-2 tabular-nums">{a.grade.toFixed(2)}</td>
                                  <td className="px-3 py-2 tabular-nums">{a.weight.toFixed(2)}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      )}
                    </div>
                  </div>

                  <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Manual override</h3>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Set a final course percent (0–100). Leave empty to use the computed value and clear any stored override.
                    </p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                      <div>
                        <label className="block text-xs font-medium text-slate-600 dark:text-slate-400" htmlFor="gb-override-pct">
                          Override %
                        </label>
                        <input
                          id="gb-override-pct"
                          type="text"
                          inputMode="decimal"
                          className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900"
                          value={overridePctInput}
                          onChange={(e) => setOverridePctInput(e.target.value)}
                          placeholder="e.g. 87.5"
                        />
                      </div>
                      <div className="sm:col-span-2">
                        <label className="block text-xs font-medium text-slate-600 dark:text-slate-400" htmlFor="gb-override-note">
                          Note (optional)
                        </label>
                        <textarea
                          id="gb-override-note"
                          rows={2}
                          className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900"
                          value={overrideNoteInput}
                          onChange={(e) => setOverrideNoteInput(e.target.value)}
                          placeholder="Visible to staff in records…"
                        />
                      </div>
                    </div>
                  </div>
                </div>
              ) : (
                <p className="text-sm text-slate-500">No data.</p>
              )}
            </Modal>

            <Modal
              open={drillOpen}
              title="Item detail"
              onClose={closeDrill}
              size="lg"
              footer={
                drillData && drillData.item_type === 'assignment' && drillData.submission ? (
                  <div className="flex flex-wrap items-center justify-end gap-2">
                    <ButtonSecondary type="button" disabled={gradeSaving} onClick={closeDrill}>
                      Close
                    </ButtonSecondary>
                    <ButtonPrimary type="button" disabled={gradeSaving} onClick={() => void saveAssignmentGrade()}>
                      {gradeSaving ? 'Saving…' : 'Save grade'}
                    </ButtonPrimary>
                  </div>
                ) : (
                  <div className="flex justify-end">
                    <ButtonSecondary type="button" onClick={closeDrill}>
                      Close
                    </ButtonSecondary>
                  </div>
                )
              }
            >
              {drillBusy ? (
                <p className="text-sm text-slate-500">Loading…</p>
              ) : drillErr ? (
                <ApiErrorPanel
                  error={drillErr}
                  title="Could not load details"
                  onRetry={drillCtx ? () => void loadDrilldown(drillCtx.user, drillCtx.item) : undefined}
                />
              ) : !drillData ? (
                <p className="text-sm text-slate-500">No data.</p>
              ) : drillData.item_type === 'quiz' ? (
                <div className="space-y-3">
                  <div>
                    <div className="text-sm font-semibold text-slate-900 dark:text-white">{drillData.item_title || 'Quiz'}</div>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Attempts (latest first). The grid uses the best score.
                    </p>
                  </div>
                  <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                    <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                      <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                        <tr>
                          <th className="px-4 py-2.5">Attempt</th>
                          <th className="px-4 py-2.5">Score</th>
                          <th className="px-4 py-2.5">Status</th>
                          <th className="px-4 py-2.5">Completed</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {(drillData.attempts || []).map((a) => (
                          <tr key={a.id}>
                            <td className="px-4 py-2.5 tabular-nums">#{a.attempt_number}</td>
                            <td className="px-4 py-2.5 tabular-nums font-semibold">{Number(a.score || 0).toFixed(2)}%</td>
                            <td className="px-4 py-2.5">{a.status}</td>
                            <td className="px-4 py-2.5 text-xs text-slate-500">{a.completed_at || '—'}</td>
                          </tr>
                        ))}
                        {(drillData.attempts || []).length === 0 ? (
                          <tr>
                            <td colSpan={4} className="px-4 py-6 text-center text-xs text-slate-500">
                              No attempts found.
                            </td>
                          </tr>
                        ) : null}
                      </tbody>
                    </table>
                  </div>
                </div>
              ) : (
                <div className="space-y-4">
                  <div>
                    <div className="text-sm font-semibold text-slate-900 dark:text-white">{drillData.item_title || 'Assignment'}</div>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Grade and feedback update the course grid immediately.
                    </p>
                  </div>
                  {!drillData.submission ? (
                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-950/30 dark:text-slate-300">
                      No submission found for this learner.
                    </div>
                  ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                      {gradeInlineErr ? (
                        <div className="sm:col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-100">
                          {gradeInlineErr}
                        </div>
                      ) : null}
                      <div>
                        <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">Grade (%)</label>
                        <input
                          className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                          value={gradeInput}
                          onChange={(e) => setGradeInput(e.target.value)}
                          placeholder="e.g. 92"
                        />
                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                          Leave blank to derive from rubric scores when every criterion has a value; otherwise clears the
                          grade.
                        </p>
                      </div>
                      {(drillData as DrillAssignment).rubric_criteria &&
                      (drillData as DrillAssignment).rubric_criteria!.length > 0 ? (
                        <div className="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/40">
                          <p className="text-sm font-medium text-slate-800 dark:text-slate-200">Rubric scores</p>
                          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Enter points per row (0–max). When all rows are filled and overall grade is empty, the total
                            percent is calculated from weights.
                          </p>
                          <ul className="mt-3 space-y-2">
                            {(drillData as DrillAssignment).rubric_criteria!.map((c) => (
                              <li key={c.index} className="flex flex-wrap items-center gap-2 text-sm">
                                <span className="min-w-0 flex-1 text-slate-800 dark:text-slate-100">{c.label}</span>
                                <span className="text-xs text-slate-500">max {c.max_points}</span>
                                <input
                                  type="text"
                                  inputMode="decimal"
                                  className="w-24 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                                  value={rubricScoreInputs[c.index] ?? ''}
                                  onChange={(e) =>
                                    setRubricScoreInputs((prev) => ({ ...prev, [c.index]: e.target.value }))
                                  }
                                  placeholder="0"
                                />
                              </li>
                            ))}
                          </ul>
                        </div>
                      ) : null}
                      <div className="sm:col-span-2">
                        <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">Feedback</label>
                        <textarea
                          className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                          rows={6}
                          value={feedbackInput}
                          onChange={(e) => setFeedbackInput(e.target.value)}
                          placeholder="Notes for the learner…"
                        />
                      </div>
                      <div className="sm:col-span-2 grid gap-1 text-xs text-slate-500 dark:text-slate-400">
                        <div>Submitted: {drillData.submission.submitted_at || '—'}</div>
                        <div>Graded: {drillData.submission.graded_at || '—'}</div>
                        <div>Status: {drillData.submission.status || '—'}</div>
                      </div>
                    </div>
                  )}
                </div>
              )}
            </Modal>
          </>
        )}
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}
