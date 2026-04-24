import { createPortal } from 'react-dom';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../../api';

type CourseRow = { id: number; title: string; status?: string };

type CoursePrereqResp = {
  ok?: boolean;
  prerequisite_courses?: CourseRow[];
};

type LockedLessonRow = {
  lesson_id: number;
  title: string;
  status?: string;
  prerequisite_lessons?: CourseRow[];
};

type LessonSummaryResp = {
  ok?: boolean;
  locked_lessons?: LockedLessonRow[];
};

/**
 * Clickable count in the Prerequisites table; opens a popover with the
 * underlying required courses (enrollment) or lessons + their required lessons.
 */
export function PrerequisiteLockDetailPopover(props: {
  kind: 'enrollment' | 'lessons';
  courseId: number;
  courseTitle: string;
  count: number;
  enabled: boolean;
}) {
  const { kind, courseId, courseTitle, count, enabled } = props;
  const [open, setOpen] = useState(false);
  const wrapRef = useRef<HTMLSpanElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const [pos, setPos] = useState<{ top: number; left: number; maxW: number } | null>(null);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [courses, setCourses] = useState<CourseRow[]>([]);
  const [lockedLessons, setLockedLessons] = useState<LockedLessonRow[]>([]);

  useLayoutEffect(() => {
    if (!open || !wrapRef.current) {
      setPos(null);
      return;
    }
    const update = () => {
      const el = wrapRef.current;
      if (!el) return;
      const rect = el.getBoundingClientRect();
      const gap = 6;
      const pad = 10;
      const maxW = Math.min(22 * 16, Math.max(240, window.innerWidth - pad * 2));
      let left = rect.left;
      if (left + maxW > window.innerWidth - pad) {
        left = Math.max(pad, window.innerWidth - pad - maxW);
      }
      setPos({ top: rect.bottom + gap, left, maxW });
    };
    update();
    const id = window.requestAnimationFrame(update);
    window.addEventListener('resize', update);
    window.addEventListener('scroll', update, true);
    return () => {
      window.cancelAnimationFrame(id);
      window.removeEventListener('resize', update);
      window.removeEventListener('scroll', update, true);
    };
  }, [open]);

  useEffect(() => {
    if (!open) {
      setError(null);
      setCourses([]);
      setLockedLessons([]);
      setLoading(false);
      return;
    }
    if (!enabled || courseId <= 0) return;

    if (count <= 0) {
      setLoading(false);
      setError(null);
      setCourses([]);
      setLockedLessons([]);
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(null);
    void (async () => {
      try {
        if (kind === 'enrollment') {
          const data = await getSikshyaApi().get<CoursePrereqResp>(SIKSHYA_ENDPOINTS.pro.coursePrerequisites(courseId));
          if (cancelled) return;
          setCourses(Array.isArray(data.prerequisite_courses) ? data.prerequisite_courses : []);
        } else {
          const data = await getSikshyaApi().get<LessonSummaryResp>(
            SIKSHYA_ENDPOINTS.pro.courseLessonPrerequisiteSummary(courseId)
          );
          if (cancelled) return;
          setLockedLessons(Array.isArray(data.locked_lessons) ? data.locked_lessons : []);
        }
      } catch (e) {
        if (!cancelled) setError(e instanceof Error ? e.message : 'Could not load details');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [open, enabled, courseId, kind, count]);

  useEffect(() => {
    if (!open) return;
    const onPointer = (e: MouseEvent | TouchEvent) => {
      const t = e.target;
      if (!(t instanceof Node)) return;
      if (wrapRef.current?.contains(t) || panelRef.current?.contains(t)) return;
      setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onPointer);
    document.addEventListener('touchstart', onPointer, { passive: true });
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onPointer);
      document.removeEventListener('touchstart', onPointer);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const label =
    kind === 'enrollment'
      ? `${count} course${count === 1 ? '' : 's'}`
      : `${count} lesson${count === 1 ? '' : 's'}`;

  const title = kind === 'enrollment' ? 'Enrollment lock' : 'Lesson locks';

  const panel =
    open && pos ? (
      <div
        ref={panelRef}
        role="dialog"
        aria-label={`${title} — ${courseTitle}`}
        style={{
          position: 'fixed',
          top: pos.top,
          left: pos.left,
          maxWidth: pos.maxW,
          zIndex: 10000,
        }}
        className="rounded-xl border border-slate-200 bg-white py-2 pl-3 pr-2 shadow-xl ring-1 ring-black/5 dark:border-slate-600 dark:bg-slate-900 dark:ring-white/10"
      >
        <p className="border-b border-slate-100 pb-2 pr-1 text-xs font-semibold text-slate-800 dark:border-slate-700 dark:text-slate-100">
          <span className="block text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {title}
          </span>
          <span className="mt-0.5 block truncate">{courseTitle}</span>
        </p>
        <div className="max-h-[min(22rem,70vh)] overflow-y-auto py-2 pr-1 text-sm">
          {count <= 0 ? (
            <p className="text-slate-600 dark:text-slate-300">
              {kind === 'enrollment'
                ? 'No prior courses are required before enrollment.'
                : 'No lessons in this course use prerequisite locks yet.'}
            </p>
          ) : loading ? (
            <p className="text-slate-500 dark:text-slate-400">Loading…</p>
          ) : error ? (
            <p className="text-rose-700 dark:text-rose-300">{error}</p>
          ) : kind === 'enrollment' ? (
            courses.length === 0 ? (
              <p className="text-slate-600 dark:text-slate-300">No required courses (counts may be syncing).</p>
            ) : (
              <ul className="space-y-1.5">
                {courses.map((c) => (
                  <li key={c.id} className="text-slate-800 dark:text-slate-100">
                    <span className="font-medium">{c.title || `Course #${c.id}`}</span>
                    {c.status && c.status !== 'publish' ? (
                      <span className="ml-1 text-xs text-slate-500 dark:text-slate-400">({c.status})</span>
                    ) : null}
                    <span className="ml-1 text-xs text-slate-400 dark:text-slate-500">#{c.id}</span>
                  </li>
                ))}
              </ul>
            )
          ) : lockedLessons.length === 0 ? (
            <p className="text-slate-600 dark:text-slate-300">No lesson locks found (counts may be syncing).</p>
          ) : (
            <ul className="space-y-3">
              {lockedLessons.map((row) => (
                <li key={row.lesson_id} className="border-b border-slate-100 pb-3 last:border-0 last:pb-0 dark:border-slate-800">
                  <p className="font-medium text-slate-900 dark:text-white">
                    {row.title || `Lesson #${row.lesson_id}`}
                    <span className="ml-1 text-xs font-normal text-slate-400 dark:text-slate-500">#{row.lesson_id}</span>
                  </p>
                  <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">Requires completing first:</p>
                  <ul className="mt-1 space-y-0.5 pl-2">
                    {(row.prerequisite_lessons ?? []).map((p) => (
                      <li key={p.id} className="text-slate-700 dark:text-slate-200">
                        · {p.title || `Lesson #${p.id}`}
                        <span className="text-xs text-slate-400 dark:text-slate-500"> #{p.id}</span>
                      </li>
                    ))}
                  </ul>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    ) : null;

  return (
    <>
      <span ref={wrapRef} className="inline-block">
        <button
          type="button"
          onClick={() => setOpen((o) => !o)}
          className="cursor-pointer border-b border-dotted border-current text-left font-medium text-slate-800 underline-offset-2 hover:text-indigo-700 dark:text-slate-100 dark:hover:text-indigo-300"
          aria-expanded={open}
        >
          {label}
        </button>
      </span>
      {typeof document !== 'undefined' && panel ? createPortal(panel, document.body) : null}
    </>
  );
}
