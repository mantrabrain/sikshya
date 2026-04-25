import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { BulkActionsBar } from '../components/shared/list/BulkActionsBar';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListSearchToolbar } from '../components/shared/list/ListSearchToolbar';
import { ButtonPrimary } from '../components/shared/buttons';
import { CourseFilterSelect } from '../components/shared/CourseFilterSelect';
import { FieldHint } from '../components/shared/FieldHint';
import { DataTable, type Column } from '../components/shared/DataTable';
import { DataTableSkeleton } from '../components/shared/Skeleton';
import { DateTimePickerField } from '../components/shared/DateTimePickerField';
import { Modal } from '../components/shared/Modal';
import { RowActionsMenu, type RowActionItem } from '../components/shared/list/RowActionsMenu';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPaginationBar } from '../components/shared/list/ListPaginationBar';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { useAsyncData } from '../hooks/useAsyncData';
import { useDebouncedValue } from '../hooks/useDebouncedValue';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { appViewHref } from '../lib/appUrl';
import type { SikshyaReactConfig } from '../types';

/** Mirrors `DripService::unlockAtForLesson` — keep the union in sync with the PHP side. */
type DripRuleType = 'delay_days' | 'date';

type Rule = {
  id?: number;
  course_id?: number;
  lesson_id?: number | null;
  rule_type?: string;
  rule_value?: string;
  created_at?: string;
  course_title?: string | null;
  lesson_title?: string | null;
};

type ListResp = { ok?: boolean; rules?: Rule[]; total?: number; page?: number; per_page?: number; total_pages?: number };

type LessonRow = { id: number; title: string; status: string };
type LessonsResp = { ok?: boolean; course_id?: number; lessons?: LessonRow[] };

type DripNotifStatus = {
  ok?: boolean;
  drip_addon_enabled?: boolean;
  drip_notifications_addon_enabled?: boolean;
  next_drip_run_unix?: number;
  next_drip_run_iso?: string;
  note?: string;
  template_lesson_unlock_enabled?: boolean;
  template_course_unlock_enabled?: boolean;
  lesson_unlock_email_active?: boolean;
  course_unlock_email_active?: boolean;
};

/**
 * Render a saved rule's `rule_value` in plain English so admins recognise their
 * own rule in the list without parsing raw values. Falls back to the raw value
 * if the type is unknown, so future rule kinds still surface something.
 */
function describeRuleValue(type: string, value: string): string {
  if (type === 'delay_days') {
    const days = parseInt(value, 10);
    if (!Number.isFinite(days) || days <= 0) {
      return 'Immediately on enrollment';
    }
    return `${days} day${days === 1 ? '' : 's'} after enrollment`;
  }
  if (type === 'date') {
    const ts = Date.parse(value);
    if (!Number.isFinite(ts)) {
      return value || '—';
    }
    try {
      return `On ${new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(ts))}`;
    } catch {
      return value || '—';
    }
  }
  return value || '—';
}

function ruleRowId(r: Rule): number {
  const n = Number(r.id);
  return Number.isFinite(n) && n > 0 ? n : 0;
}

const dripToolbarSelectClass =
  'rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200';

export function ContentDripPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const dialog = useSikshyaDialog();

  const featureOk = isFeatureEnabled(config, 'content_drip');
  const addon = useAddonEnabled('content_drip');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const notifyFeature = isFeatureEnabled(config, 'drip_notifications');
  const notifyAddon = useAddonEnabled('drip_notifications');
  const notifyMode = resolveGatedWorkspaceMode(notifyFeature, notifyAddon.enabled, notifyAddon.loading);
  const notifyEnabled = notifyMode === 'full';

  // Initial course id from the URL so deep links from the curriculum builder
  // (?view=learning-rules&tab=drip&course_id=NN) land directly on Step 2.
  const initialCourse = config.query?.course_id ? Number(config.query.course_id) : 0;
  const [courseId, setCourseId] = useState<number>(Number.isFinite(initialCourse) ? initialCourse : 0);
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 320);
  const [listRuleType, setListRuleType] = useState<DripRuleType | ''>('');
  const [page, setPage] = useState(1);
  const perPage = 20;
  const [listOrderBy, setListOrderBy] = useState<'created_at' | 'course' | 'id'>('created_at');
  const [listOrder, setListOrder] = useState<'asc' | 'desc'>('desc');
  const [selectedRuleIds, setSelectedRuleIds] = useState<Set<number>>(() => new Set());
  const [bulkActionValue, setBulkActionValue] = useState('');
  const [bulkBusy, setBulkBusy] = useState(false);
  const [bulkError, setBulkError] = useState<unknown>(null);
  const headerSelectRef = useRef<HTMLInputElement>(null);

  const [editorOpen, setEditorOpen] = useState(false);
  const [editing, setEditing] = useState<Rule | null>(null);

  const [toast, setToast] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  // Auto-dismiss success toasts so they don't pile up after several saves.
  useEffect(() => {
    if (!toast || toast.kind !== 'success') return;
    const t = window.setTimeout(() => setToast(null), 2400);
    return () => window.clearTimeout(t);
  }, [toast]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, courseId, listRuleType, listOrderBy, listOrder]);

  useEffect(() => {
    setSelectedRuleIds(new Set());
    setBulkActionValue('');
  }, [page, courseId, debouncedSearch, listRuleType, listOrderBy, listOrder]);

  const rulesLoader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, rules: [] as Rule[], total: 0, page: 1, per_page: perPage, total_pages: 0 } as ListResp;
    }
    const q = new URLSearchParams({
      page: String(page),
      per_page: String(perPage),
      orderby: listOrderBy,
      order: listOrder,
      ...(courseId > 0 ? { course_id: String(courseId) } : {}),
      ...(debouncedSearch.trim() ? { search: debouncedSearch.trim() } : {}),
      ...(listRuleType ? { rule_type: listRuleType } : {}),
    });
    return getSikshyaApi().get<ListResp>(`${SIKSHYA_ENDPOINTS.pro.dripRules}?${q.toString()}`);
  }, [enabled, courseId, debouncedSearch, listRuleType, page, perPage, listOrderBy, listOrder]);
  const rulesQ = useAsyncData(rulesLoader, [enabled, courseId, debouncedSearch, listRuleType, page, listOrderBy, listOrder, perPage]);
  const rules = rulesQ.data?.rules ?? [];

  const dripBulkOptions = useMemo(() => [{ value: 'delete_schedules', label: 'Delete schedules' }], []);

  const selectableIdsOnPage = useMemo(() => rules.map(ruleRowId).filter((id) => id > 0), [rules]);
  const checkedOnPage = useMemo(
    () => selectableIdsOnPage.filter((id) => selectedRuleIds.has(id)).length,
    [selectableIdsOnPage, selectedRuleIds]
  );
  const allVisibleSelected =
    selectableIdsOnPage.length > 0 && checkedOnPage === selectableIdsOnPage.length;

  useLayoutEffect(() => {
    const el = headerSelectRef.current;
    if (!el) return;
    el.indeterminate = checkedOnPage > 0 && checkedOnPage < selectableIdsOnPage.length;
  }, [checkedOnPage, selectableIdsOnPage.length]);

  const toggleSelectAllRules = useCallback(() => {
    setSelectedRuleIds((prev) => {
      const next = new Set(prev);
      const allSel =
        selectableIdsOnPage.length > 0 && selectableIdsOnPage.every((id) => next.has(id));
      if (allSel) {
        selectableIdsOnPage.forEach((id) => next.delete(id));
      } else {
        selectableIdsOnPage.forEach((id) => next.add(id));
      }
      return next;
    });
  }, [selectableIdsOnPage]);

  const toggleRuleSelected = useCallback((id: number) => {
    if (id <= 0) return;
    setSelectedRuleIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);
  const [editorCourseId, setEditorCourseId] = useState<number>(0);
  const [editorScope, setEditorScope] = useState<'course' | 'lesson'>('course');
  const [editorLessonId, setEditorLessonId] = useState<number>(0);
  const [editorRuleType, setEditorRuleType] = useState<DripRuleType>('delay_days');
  const [editorDelayDays, setEditorDelayDays] = useState<string>('7');
  const [editorUnlockDate, setEditorUnlockDate] = useState<string>('');
  const [editorSaving, setEditorSaving] = useState(false);

  useEffect(() => {
    if (!editorOpen) {
      return;
    }
    const cid = editing?.course_id ? Number(editing.course_id) : courseId > 0 ? courseId : 0;
    const lid = editing?.lesson_id != null && editing.lesson_id !== '' ? Number(editing.lesson_id) : 0;
    const scope = Number.isFinite(lid) && lid > 0 ? 'lesson' : 'course';
    const rt = (editing?.rule_type as DripRuleType | undefined) || 'delay_days';
    setEditorCourseId(Number.isFinite(cid) ? cid : 0);
    setEditorScope(scope);
    setEditorLessonId(Number.isFinite(lid) && lid > 0 ? lid : 0);
    setEditorRuleType(rt);
    setEditorDelayDays(rt === 'delay_days' ? String(editing?.rule_value ?? '7') : '7');
    setEditorUnlockDate(rt === 'date' ? String(editing?.rule_value ?? '') : '');
  }, [editorOpen, editing, courseId]);

  const editorLessonsLoader = useCallback(async () => {
    if (!enabled || editorCourseId <= 0) {
      return { ok: true, course_id: editorCourseId, lessons: [] } as LessonsResp;
    }
    return getSikshyaApi().get<LessonsResp>(SIKSHYA_ENDPOINTS.pro.courseLessons(editorCourseId));
  }, [enabled, editorCourseId]);
  const editorLessonsQ = useAsyncData(editorLessonsLoader, [enabled, editorCourseId]);
  const editorLessons = editorLessonsQ.data?.lessons ?? [];

  const notifyLoader = useCallback(async () => {
    if (!notifyEnabled) return { ok: true } as DripNotifStatus;
    return getSikshyaApi().get<DripNotifStatus>(SIKSHYA_ENDPOINTS.pro.dripNotificationsStatus);
  }, [notifyEnabled]);
  const notifyQ = useAsyncData(notifyLoader, [notifyEnabled]);

  const saveRuleFromModal = async () => {
    const cid = editorCourseId;
    if (cid <= 0) {
      setToast({ kind: 'error', text: 'Pick the course first.' });
      return;
    }
    if (editorScope === 'lesson' && editorLessonId <= 0) {
      setToast({ kind: 'error', text: 'Pick the lesson this rule applies to.' });
      return;
    }
    let value = '';
    if (editorRuleType === 'delay_days') {
      const days = parseInt(editorDelayDays, 10);
      if (!Number.isFinite(days) || days < 0) {
        setToast({ kind: 'error', text: 'Enter how many days after enrollment the lesson should unlock.' });
        return;
      }
      value = String(days);
    } else {
      if (!editorUnlockDate) {
        setToast({ kind: 'error', text: 'Pick the date when learners should get access.' });
        return;
      }
      value = editorUnlockDate;
    }

    setEditorSaving(true);
    setToast(null);
    const payload = {
      course_id: cid,
      lesson_id: editorScope === 'lesson' ? editorLessonId : 0,
      rule_type: editorRuleType,
      rule_value: value,
    };
    const existingId = editing?.id != null ? Number(editing.id) : NaN;
    const isEdit = Number.isFinite(existingId) && existingId > 0;
    try {
      if (isEdit) {
        await getSikshyaApi().put(SIKSHYA_ENDPOINTS.pro.dripRule(existingId), payload);
      } else {
        await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.dripRules, payload);
      }
      setToast({ kind: 'success', text: isEdit ? 'Schedule updated.' : 'Schedule created.' });
      setEditorOpen(false);
      setEditing(null);
      // List is filtered by toolbar course + rule type + search — align filters so the new row appears.
      setCourseId(cid);
      setPage(1);
      setSearch('');
      setListRuleType('');
      void rulesQ.refetch();
    } catch (err) {
      setToast({ kind: 'error', text: err instanceof Error ? err.message : 'Save failed' });
    } finally {
      setEditorSaving(false);
    }
  };

  const removeRule = useCallback(
    async (rule: Rule) => {
      if (!rule.id) return;
      const lid = rule.lesson_id ? Number(rule.lesson_id) : 0;
      const target =
        lid > 0
          ? rule.lesson_title && String(rule.lesson_title).trim()
            ? rule.lesson_title
            : `Lesson #${lid}`
          : 'the whole course';
      const ok = await dialog.confirm({
        title: 'Remove this schedule?',
        message: (
          <span>
            The unlock rule for <strong>{target}</strong> will be deleted. Learners who haven't unlocked it yet will get
            immediate access (unless another rule covers the same lesson).
          </span>
        ),
        confirmLabel: 'Delete',
        variant: 'danger',
      });
      if (!ok) return;

      setDeletingId(rule.id);
      setToast(null);
      try {
        await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.pro.dripRule(rule.id));
        setToast({ kind: 'success', text: 'Schedule removed.' });
        void rulesQ.refetch();
      } catch (err) {
        setToast({ kind: 'error', text: err instanceof Error ? err.message : 'Could not delete the rule' });
      } finally {
        setDeletingId(null);
      }
    },
    [dialog, rulesQ]
  );

  const onDripBulkApply = useCallback(async () => {
    if (!enabled || selectedRuleIds.size === 0 || bulkActionValue !== 'delete_schedules') {
      return;
    }
    const ids = [...selectedRuleIds];
    const n = ids.length;
    const ok = await dialog.confirm({
      title: `Delete ${n} schedule(s)?`,
      message:
        'Selected unlock rules will be removed. Learners who have not unlocked those lessons yet may get immediate access unless another rule applies.',
      confirmLabel: 'Delete',
      variant: 'danger',
    });
    if (!ok) return;

    setBulkBusy(true);
    setBulkError(null);
    setToast(null);
    try {
      await Promise.all(ids.map((id) => getSikshyaApi().delete(SIKSHYA_ENDPOINTS.pro.dripRule(id))));
      setToast({ kind: 'success', text: `Removed ${n} schedule(s).` });
      setSelectedRuleIds(new Set());
      setBulkActionValue('');
      void rulesQ.refetch();
    } catch (e) {
      setBulkError(e);
    } finally {
      setBulkBusy(false);
    }
  }, [bulkActionValue, dialog, enabled, rulesQ, selectedRuleIds]);

  const columns: Column<Rule>[] = useMemo(
    () => [
      {
        id: '_bulk_select',
        header: (
          <input
            ref={headerSelectRef}
            type="checkbox"
            className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-700"
            aria-label="Select all rules on this page"
            checked={allVisibleSelected}
            onChange={toggleSelectAllRules}
            disabled={selectableIdsOnPage.length === 0}
          />
        ),
        alwaysVisible: true,
        headerClassName: 'w-12',
        cellClassName: 'w-12',
        render: (r) => {
          const id = ruleRowId(r);
          if (id <= 0) {
            return <span className="text-slate-300 dark:text-slate-600" aria-hidden>—</span>;
          }
          return (
            <input
              type="checkbox"
              className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-700"
              aria-label="Select this schedule row"
              checked={selectedRuleIds.has(id)}
              onChange={() => toggleRuleSelected(id)}
            />
          );
        },
      },
      {
        id: 'course',
        header: 'Course',
        render: (r) => (
          <div className="max-w-[28rem]">
            <div className="truncate font-semibold text-slate-900 dark:text-white">
              {r.course_title || (r.course_id ? `Course #${r.course_id}` : '—')}
            </div>
            {r.course_id ? <div className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">#{r.course_id}</div> : null}
          </div>
        ),
      },
      {
        id: 'scope',
        header: 'Scope',
        cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-300',
        render: (r) => (r.lesson_id ? 'Lesson' : 'Course'),
      },
      {
        id: 'lesson',
        header: 'Lesson',
        render: (r) =>
          r.lesson_id ? (
            <div className="max-w-[22rem] truncate text-slate-700 dark:text-slate-200">
              {r.lesson_title || `Lesson #${r.lesson_id}`}
            </div>
          ) : (
            <span className="text-slate-400">—</span>
          ),
      },
      {
        id: 'unlock',
        header: 'Unlock',
        render: (r) => (
          <span className="text-slate-700 dark:text-slate-200">
            {describeRuleValue(String(r.rule_type || ''), String(r.rule_value || ''))}
          </span>
        ),
      },
      {
        id: 'actions',
        header: '',
        headerClassName: 'w-[1%]',
        cellClassName: 'text-right align-middle',
        render: (r) => {
          const rid = r.id ?? 0;
          const busy = deletingId === rid;
          const items: RowActionItem[] = [
            {
              key: 'edit',
              label: 'Edit schedule',
              onClick: () => {
                setEditing(r);
                setEditorOpen(true);
              },
            },
            {
              key: 'delete',
              label: busy ? 'Deleting…' : 'Delete schedule',
              danger: true,
              disabled: rid <= 0 || busy,
              onClick: () => {
                if (busy || rid <= 0) return;
                void removeRule(r);
              },
            },
          ];
          return (
            <div className="flex justify-end" onClick={(e) => e.stopPropagation()}>
              <RowActionsMenu
                items={items}
                ariaLabel={`Actions for ${r.course_title || `Course #${r.course_id}` || 'schedule'}`}
              />
            </div>
          );
        },
      },
    ],
    [
      deletingId,
      removeRule,
      allVisibleSelected,
      selectedRuleIds,
      toggleSelectAllRules,
      toggleRuleSelected,
      selectableIdsOnPage.length,
    ]
  );

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Drip lessons after enrollment, or open them on a fixed date — one course at a time."
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="content_drip"
        config={config}
        featureTitle="Scheduled access"
        featureDescription="Release lessons on a schedule, after enrollment, or on a fixed calendar date. Learner actions respect these rules whenever this addon is enabled."
        previewVariant="form"
        addonEnableTitle="Scheduled access is not enabled"
        addonEnableDescription="Enable the Content Drip addon to register its routes and start managing lesson unlock schedules."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
        <div className="space-y-6">
          <Modal
            open={editorOpen}
            title={editing ? 'Edit schedule rule' : 'Add schedule rule'}
            description="Choose a course, optionally a lesson, then set when it unlocks."
            onClose={() => (editorSaving ? null : (setEditorOpen(false), setEditing(null)))}
            size="lg"
            footer={
              <div className="flex items-center justify-end gap-2">
                <button
                  type="button"
                  className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                  onClick={() => {
                    setEditorOpen(false);
                    setEditing(null);
                  }}
                  disabled={editorSaving}
                >
                  Cancel
                </button>
                <ButtonPrimary type="button" onClick={() => void saveRuleFromModal()} disabled={editorSaving}>
                  {editorSaving ? 'Saving…' : 'Save rule'}
                </ButtonPrimary>
              </div>
            }
          >
            <div className="grid gap-4 sm:grid-cols-2 sm:items-stretch">
              <div className="flex min-h-0 flex-col sm:col-span-2">
                <CourseFilterSelect
                  enabled={enabled}
                  value={editorCourseId}
                  onChange={(id) => {
                    setEditorCourseId(id);
                    setEditorLessonId(0);
                  }}
                  allowClear={false}
                  fieldLayout="compact"
                  dropdownZIndex={11050}
                  allLabel="Search and select a course…"
                  label="Course"
                  hint="Use the search field in the dropdown to find a course (same as the list filter)."
                />
              </div>

              <div className="flex min-h-0 flex-col">
                <label className="block shrink-0 text-sm text-slate-700 dark:text-slate-300">
                  Scope
                  <select
                    value={editorScope}
                    onChange={(e) => {
                      const next = e.target.value as 'course' | 'lesson';
                      setEditorScope(next);
                      if (next === 'course') setEditorLessonId(0);
                    }}
                    className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                  >
                    <option value="course">Whole course</option>
                    <option value="lesson">Specific lesson</option>
                  </select>
                </label>
                <div className="min-h-0 flex-1" aria-hidden />
                <FieldHint />
              </div>

              <div className="flex min-h-0 flex-col">
                <label className="block shrink-0 text-sm text-slate-700 dark:text-slate-300">
                  Lesson
                  <select
                    value={editorLessonId}
                    onChange={(e) => setEditorLessonId(Number(e.target.value))}
                    disabled={editorScope !== 'lesson' || editorCourseId <= 0}
                    className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                  >
                    <option value={0}>
                      {editorCourseId <= 0 ? 'Pick a course first' : editorLessonsQ.loading ? 'Loading lessons…' : '— Select lesson —'}
                    </option>
                    {editorLessons.map((l) => (
                      <option key={l.id} value={l.id}>
                        {l.title}
                      </option>
                    ))}
                  </select>
                </label>
                <div className="min-h-0 flex-1" aria-hidden />
                <FieldHint />
              </div>

              <div className="flex min-h-0 flex-col">
                <label className="block shrink-0 text-sm text-slate-700 dark:text-slate-300">
                  Unlock rule
                  <select
                    value={editorRuleType}
                    onChange={(e) => setEditorRuleType(e.target.value as DripRuleType)}
                    className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                  >
                    <option value="delay_days">After enrollment (days)</option>
                    <option value="date">On a fixed date</option>
                  </select>
                </label>
                <div className="min-h-0 flex-1" aria-hidden />
                <FieldHint />
              </div>

              {editorRuleType === 'delay_days' ? (
                <div className="flex min-h-0 flex-col">
                  <label className="block shrink-0 text-sm text-slate-700 dark:text-slate-300">
                    Days after enrollment
                    <input
                      type="number"
                      min={0}
                      step={1}
                      value={editorDelayDays}
                      onChange={(e) => setEditorDelayDays(e.target.value)}
                      className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                  </label>
                  <div className="min-h-0 flex-1" aria-hidden />
                  <FieldHint />
                </div>
              ) : (
                <div className="flex min-h-0 flex-col sm:col-span-2">
                  <label className="block shrink-0 text-sm text-slate-700 dark:text-slate-300">Unlock date</label>
                  <div className="mt-1 shrink-0">
                    <DateTimePickerField value={editorUnlockDate} onChange={setEditorUnlockDate} />
                  </div>
                  <div className="min-h-0 flex-1" aria-hidden />
                  <FieldHint />
                </div>
              )}
            </div>
          </Modal>

          <ListPanel overflow="visible">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800">
              <div>
                <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Schedules</h2>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Same layout as Course listing: search, sort, bulk actions, then the table.
                </p>
              </div>
              <ButtonPrimary
                type="button"
                onClick={() => {
                  setEditing(null);
                  setEditorOpen(true);
                }}
              >
                + Add schedule rule
              </ButtonPrimary>
            </div>
            <ListSearchToolbar
              searchValue={search}
              onSearchChange={setSearch}
              searchPlaceholder="Search by course or lesson…"
              sortField={listOrderBy}
              sortFieldOptions={[
                { value: 'created_at', label: 'Created' },
                { value: 'course', label: 'Course' },
                { value: 'id', label: 'Rule ID' },
              ]}
              onSortFieldChange={(v) => setListOrderBy(v as 'created_at' | 'course' | 'id')}
              sortOrder={listOrder}
              onSortOrderToggle={() => setListOrder((o) => (o === 'asc' ? 'desc' : 'asc'))}
              trailing={
                <div className="flex flex-wrap items-center gap-2">
                  <label className="sr-only" htmlFor="sikshya-drip-rule-type-filter">
                    Rule type
                  </label>
                  <select
                    id="sikshya-drip-rule-type-filter"
                    value={listRuleType}
                    onChange={(e) => setListRuleType(e.target.value as DripRuleType | '')}
                    className={dripToolbarSelectClass}
                  >
                    <option value="">All rule types</option>
                    <option value="delay_days">After enrollment</option>
                    <option value="date">Fixed date</option>
                  </select>
                  <CourseFilterSelect
                    enabled={enabled}
                    value={courseId}
                    onChange={setCourseId}
                    allLabel="All courses"
                    fieldLayout="compact"
                    labelVisibility="sr-only"
                    dropdownZIndex={11050}
                    hint="Limit to one course or All courses."
                  />
                </div>
              }
            />
            <div className="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
              <BulkActionsBar
                disabled={!enabled}
                customOptions={dripBulkOptions}
                selectId="sikshya-drip-bulk"
                selectedCount={selectedRuleIds.size}
                value={bulkActionValue}
                onChange={setBulkActionValue}
                onApply={() => void onDripBulkApply()}
                applyBusy={bulkBusy}
                trashMode={false}
              />
              <div className="shrink-0 text-xs font-medium tabular-nums text-slate-500 dark:text-slate-400 sm:text-right">
                {rulesQ.data?.total != null
                  ? `${rulesQ.data.total} schedule${rulesQ.data.total === 1 ? '' : 's'}`
                  : '\u00a0'}
              </div>
            </div>
            {bulkError ? (
              <div className="border-b border-red-100 px-4 py-3 dark:border-red-900/40">
                <ApiErrorPanel error={bulkError} title="Bulk action failed" onRetry={() => setBulkError(null)} />
              </div>
            ) : null}

            {rulesQ.error ? (
              <div className="p-4">
                <ApiErrorPanel error={rulesQ.error} title="Could not load schedules" onRetry={() => rulesQ.refetch()} />
              </div>
            ) : rulesQ.loading ? (
              <DataTableSkeleton headers={['', 'Course', 'Scope', 'Lesson', 'Unlock', '']} rows={8} />
            ) : (
              <>
                <ListPaginationBar
                  placement="top"
                  page={rulesQ.data?.page ?? page}
                  total={rulesQ.data?.total ?? null}
                  totalPages={rulesQ.data?.total_pages ?? null}
                  perPage={perPage}
                  onPageChange={setPage}
                  disabled={rulesQ.loading}
                />
                <DataTable
                  columns={columns}
                  rows={rules}
                  rowKey={(r) => r.id || `${r.course_id}-${r.lesson_id || 0}-${r.rule_type || ''}`}
                  wrapInCard={false}
                  emptyContent={
                    <ListEmptyState
                      title="No schedule rules"
                      description="Create your first schedule rule to drip content to learners."
                      action={
                        <ButtonPrimary
                          type="button"
                          onClick={() => {
                            setEditing(null);
                            setEditorOpen(true);
                          }}
                        >
                          + Add schedule rule
                        </ButtonPrimary>
                      }
                    />
                  }
                />
                <ListPaginationBar
                  placement="bottom"
                  page={rulesQ.data?.page ?? page}
                  total={rulesQ.data?.total ?? null}
                  totalPages={rulesQ.data?.total_pages ?? null}
                  perPage={perPage}
                  onPageChange={setPage}
                  disabled={rulesQ.loading}
                />
              </>
            )}
          </ListPanel>

          {/* Disambiguation: this is the LMS-enforced schedule, not a content
              author's "release notes" field. Mirrors the equivalent block on
              PrerequisitesPage so the two screens read as a pair. */}
          <div className="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-xs text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-950/40 dark:text-indigo-200">
            <p className="font-semibold">What this page does</p>
            <p className="mt-1 leading-relaxed">
              These rules <strong>actually block access</strong> to a lesson until the unlock condition is met. A learner
              who tries to open a locked lesson will see "This lesson will unlock on …" instead of the content. Pair this
              with <strong>Prerequisites</strong> when you want learners to also finish prior lessons before unlocking.
            </p>
            <p className="mt-2 leading-relaxed">
              <strong>Quizzes and assignments</strong> use the same unlock time as the <strong>nearest previous lesson</strong>{' '}
              in your course outline (or the course-wide rule if nothing comes before them). Schedule per-lesson overrides to
              control when later quizzes open in sequence.
            </p>
          </div>

          {/* Toast */}
          {toast ? (
            <div className="fixed right-4 top-4 z-[9999] w-[min(28rem,calc(100vw-2rem))]">
              <div
                role="status"
                className={`flex items-start justify-between gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg ${
                  toast.kind === 'success'
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-200'
                    : 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-200'
                }`}
              >
                <span className="min-w-0 flex-1">{toast.text}</span>
                <button
                  type="button"
                  onClick={() => setToast(null)}
                  className="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold hover:bg-black/5 dark:hover:bg-white/10"
                  aria-label="Dismiss"
                >
                  Dismiss
                </button>
              </div>
            </div>
          ) : null}

          {/* Drip notifications addon — sub-section, gated separately so admins
              can toggle the email-on-unlock behaviour without touching the
              schedule rules above. */}
          <div className="pt-2">
            <GatedFeatureWorkspace
              mode={notifyMode}
              featureId="drip_notifications"
              config={config}
              featureTitle="Drip notifications"
              featureDescription="When a lesson unlocks for a learner, automatically email them so they come back and start it."
              previewVariant="form"
              addonEnableTitle="Drip notifications is not enabled"
              addonEnableDescription="Enable Drip notifications. When the drip cron unlocks content, learners receive your transactional templates (From/SMTP on the Email page)."
              canEnable={Boolean(notifyAddon.licenseOk)}
              enableBusy={notifyAddon.loading}
              onEnable={() => void notifyAddon.enable()}
              addonError={notifyAddon.error}
            >
              <ListPanel className="p-6">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h2 className="text-sm font-semibold text-slate-900 dark:text-white">
                      Notification cron
                    </h2>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Sikshya checks every hour for newly-unlocked lessons and sends templated email to affected learners.
                      Configure copy under{' '}
                      <a className="font-medium text-teal-700 underline dark:text-teal-300" href={appViewHref(config, 'email-hub', { tab: 'templates' })}>
                        Email templates
                      </a>{' '}
                      and toggles on the{' '}
                      <a className="font-medium text-teal-700 underline dark:text-teal-300" href={appViewHref(config, 'email')}>
                        Email
                      </a>{' '}
                      screen.
                    </p>
                  </div>
                </div>

                {notifyQ.error ? (
                  <div className="mt-3">
                    <ApiErrorPanel error={notifyQ.error} title="Could not load status" onRetry={() => notifyQ.refetch()} />
                  </div>
                ) : notifyQ.loading ? (
                  <p className="mt-3 text-sm text-slate-500">Loading…</p>
                ) : (
                  <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3 dark:border-slate-700 dark:bg-slate-900/60">
                      <dt className="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Next run
                      </dt>
                      <dd className="mt-0.5 font-medium text-slate-900 dark:text-white">
                        {notifyQ.data?.next_drip_run_iso ? notifyQ.data.next_drip_run_iso : '—'}
                      </dd>
                    </div>
                    <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3 dark:border-slate-700 dark:bg-slate-900/60">
                      <dt className="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Schedule addon
                      </dt>
                      <dd className="mt-0.5 font-medium text-slate-900 dark:text-white">
                        {notifyQ.data?.drip_addon_enabled ? 'Enabled' : 'Disabled'}
                      </dd>
                    </div>
                    <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3 dark:border-slate-700 dark:bg-slate-900/60">
                      <dt className="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Lesson unlock email
                      </dt>
                      <dd className="mt-0.5 font-medium text-slate-900 dark:text-white">
                        {notifyQ.data?.lesson_unlock_email_active
                          ? 'On'
                          : notifyQ.data?.drip_notifications_addon_enabled === false
                            ? 'Off (Drip notifications add-on)'
                            : notifyQ.data?.template_lesson_unlock_enabled === false
                              ? 'Off (template disabled)'
                              : 'Off (plan)'}
                      </dd>
                    </div>
                    <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-3 dark:border-slate-700 dark:bg-slate-900/60">
                      <dt className="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Course-wide unlock email
                      </dt>
                      <dd className="mt-0.5 font-medium text-slate-900 dark:text-white">
                        {notifyQ.data?.course_unlock_email_active
                          ? 'On'
                          : notifyQ.data?.drip_notifications_addon_enabled === false
                            ? 'Off (Drip notifications add-on)'
                            : notifyQ.data?.template_course_unlock_enabled === false
                              ? 'Off (template disabled)'
                              : 'Off (plan)'}
                      </dd>
                    </div>
                    {notifyQ.data?.note ? (
                      <p className="sm:col-span-2 text-xs text-slate-500 dark:text-slate-400">{notifyQ.data.note}</p>
                    ) : null}
                    {!notifyQ.data?.drip_addon_enabled ? (
                      <p className="sm:col-span-2 text-xs text-amber-700 dark:text-amber-300">
                        Notifications only fire when Scheduled access is also enabled above. Without it, nothing actually
                        unlocks for learners and there is nothing to email about.
                      </p>
                    ) : null}
                  </dl>
                )}
              </ListPanel>
            </GatedFeatureWorkspace>
          </div>
        </div>
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}
