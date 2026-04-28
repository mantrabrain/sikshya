import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { BulkActionsBar } from '../components/shared/list/BulkActionsBar';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListSearchToolbar } from '../components/shared/list/ListSearchToolbar';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { DataTable, type Column } from '../components/shared/DataTable';
import { DataTableSkeleton } from '../components/shared/Skeleton';
import { Modal } from '../components/shared/Modal';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPaginationBar } from '../components/shared/list/ListPaginationBar';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { MultiCoursePicker } from '../components/shared/MultiCoursePicker';
import { SingleCoursePicker } from '../components/shared/SingleCoursePicker';
import { CourseFilterSelect } from '../components/shared/CourseFilterSelect';
import { FieldHint } from '../components/shared/FieldHint';
import { PrerequisiteLockDetailPopover } from '../components/shared/PrerequisiteLockDetailPopover';
import { RowActionsMenu, type RowActionItem } from '../components/shared/list/RowActionsMenu';
import { useAsyncData } from '../hooks/useAsyncData';
import { useDebouncedValue } from '../hooks/useDebouncedValue';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import type { SikshyaReactConfig } from '../types';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';

type LessonRow = { id: number; title: string; status: string };

type CoursePrereqResp = {
  ok?: boolean;
  course_id?: number;
  prerequisite_course_ids?: number[];
  prerequisite_courses?: LessonRow[];
};

type LessonPrereqResp = {
  ok?: boolean;
  lesson_id?: number;
  prerequisite_lesson_ids?: number[];
  prerequisite_lessons?: LessonRow[];
};

type LessonsResp = { ok?: boolean; course_id?: number; lessons?: LessonRow[] };

type PrereqCourseRow = {
  course_id: number;
  course_title: string;
  required_courses_count: number;
  lesson_locks_count: number;
  has_any_rules?: boolean;
};

type PrereqCourseListResp = {
  ok?: boolean;
  courses?: PrereqCourseRow[];
  total?: number;
  page?: number;
  per_page?: number;
  total_pages?: number;
};

export function PrerequisitesPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const dialog = useSikshyaDialog();
  const featureOk = isFeatureEnabled(config, 'prerequisites');
  const addon = useAddonEnabled('prerequisites');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 320);
  const [page, setPage] = useState(1);
  const perPage = 20;
  const [enrollmentModalOpen, setEnrollmentModalOpen] = useState(false);
  const [lessonModalOpen, setLessonModalOpen] = useState(false);
  const [addModalOpen, setAddModalOpen] = useState(false);
  const [addCourseId, setAddCourseId] = useState<number>(0);
  const [addLockType, setAddLockType] = useState<'enrollment' | 'lessons'>('enrollment');
  const [addStarting, setAddStarting] = useState(false);
  const [activeCourseRow, setActiveCourseRow] = useState<PrereqCourseRow | null>(null);
  const [modalCoursePrereqs, setModalCoursePrereqs] = useState<number[]>([]);
  const [modalCoursePrereqLabels, setModalCoursePrereqLabels] = useState<Record<number, string>>({});
  const [modalSavingEnrollment, setModalSavingEnrollment] = useState(false);

  const [modalLessonId, setModalLessonId] = useState<number>(0);
  const [modalLessonPrereqs, setModalLessonPrereqs] = useState<number[]>([]);
  const [modalSavingLesson, setModalSavingLesson] = useState(false);
  const [clearingEnrollmentId, setClearingEnrollmentId] = useState<number | null>(null);
  const [clearingLessonsCourseId, setClearingLessonsCourseId] = useState<number | null>(null);
  const [clearingAllLocksCourseId, setClearingAllLocksCourseId] = useState<number | null>(null);
  /** When true, list every course (including none with locks). Default false = only courses that still have locks, so a full delete removes the row. */
  const [showAllCourses, setShowAllCourses] = useState(false);

  const initialCourse = config.query?.course_id ? Number(config.query.course_id) : 0;
  const [filterCourseId, setFilterCourseId] = useState<number>(Number.isFinite(initialCourse) ? initialCourse : 0);
  const [listOrderBy, setListOrderBy] = useState<'modified' | 'title' | 'id'>('modified');
  const [listOrder, setListOrder] = useState<'asc' | 'desc'>('desc');
  const [selectedCourseIds, setSelectedCourseIds] = useState<Set<number>>(() => new Set());
  const [bulkActionValue, setBulkActionValue] = useState('');
  const [bulkBusy, setBulkBusy] = useState(false);
  const [bulkError, setBulkError] = useState<unknown>(null);
  const headerSelectRef = useRef<HTMLInputElement>(null);

  useEffect(() => setPage(1), [debouncedSearch, filterCourseId, listOrderBy, listOrder, showAllCourses]);

  useEffect(() => {
    setSelectedCourseIds(new Set());
    setBulkActionValue('');
  }, [page, filterCourseId, debouncedSearch, listOrderBy, listOrder, showAllCourses]);

  const listLoader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, courses: [] as PrereqCourseRow[], total: 0, page: 1, per_page: perPage, total_pages: 0 } as PrereqCourseListResp;
    }
    return getSikshyaApi().get<PrereqCourseListResp>(
      SIKSHYA_ENDPOINTS.pro.prerequisiteCourses({
        search: filterCourseId > 0 ? undefined : debouncedSearch.trim() || undefined,
        page: filterCourseId > 0 ? 1 : page,
        per_page: perPage,
        orderby: listOrderBy,
        order: listOrder,
        ...(filterCourseId > 0 ? { course_id: filterCourseId } : {}),
        ...(filterCourseId <= 0 && !showAllCourses ? { only_with_locks: true } : {}),
      })
    );
  }, [enabled, debouncedSearch, page, filterCourseId, perPage, listOrderBy, listOrder, showAllCourses]);
  const listQ = useAsyncData(listLoader, [
    enabled,
    debouncedSearch,
    page,
    filterCourseId,
    listOrderBy,
    listOrder,
    perPage,
    showAllCourses,
  ]);
  const rows = listQ.data?.courses ?? [];
  const toast = useTopRightToast(2600);

  const prereqBulkOptions = useMemo(
    () => [
      { value: 'clear_all', label: 'Delete all access locks' },
      { value: 'clear_enrollment', label: 'Delete enrollment lock only' },
      { value: 'clear_lesson_locks', label: 'Delete lesson locks only' },
    ],
    []
  );

  const visibleCourseIds = useMemo(() => rows.map((r) => r.course_id), [rows]);
  const checkedOnPage = useMemo(
    () => visibleCourseIds.filter((id) => selectedCourseIds.has(id)).length,
    [visibleCourseIds, selectedCourseIds]
  );
  const allVisibleSelected = visibleCourseIds.length > 0 && checkedOnPage === visibleCourseIds.length;

  useLayoutEffect(() => {
    const el = headerSelectRef.current;
    if (!el) return;
    el.indeterminate = checkedOnPage > 0 && checkedOnPage < visibleCourseIds.length;
  }, [checkedOnPage, visibleCourseIds.length]);

  const toggleSelectAllCourses = useCallback(() => {
    setSelectedCourseIds((prev) => {
      const next = new Set(prev);
      const allSel = visibleCourseIds.length > 0 && visibleCourseIds.every((id) => next.has(id));
      if (allSel) {
        visibleCourseIds.forEach((id) => next.delete(id));
      } else {
        visibleCourseIds.forEach((id) => next.add(id));
      }
      return next;
    });
  }, [visibleCourseIds]);

  const toggleCourseSelected = useCallback((courseId: number) => {
    setSelectedCourseIds((prev) => {
      const next = new Set(prev);
      if (next.has(courseId)) {
        next.delete(courseId);
      } else {
        next.add(courseId);
      }
      return next;
    });
  }, []);

  const clearAllAccessLocksForCourseId = useCallback(async (courseId: number) => {
    await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.coursePrerequisites(courseId), {
      prerequisite_course_ids: [],
    });
    const data = await getSikshyaApi().get<{ locked_lessons?: { lesson_id: number }[] }>(
      SIKSHYA_ENDPOINTS.pro.courseLessonPrerequisiteSummary(courseId)
    );
    const lids = (data.locked_lessons ?? []).map((x) => x.lesson_id).filter((lid) => lid > 0);
    await Promise.all(
      lids.map((lid) =>
        getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.lessonPrerequisites(lid), { prerequisite_lesson_ids: [] })
      )
    );
  }, []);

  const clearAllAccessLocksForRow = useCallback(
    async (r: PrereqCourseRow) => {
      const hasAny = (r.required_courses_count || 0) > 0 || (r.lesson_locks_count || 0) > 0;
      if (!hasAny) return;
      const ok = await dialog.confirm({
        title: 'Delete all access locks?',
        message:
          'Removes enrollment prerequisites and every lesson lock in this course. The course stays in your catalog; it disappears from this list until you add locks again.',
        confirmLabel: 'Delete all locks',
        variant: 'danger',
      });
      if (!ok) return;
      setClearingAllLocksCourseId(r.course_id);
      toast.clear();
      try {
        await clearAllAccessLocksForCourseId(r.course_id);
        toast.success('Removed', 'All access locks removed for this course.');
        void listQ.refetch();
      } catch (e) {
        toast.error('Request failed', e instanceof Error ? e.message : 'Request failed');
      } finally {
        setClearingAllLocksCourseId(null);
      }
    },
    [clearAllAccessLocksForCourseId, dialog, listQ]
  );

  const clearEnrollmentLocks = useCallback(
    async (r: PrereqCourseRow) => {
      if ((r.required_courses_count || 0) <= 0) return;
      const ok = await dialog.confirm({
        title: 'Clear enrollment lock?',
        message:
          'Learners will be able to enroll in this course without completing other courses first. This cannot be undone from here except by setting new requirements.',
        confirmLabel: 'Clear lock',
        variant: 'danger',
      });
      if (!ok) return;
      setClearingEnrollmentId(r.course_id);
      toast.clear();
      try {
        await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.coursePrerequisites(r.course_id), {
          prerequisite_course_ids: [],
        });
        toast.success('Removed', 'Enrollment lock cleared.');
        void listQ.refetch();
      } catch (e) {
        toast.error('Request failed', e instanceof Error ? e.message : 'Request failed');
      } finally {
        setClearingEnrollmentId(null);
      }
    },
    [dialog, listQ]
  );

  const clearAllLessonLocks = useCallback(
    async (r: PrereqCourseRow) => {
      if ((r.lesson_locks_count || 0) <= 0) return;
      const ok = await dialog.confirm({
        title: 'Remove all lesson locks?',
        message:
          'Every lesson in this course that currently requires other lessons first will be reset. Learners follow curriculum order only until you set locks again.',
        confirmLabel: 'Remove all',
        variant: 'danger',
      });
      if (!ok) return;
      setClearingLessonsCourseId(r.course_id);
      toast.clear();
      try {
        const data = await getSikshyaApi().get<{ locked_lessons?: { lesson_id: number }[] }>(
          SIKSHYA_ENDPOINTS.pro.courseLessonPrerequisiteSummary(r.course_id)
        );
        const lids = (data.locked_lessons ?? []).map((x) => x.lesson_id).filter((id) => id > 0);
        await Promise.all(
          lids.map((lid) =>
            getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.lessonPrerequisites(lid), { prerequisite_lesson_ids: [] })
          )
        );
        toast.success('Removed', 'Lesson locks removed.');
        void listQ.refetch();
      } catch (e) {
        toast.error('Request failed', e instanceof Error ? e.message : 'Request failed');
      } finally {
        setClearingLessonsCourseId(null);
      }
    },
    [dialog, listQ]
  );

  const onPrereqBulkApply = useCallback(async () => {
    if (!enabled || selectedCourseIds.size === 0 || bulkActionValue === '') {
      return;
    }
    const ids = [...selectedCourseIds];
    const n = ids.length;
    setBulkError(null);
    toast.clear();

    const run = async () => {
      if (bulkActionValue === 'clear_all') {
        await Promise.all(ids.map((courseId) => clearAllAccessLocksForCourseId(courseId)));
        toast.success('Removed', `Removed all access locks for ${n} course(s).`);
        return;
      }
      if (bulkActionValue === 'clear_enrollment') {
        await Promise.all(
          ids.map((courseId) =>
            getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.coursePrerequisites(courseId), {
              prerequisite_course_ids: [],
            })
          )
        );
        toast.success('Updated', `Updated ${n} course(s).`);
        return;
      }
      if (bulkActionValue === 'clear_lesson_locks') {
        for (const courseId of ids) {
          const data = await getSikshyaApi().get<{ locked_lessons?: { lesson_id: number }[] }>(
            SIKSHYA_ENDPOINTS.pro.courseLessonPrerequisiteSummary(courseId)
          );
          const lids = (data.locked_lessons ?? []).map((x) => x.lesson_id).filter((lid) => lid > 0);
          await Promise.all(
            lids.map((lid) =>
              getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.lessonPrerequisites(lid), { prerequisite_lesson_ids: [] })
            )
          );
        }
        toast.success('Removed', `Lesson locks cleared for ${n} course(s).`);
      }
    };

    if (bulkActionValue === 'clear_all') {
      const ok = await dialog.confirm({
        title: `Delete all access locks for ${n} course(s)?`,
        message:
          'Clears enrollment prerequisites and every lesson lock for each selected course. Rows disappear from this list until you add locks again (unless you turn on “Show courses without locks”).',
        confirmLabel: 'Delete all locks',
        variant: 'danger',
      });
      if (!ok) return;
    } else if (bulkActionValue === 'clear_enrollment') {
      const ok = await dialog.confirm({
        title: `Delete enrollment lock for ${n} course(s)?`,
        message:
          'Learners will be able to enroll in those courses without completing other courses first, where a lock existed.',
        confirmLabel: 'Delete locks',
        variant: 'danger',
      });
      if (!ok) return;
    } else if (bulkActionValue === 'clear_lesson_locks') {
      const ok = await dialog.confirm({
        title: `Delete all lesson locks for ${n} course(s)?`,
        message:
          'Every lesson in those courses that currently requires other lessons first will be reset. This can take a moment.',
        confirmLabel: 'Delete all lesson locks',
        variant: 'danger',
      });
      if (!ok) return;
    } else {
      return;
    }

    setBulkBusy(true);
    try {
      await run();
      setSelectedCourseIds(new Set());
      setBulkActionValue('');
      void listQ.refetch();
    } catch (e) {
      setBulkError(e);
    } finally {
      setBulkBusy(false);
    }
  }, [bulkActionValue, clearAllAccessLocksForCourseId, dialog, enabled, listQ, selectedCourseIds]);

  const columns: Column<PrereqCourseRow>[] = useMemo(
    () => [
      {
        id: '_bulk_select',
        header: (
          <input
            ref={headerSelectRef}
            type="checkbox"
            className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-700"
            aria-label="Select all courses on this page"
            checked={allVisibleSelected}
            onChange={toggleSelectAllCourses}
          />
        ),
        alwaysVisible: true,
        headerClassName: 'w-12',
        cellClassName: 'w-12',
        render: (r) => (
          <input
            type="checkbox"
            className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-700"
            aria-label={`Select ${r.course_title}`}
            checked={selectedCourseIds.has(r.course_id)}
            onChange={() => toggleCourseSelected(r.course_id)}
          />
        ),
      },
      {
        id: 'course',
        header: 'Course',
        render: (r) => (
          <div className="max-w-[30rem]">
            <div className="truncate font-semibold text-slate-900 dark:text-white">{r.course_title}</div>
            <div className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">#{r.course_id}</div>
          </div>
        ),
      },
      {
        id: 'enrollment',
        header: 'Enrollment lock',
        cellClassName: 'whitespace-nowrap text-slate-700 dark:text-slate-200',
        render: (r) => (
          <PrerequisiteLockDetailPopover
            kind="enrollment"
            courseId={r.course_id}
            courseTitle={r.course_title}
            count={r.required_courses_count || 0}
            enabled={enabled}
          />
        ),
      },
      {
        id: 'lessons',
        header: 'Lesson locks',
        cellClassName: 'whitespace-nowrap text-slate-700 dark:text-slate-200',
        render: (r) => (
          <PrerequisiteLockDetailPopover
            kind="lessons"
            courseId={r.course_id}
            courseTitle={r.course_title}
            count={r.lesson_locks_count || 0}
            enabled={enabled}
          />
        ),
      },
      {
        id: 'actions',
        header: '',
        headerClassName: 'w-[1%]',
        cellClassName: 'text-right align-middle',
        render: (r) => {
          const enrollmentBusy = clearingEnrollmentId === r.course_id;
          const lessonsBusy = clearingLessonsCourseId === r.course_id;
          const allBusy = clearingAllLocksCourseId === r.course_id;
          const hasEnrollmentLock = (r.required_courses_count || 0) > 0;
          const hasLessonLocks = (r.lesson_locks_count || 0) > 0;
          const hasAnyLock = hasEnrollmentLock || hasLessonLocks;
          const items: RowActionItem[] = [
            {
              key: 'edit-enrollment',
              label: 'Edit enrollment lock',
              onClick: () => {
                setActiveCourseRow(r);
                setEnrollmentModalOpen(true);
              },
            },
            {
              key: 'edit-lessons',
              label: 'Edit lesson locks',
              onClick: () => {
                setActiveCourseRow(r);
                setLessonModalOpen(true);
              },
            },
            {
              key: 'delete-all-access',
              label: allBusy ? 'Deleting all locks…' : 'Delete all access locks',
              danger: true,
              disabled: !hasAnyLock || allBusy || enrollmentBusy || lessonsBusy,
              onClick: () => void clearAllAccessLocksForRow(r),
            },
            {
              key: 'delete-enrollment',
              label: enrollmentBusy ? 'Deleting enrollment lock…' : 'Delete enrollment lock only',
              danger: true,
              disabled: !hasEnrollmentLock || enrollmentBusy || allBusy,
              onClick: () => void clearEnrollmentLocks(r),
            },
            {
              key: 'delete-lesson-locks',
              label: lessonsBusy ? 'Deleting lesson locks…' : 'Delete lesson locks only',
              danger: true,
              disabled: !hasLessonLocks || lessonsBusy || allBusy,
              onClick: () => void clearAllLessonLocks(r),
            },
          ];
          return (
            <div className="flex justify-end" onClick={(e) => e.stopPropagation()}>
              <RowActionsMenu items={items} ariaLabel={`Actions for ${r.course_title}`} />
            </div>
          );
        },
      },
    ],
    [
      enabled,
      clearingAllLocksCourseId,
      clearingEnrollmentId,
      clearingLessonsCourseId,
      clearAllAccessLocksForRow,
      clearAllLessonLocks,
      clearEnrollmentLocks,
      allVisibleSelected,
      selectedCourseIds,
      toggleSelectAllCourses,
      toggleCourseSelected,
    ]
  );

  const modalLessonsLoader = useCallback(async () => {
    const cid = activeCourseRow?.course_id ?? 0;
    if (!enabled || !lessonModalOpen || cid <= 0) {
      return { ok: true, course_id: cid, lessons: [] } as LessonsResp;
    }
    return getSikshyaApi().get<LessonsResp>(SIKSHYA_ENDPOINTS.pro.courseLessons(cid));
  }, [enabled, lessonModalOpen, activeCourseRow?.course_id]);
  const modalLessonsQ = useAsyncData(modalLessonsLoader, [enabled, lessonModalOpen, activeCourseRow?.course_id]);
  const modalLessons = modalLessonsQ.data?.lessons ?? [];

  const modalLessonLoader = useCallback(async () => {
    if (!enabled || !lessonModalOpen || modalLessonId <= 0) {
      return { ok: true, lesson_id: modalLessonId, prerequisite_lesson_ids: [], prerequisite_lessons: [] } as LessonPrereqResp;
    }
    return getSikshyaApi().get<LessonPrereqResp>(SIKSHYA_ENDPOINTS.pro.lessonPrerequisites(modalLessonId));
  }, [enabled, lessonModalOpen, modalLessonId]);
  const modalLessonQ = useAsyncData(modalLessonLoader, [enabled, lessonModalOpen, modalLessonId]);

  useEffect(() => {
    if (!enrollmentModalOpen) return;
    const cid = activeCourseRow?.course_id ?? 0;
    if (cid <= 0) return;
    setModalSavingEnrollment(false);
    setModalCoursePrereqs([]);
    setModalCoursePrereqLabels({});
    void (async () => {
      try {
        const data = await getSikshyaApi().get<CoursePrereqResp>(SIKSHYA_ENDPOINTS.pro.coursePrerequisites(cid));
        setModalCoursePrereqs(data.prerequisite_course_ids ?? []);
        const labels: Record<number, string> = {};
        for (const r of data.prerequisite_courses ?? []) labels[r.id] = r.title;
        setModalCoursePrereqLabels(labels);
      } catch {
        // Surface via toast on save; keep empty state.
      }
    })();
  }, [enrollmentModalOpen, activeCourseRow?.course_id]);

  useEffect(() => {
    const data = modalLessonQ.data;
    if (!data) return;
    setModalLessonPrereqs(data.prerequisite_lesson_ids ?? []);
  }, [modalLessonQ.data]);

  const saveEnrollmentModal = async () => {
    const cid = activeCourseRow?.course_id ?? 0;
    if (cid <= 0) return;
    setModalSavingEnrollment(true);
    toast.clear();
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.coursePrerequisites(cid), {
        prerequisite_course_ids: modalCoursePrereqs,
      });
      toast.success('Saved', 'Enrollment lock saved.');
      setEnrollmentModalOpen(false);
      setActiveCourseRow(null);
      void listQ.refetch();
    } catch (e) {
      toast.error('Save failed', e instanceof Error ? e.message : 'Save failed');
    } finally {
      setModalSavingEnrollment(false);
    }
  };

  const saveLessonModal = async () => {
    if (modalLessonId <= 0) {
      toast.error('Missing lesson', 'Pick a lesson first.');
      return;
    }
    setModalSavingLesson(true);
    toast.clear();
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.lessonPrerequisites(modalLessonId), {
        prerequisite_lesson_ids: modalLessonPrereqs,
      });
      toast.success('Saved', 'Lesson lock saved.');
      setLessonModalOpen(false);
      setActiveCourseRow(null);
      setModalLessonId(0);
      void listQ.refetch();
    } catch (e) {
      toast.error('Save failed', e instanceof Error ? e.message : 'Save failed');
    } finally {
      setModalSavingLesson(false);
    }
  };

  const startAddPrerequisite = useCallback(async () => {
    if (addCourseId <= 0 || addStarting) {
      return;
    }

    setAddStarting(true);
    toast.clear();

    try {
      let courseTitle = rows.find((row) => row.course_id === addCourseId)?.course_title || '';
      if (!courseTitle) {
        const course = await getWpApi().get<{ id?: number; title?: { rendered?: string } }>(
          `/sik_course/${encodeURIComponent(String(addCourseId))}?context=edit&_fields=id,title`
        );
        courseTitle = course?.title?.rendered ? String(course.title.rendered).replace(/<[^>]*>/g, '').trim() : '';
      }

      const nextRow: PrereqCourseRow = {
        course_id: addCourseId,
        course_title: courseTitle || `Course #${addCourseId}`,
        required_courses_count: 0,
        lesson_locks_count: 0,
        has_any_rules: false,
      };

      setActiveCourseRow(nextRow);
      setAddModalOpen(false);
      if (addLockType === 'lessons') {
        setLessonModalOpen(true);
      } else {
        setEnrollmentModalOpen(true);
      }
    } catch (e) {
      toast.error('Could not load', e instanceof Error ? e.message : 'Could not load the selected course.');
    } finally {
      setAddStarting(false);
    }
  }, [addCourseId, addLockType, addStarting, rows]);

  // Auto-dismiss handled by shared toast.

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Block enrollment, or unlock lessons in order. Two simple rules per course."
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="prerequisites"
        config={config}
        featureTitle="Access locks (Prerequisites)"
        featureDescription="Build a guided learning path: require prior courses before enrolment, or require earlier lessons before later ones unlock."
        previewVariant="form"
        addonEnableTitle="Prerequisites is not enabled"
        addonEnableDescription="Turn on the Prerequisites add-on to enforce a learning order across courses and lessons."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => addon.enable()}
        addonError={addon.error}
      >
        <div className="space-y-6">
          <Modal
            open={addModalOpen}
            title="Add prerequisites"
            description="Pick a course, then choose whether you want to add an enrollment lock or lesson-level prerequisite locks."
            onClose={() => (addStarting ? null : setAddModalOpen(false))}
            size="lg"
            footer={
              <div className="flex items-center justify-end gap-2">
                <ButtonSecondary type="button" onClick={() => setAddModalOpen(false)} disabled={addStarting}>
                  Cancel
                </ButtonSecondary>
                <ButtonPrimary type="button" onClick={() => void startAddPrerequisite()} disabled={addCourseId <= 0 || addStarting}>
                  {addStarting ? 'Opening…' : 'Continue'}
                </ButtonPrimary>
              </div>
            }
          >
            <div className="space-y-5">
              <SingleCoursePicker
                value={addCourseId}
                onChange={setAddCourseId}
                label="Course"
                placeholder="Choose a course to add access locks…"
                hint="You can add prerequisites even if the course is not currently listed below."
              />

              <div>
                <p className="text-sm font-medium text-slate-900 dark:text-white">Lock type</p>
                <div className="mt-2 grid gap-3 sm:grid-cols-2">
                  <button
                    type="button"
                    className={`rounded-xl border p-4 text-left transition ${
                      addLockType === 'enrollment'
                        ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/20 dark:bg-brand-950/30'
                        : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900'
                    }`}
                    onClick={() => setAddLockType('enrollment')}
                  >
                    <div className="text-sm font-semibold text-slate-900 dark:text-white">Enrollment lock</div>
                    <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Require learners to complete other courses before they can enroll.
                    </div>
                  </button>
                  <button
                    type="button"
                    className={`rounded-xl border p-4 text-left transition ${
                      addLockType === 'lessons'
                        ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/20 dark:bg-brand-950/30'
                        : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900'
                    }`}
                    onClick={() => setAddLockType('lessons')}
                  >
                    <div className="text-sm font-semibold text-slate-900 dark:text-white">Lesson locks</div>
                    <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Force learners to complete earlier lessons before later lessons unlock.
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </Modal>

          <Modal
            open={enrollmentModalOpen}
            title={activeCourseRow ? `Edit enrollment lock — ${activeCourseRow.course_title}` : 'Edit enrollment lock'}
            description="Learners cannot enroll until they complete every required course."
            onClose={() => (modalSavingEnrollment ? null : (setEnrollmentModalOpen(false), setActiveCourseRow(null)))}
            size="lg"
            footer={
              <div className="flex items-center justify-end gap-2">
                <button
                  type="button"
                  className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                  onClick={() => {
                    setEnrollmentModalOpen(false);
                    setActiveCourseRow(null);
                  }}
                  disabled={modalSavingEnrollment}
                >
                  Cancel
                </button>
                <ButtonPrimary type="button" onClick={() => void saveEnrollmentModal()} disabled={modalSavingEnrollment}>
                  {modalSavingEnrollment ? 'Saving…' : 'Save'}
                </ButtonPrimary>
              </div>
            }
          >
            <MultiCoursePicker
              value={modalCoursePrereqs}
              onChange={setModalCoursePrereqs}
              labels={modalCoursePrereqLabels}
              excludeIds={activeCourseRow?.course_id ? [activeCourseRow.course_id] : []}
              title="Select required courses"
              placeholder="Click to select courses…"
              hint="Empty means enrollment is open."
            />
          </Modal>

          <Modal
            open={lessonModalOpen}
            title={activeCourseRow ? `Edit lesson order — ${activeCourseRow.course_title}` : 'Edit lesson order'}
            description="Pick a lesson, then choose which lessons must be completed first."
            onClose={() => (modalSavingLesson ? null : (setLessonModalOpen(false), setActiveCourseRow(null), setModalLessonId(0)))}
            size="xl"
            footer={
              <div className="flex items-center justify-end gap-2">
                <button
                  type="button"
                  className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                  onClick={() => {
                    setLessonModalOpen(false);
                    setActiveCourseRow(null);
                    setModalLessonId(0);
                  }}
                  disabled={modalSavingLesson}
                >
                  Cancel
                </button>
                <ButtonPrimary type="button" onClick={() => void saveLessonModal()} disabled={modalSavingLesson}>
                  {modalSavingLesson ? 'Saving…' : 'Save'}
                </ButtonPrimary>
              </div>
            }
          >
            <div className="grid gap-4 lg:grid-cols-[340px_1fr] lg:items-stretch">
              <div className="flex min-h-0 flex-col">
                <label className="block shrink-0 text-sm font-medium text-slate-800 dark:text-slate-200">Lesson</label>
                <select
                  value={modalLessonId}
                  onChange={(e) => setModalLessonId(Number(e.target.value))}
                  className="mt-1 w-full shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                >
                  <option value={0}>{modalLessonsQ.loading ? 'Loading…' : '— Select —'}</option>
                  {modalLessons.map((l) => (
                    <option key={l.id} value={l.id}>
                      {l.title}
                    </option>
                  ))}
                </select>
                <div className="min-h-0 flex-1" aria-hidden />
                <FieldHint>Select the lesson you want to lock.</FieldHint>
              </div>

              <div className="flex min-h-0 flex-col">
                <div className="flex shrink-0 items-center justify-between gap-2">
                  <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">Required lessons</label>
                  {modalLessonId > 0 ? (
                    <span className="text-xs text-slate-500 dark:text-slate-400">{modalLessonPrereqs.length} selected</span>
                  ) : null}
                </div>
                <div className="mt-2 min-h-[12rem] max-h-[360px] flex-1 overflow-auto rounded-xl border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-950">
                  {modalLessonId <= 0 ? (
                    <div className="p-3 text-sm text-slate-500 dark:text-slate-400">Pick a lesson to edit prerequisites.</div>
                  ) : modalLessonQ.loading ? (
                    <div className="p-3 text-sm text-slate-500 dark:text-slate-400">Loading…</div>
                  ) : (
                    <>
                      {modalLessons
                        .filter((l) => {
                          if (l.id === modalLessonId) return false;
                          const idx = modalLessons.findIndex((x) => x.id === l.id);
                          const selfIdx = modalLessons.findIndex((x) => x.id === modalLessonId);
                          // Backend rejects forward dependencies; keep UI aligned by showing only earlier lessons.
                          if (idx >= 0 && selfIdx >= 0) return idx < selfIdx;
                          return true;
                        })
                        .map((l) => {
                          const checked = modalLessonPrereqs.includes(l.id);
                          return (
                            <label
                              key={l.id}
                              className="flex cursor-pointer items-start gap-3 rounded-lg px-2 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-900"
                            >
                              <input
                                type="checkbox"
                                checked={checked}
                                onChange={() =>
                                  setModalLessonPrereqs((prev) =>
                                    prev.includes(l.id) ? prev.filter((x) => x !== l.id) : [...prev, l.id]
                                  )
                                }
                                className="mt-0.5 h-4 w-4"
                              />
                              <span className="min-w-0">
                                <span className="block truncate font-medium text-slate-900 dark:text-white">{l.title}</span>
                                <span className="block text-[11px] text-slate-500 dark:text-slate-400">Lesson #{l.id}</span>
                              </span>
                            </label>
                          );
                        })}
                      {modalLessons.length <= 1 ? (
                        <div className="p-3 text-sm text-slate-500 dark:text-slate-400">This course has no other lessons yet.</div>
                      ) : null}
                    </>
                  )}
                </div>
                <FieldHint>Only lessons earlier in the curriculum can be prerequisites.</FieldHint>
              </div>
            </div>
          </Modal>

          <ListPanel overflow="visible">
            <div className="border-b border-slate-100 px-4 py-4 dark:border-slate-800">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Courses with access locks</h2>
                  <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Each row is a course that still has an enrollment lock and/or lesson locks. Deleting all locks removes the
                    row from this list (the course is not deleted from the site). Use “Show courses without locks” to browse
                    the full catalog.
                  </p>
                </div>
                <div className="shrink-0">
                  <ButtonPrimary type="button" onClick={() => setAddModalOpen(true)} disabled={!enabled}>
                    Add prerequisites
                  </ButtonPrimary>
                </div>
              </div>
            </div>
            <ListSearchToolbar
              searchValue={search}
              onSearchChange={setSearch}
              searchPlaceholder={
                filterCourseId > 0 ? 'Clear course filter (right) to search by title…' : 'Search courses by title…'
              }
              searchDisabled={filterCourseId > 0}
              sortField={listOrderBy}
              sortFieldOptions={[
                { value: 'title', label: 'Course title' },
                { value: 'modified', label: 'Last modified' },
                { value: 'id', label: 'Course ID' },
              ]}
              onSortFieldChange={(v) => setListOrderBy(v as 'modified' | 'title' | 'id')}
              sortOrder={listOrder}
              onSortOrderToggle={() => setListOrder((o) => (o === 'asc' ? 'desc' : 'asc'))}
              trailing={
                <div className="flex flex-wrap items-center gap-2">
                  <CourseFilterSelect
                    enabled={enabled}
                    value={filterCourseId}
                    onChange={setFilterCourseId}
                    allLabel="All courses"
                    fieldLayout="compact"
                    labelVisibility="sr-only"
                    dropdownZIndex={11050}
                    hint="Limit to one course or All courses."
                  />
                  <label
                    className={`inline-flex min-h-[2.375rem] cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 ${
                      filterCourseId > 0 ? 'cursor-not-allowed opacity-50' : ''
                    }`}
                  >
                    <input
                      type="checkbox"
                      className="h-4 w-4 rounded border-slate-300 text-brand-600 dark:border-slate-600"
                      checked={showAllCourses}
                      disabled={filterCourseId > 0}
                      onChange={(e) => setShowAllCourses(e.target.checked)}
                    />
                    Show courses without locks
                  </label>
                </div>
              }
            />
            <div className="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
              <BulkActionsBar
                disabled={!enabled}
                customOptions={prereqBulkOptions}
                selectId="sikshya-prereq-bulk"
                selectedCount={selectedCourseIds.size}
                value={bulkActionValue}
                onChange={setBulkActionValue}
                onApply={() => void onPrereqBulkApply()}
                applyBusy={bulkBusy}
                trashMode={false}
              />
              <div className="shrink-0 text-xs font-medium tabular-nums text-slate-500 dark:text-slate-400 sm:text-right">
                {listQ.data?.total != null ? `${listQ.data.total} course${listQ.data.total === 1 ? '' : 's'}` : '\u00a0'}
              </div>
            </div>
            {bulkError ? (
              <div className="border-b border-red-100 px-4 py-3 dark:border-red-900/40">
                <ApiErrorPanel error={bulkError} title="Bulk action failed" onRetry={() => setBulkError(null)} />
              </div>
            ) : null}

            {listQ.error ? (
              <div className="p-4">
                <ApiErrorPanel error={listQ.error} title="Could not load courses" onRetry={() => listQ.refetch()} />
              </div>
            ) : listQ.loading ? (
              <DataTableSkeleton headers={['', 'Course', 'Enrollment lock', 'Lesson locks', '']} rows={8} />
            ) : (
              <>
                <ListPaginationBar
                  placement="top"
                  page={listQ.data?.page ?? page}
                  total={listQ.data?.total ?? null}
                  totalPages={listQ.data?.total_pages ?? null}
                  perPage={perPage}
                  onPageChange={setPage}
                  disabled={listQ.loading}
                />
                <DataTable
                  columns={columns}
                  rows={rows}
                  rowKey={(r) => r.course_id}
                  wrapInCard={false}
                  emptyContent={
                    <ListEmptyState
                      title="No courses found"
                      description={
                        filterCourseId > 0
                          ? 'No course matches this filter, or it was removed.'
                          : 'Try another search.'
                      }
                    />
                  }
                />
                <ListPaginationBar
                  placement="bottom"
                  page={listQ.data?.page ?? page}
                  total={listQ.data?.total ?? null}
                  totalPages={listQ.data?.total_pages ?? null}
                  perPage={perPage}
                  onPageChange={setPage}
                  disabled={listQ.loading}
                />
              </>
            )}
          </ListPanel>

          {/* What is this page? — disambiguation block */}
          <div className="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-xs text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-950/40 dark:text-indigo-200">
            <p className="font-semibold">What this page does</p>
            <p className="mt-1 leading-relaxed">
              These are <strong>access rules</strong> the LMS enforces. They are different from the marketing
              "Prerequisites" list inside the Course Builder, which is just text shown to visitors. Use this page when you
              want learners to <em>actually</em> be blocked until they finish prior content.
            </p>
          </div>

          <TopRightToast toast={toast.toast} onDismiss={toast.clear} />

        </div>
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}
