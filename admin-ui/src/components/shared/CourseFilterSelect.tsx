import { createPortal } from 'react-dom';
import { useCallback, useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../../api';
import { FieldHint } from './FieldHint';
import { useDebouncedValue } from '../../hooks/useDebouncedValue';

type CourseRow = { id: number; title: string; status?: string };

function normalizeCourses(raw: { courses?: Array<{ id: number; title: string; status?: string }> } | undefined): CourseRow[] {
  const list = Array.isArray(raw?.courses)
    ? raw!.courses!.map((x) => ({
        id: x.id,
        title: (x.title && String(x.title).trim()) || `Course #${x.id}`,
        status: x.status,
      }))
    : [];
  list.sort((a, b) => a.title.localeCompare(b.title, undefined, { sensitivity: 'base' }));
  return list;
}

/**
 * Searchable course picker: dropdown with search, backed by {@link SIKSHYA_ENDPOINTS.pro.coursesSearch}.
 * Use `fieldLayout="toolbar"` for list filters (hint slot + stretch); `compact` for forms/modals.
 */
export function CourseFilterSelect(props: {
  enabled: boolean;
  value: number;
  onChange: (courseId: number) => void;
  disabled?: boolean;
  allLabel?: string;
  /** When false, omit the first “clear” row (e.g. modal must pick a real course). */
  allowClear?: boolean;
  /** Toolbar = hint row + flex stretch; compact = control only (e.g. inside modals). */
  fieldLayout?: 'toolbar' | 'compact';
  /** Fixed dropdown z-index (raise above dialogs, e.g. 11050). */
  dropdownZIndex?: number;
  hint?: string;
  label?: string;
  /** Use `sr-only` on list toolbars so the trigger lines up with search/sort controls. */
  labelVisibility?: 'visible' | 'sr-only';
  className?: string;
}) {
  const {
    enabled,
    value,
    onChange,
    disabled,
    allLabel = 'All courses',
    allowClear = true,
    fieldLayout = 'toolbar',
    dropdownZIndex = 10000,
    hint,
    label = 'Course filter',
    labelVisibility = 'visible',
    className,
  } = props;
  const triggerId = useId();
  const listboxId = useId();
  const hintDescId = useId();
  const wrapRef = useRef<HTMLDivElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  /** Only debounce while the panel is open so a stale term is not used right after reopening. */
  const debouncedSearch = useDebouncedValue(open ? query : '', 280);
  const [results, setResults] = useState<CourseRow[]>([]);
  const [listLoading, setListLoading] = useState(false);
  const [labelById, setLabelById] = useState<Record<number, string>>({});
  const [pos, setPos] = useState<{ top: number; left: number; width: number } | null>(null);

  const mergeLabels = useCallback((rows: CourseRow[]) => {
    setLabelById((prev) => {
      const next = { ...prev };
      for (const r of rows) next[r.id] = r.title;
      return next;
    });
  }, []);

  useEffect(() => {
    if (!open) setQuery('');
  }, [open]);

  const resolvedTitle = value > 0 ? labelById[value] : undefined;

  useEffect(() => {
    if (!enabled || value <= 0 || resolvedTitle) {
      return;
    }

    let cancelled = false;
    void (async () => {
      try {
        const data = await getWpApi().get<Array<{ id: number; title?: { rendered?: string } }>>(
          `/sik_course?context=edit&per_page=1&include=${encodeURIComponent(String(value))}&_fields=id,title`
        );
        if (cancelled || !Array.isArray(data) || !data[0]) {
          if (!cancelled) {
            setLabelById((prev) => (prev[value] ? prev : { ...prev, [value]: `Course #${value}` }));
          }
          return;
        }
        const t = data[0].title?.rendered ? String(data[0].title.rendered).replace(/<[^>]*>/g, '').trim() : '';
        if (!cancelled) {
          const title = t || `Course #${value}`;
          setLabelById((prev) => ({ ...prev, [value]: title }));
        }
      } catch {
        if (!cancelled) {
          setLabelById((prev) => (prev[value] ? prev : { ...prev, [value]: `Course #${value}` }));
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [enabled, value, resolvedTitle]);

  useEffect(() => {
    if (!open || !enabled) return;
    let cancelled = false;
    setListLoading(true);
    void (async () => {
      try {
        const r = await getSikshyaApi().get<{ courses?: Array<{ id: number; title: string; status?: string }> }>(
          SIKSHYA_ENDPOINTS.pro.coursesSearch({ search: debouncedSearch.trim(), per_page: 50 })
        );
        if (cancelled) return;
        const list = normalizeCourses(r);
        setResults(list);
        mergeLabels(list);
      } catch {
        if (!cancelled) setResults([]);
      } finally {
        if (!cancelled) setListLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [open, enabled, debouncedSearch, mergeLabels]);

  useLayoutEffect(() => {
    if (!open || !wrapRef.current) {
      setPos(null);
      return;
    }
    const update = () => {
      const el = wrapRef.current;
      if (!el) return;
      const rect = el.getBoundingClientRect();
      const pad = 8;
      const width = Math.max(rect.width, 280);
      let left = rect.left;
      if (left + width > window.innerWidth - pad) {
        left = Math.max(pad, window.innerWidth - pad - width);
      }
      setPos({ top: rect.bottom + 4, left, width });
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
  }, [open, results.length]);

  useEffect(() => {
    if (!open) return;
    const t = window.setTimeout(() => searchInputRef.current?.focus(), 0);
    return () => window.clearTimeout(t);
  }, [open]);

  useEffect(() => {
    if (!open) return;
    const onPointer = (e: MouseEvent | TouchEvent) => {
      const n = e.target;
      if (!(n instanceof Node)) return;
      if (wrapRef.current?.contains(n) || panelRef.current?.contains(n)) return;
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

  useEffect(() => {
    if (!enabled) setOpen(false);
  }, [enabled]);

  const displayLabel = value <= 0 ? allLabel : labelById[value] || `Course #${value}`;
  const labelSrOnly = labelVisibility === 'sr-only';
  const hintForA11y = hint && labelSrOnly ? `${hint}` : undefined;

  const pick = (id: number, title?: string) => {
    onChange(id);
    if (id > 0 && title) {
      setLabelById((prev) => ({ ...prev, [id]: title }));
    }
    setOpen(false);
  };

  const panel =
    open && pos && typeof document !== 'undefined' ? (
      <div
        ref={panelRef}
        style={{
          position: 'fixed',
          top: pos.top,
          left: pos.left,
          width: pos.width,
          zIndex: dropdownZIndex,
        }}
        className="overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl ring-1 ring-black/5 dark:border-slate-600 dark:bg-slate-900 dark:ring-white/10"
      >
        <div className="border-b border-slate-100 px-2 pb-2 pt-1 dark:border-slate-800">
          <input
            ref={searchInputRef}
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search courses…"
            aria-controls={listboxId}
            autoComplete="off"
            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none ring-brand-400 focus:border-brand-400 focus:ring-1 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
          />
        </div>
        <ul id={listboxId} role="listbox" aria-label="Courses" className="max-h-64 overflow-y-auto py-1">
          {allowClear ? (
            <li role="presentation">
              <button
                type="button"
                role="option"
                aria-selected={value === 0}
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => pick(0)}
                className={`flex w-full items-center px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800 ${
                  value === 0 ? 'bg-slate-50 font-medium dark:bg-slate-800/80' : ''
                }`}
              >
                {allLabel}
              </button>
            </li>
          ) : null}
          {listLoading ? (
            <li className="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">Searching…</li>
          ) : results.length === 0 ? (
            <li className="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">
              {query.trim() ? 'No courses match your search.' : 'No courses found.'}
            </li>
          ) : (
            results.map((r) => (
              <li key={r.id} role="presentation">
                <button
                  type="button"
                  role="option"
                  aria-selected={value === r.id}
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => pick(r.id, r.title)}
                  className={`flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800 ${
                    value === r.id ? 'bg-slate-50 font-medium dark:bg-slate-800/80' : ''
                  }`}
                >
                  <span className="min-w-0 truncate text-slate-900 dark:text-white">{r.title}</span>
                  <span className="text-xs text-slate-500 dark:text-slate-400">
                    #{r.id}
                    {r.status && r.status !== 'publish' ? ` · ${r.status}` : ''}
                  </span>
                </button>
              </li>
            ))
          )}
        </ul>
      </div>
    ) : null;

  const shellClass =
    fieldLayout === 'toolbar'
      ? `flex h-full min-h-0 flex-col text-sm text-slate-700 dark:text-slate-300 ${className ?? ''}`
      : `flex flex-col text-sm text-slate-700 dark:text-slate-300 ${className ?? ''}`;

  return (
    <div className={shellClass}>
      <span
        id={`${triggerId}-label`}
        className={labelSrOnly ? 'sr-only' : 'shrink-0 font-normal text-slate-700 dark:text-slate-300'}
      >
        {label}
      </span>
      {hintForA11y ? (
        <span id={hintDescId} className="sr-only">
          {hintForA11y}
        </span>
      ) : null}
      <div ref={wrapRef} className={labelSrOnly ? 'mt-0 shrink-0' : 'mt-1 shrink-0'}>
        <button
          id={triggerId}
          type="button"
          disabled={disabled}
          aria-labelledby={`${triggerId}-label`}
          aria-describedby={hintForA11y ? hintDescId : undefined}
          aria-haspopup="listbox"
          aria-expanded={open}
          aria-controls={open ? listboxId : undefined}
          title={labelSrOnly && hint ? hint : undefined}
          onClick={() => {
            if (disabled) return;
            setOpen((o) => !o);
          }}
          className={`flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-slate-900 shadow-sm hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:hover:border-slate-600 ${
            labelSrOnly ? 'min-w-[12rem] sm:min-w-[14rem]' : ''
          }`}
        >
          <span className="min-w-0 flex-1 truncate">{disabled ? '…' : displayLabel}</span>
          <span className="shrink-0 text-xs text-slate-400" aria-hidden>
            ▾
          </span>
        </button>
      </div>
      {fieldLayout === 'toolbar' ? (
        <>
          <div className="min-h-0 flex-1" aria-hidden />
          {hint && !labelSrOnly ? <FieldHint>{hint}</FieldHint> : null}
        </>
      ) : hint && !labelSrOnly ? (
        <p className="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
      ) : null}
      {typeof document !== 'undefined' && panel ? createPortal(panel, document.body) : null}
    </div>
  );
}
