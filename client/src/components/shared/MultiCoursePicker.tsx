import { useCallback, useEffect, useMemo, useState } from 'react';
import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../../api';
import { ButtonPrimary, ButtonSecondary } from './buttons';
import { FieldHint } from './FieldHint';
import { Modal } from './Modal';

export type MultiCourseOption = { id: number; title: string; status: string };

type Props = {
  value: number[];
  onChange: (next: number[]) => void;
  /** Resolved labels (id -> title); rendered in chips. Optional. */
  labels?: Record<number, string>;
  /** Excluded ids in addition to current `value`. */
  excludeIds?: number[];
  placeholder?: string;
  /** Modal title; defaults to "Select courses". */
  title?: string;
  /** Primary action label in the picker modal (default: "Select"). */
  confirmLabel?: string;
  /** Helper line shown under the field. */
  hint?: string;
  /** Defaults to 20 results per search. */
  perPage?: number;
  /** Disable interaction (viewer mode). */
  readOnly?: boolean;
  /** When set (e.g. `1`), only one course can be selected — same modal UX as multi, radio-like behavior. */
  maxSelection?: number;
  /** Extra classes on the wrapper (e.g. max width in toolbars). */
  className?: string;
};

function uniqSorted(ids: number[]) {
  return Array.from(new Set(ids.filter((n) => Number.isFinite(n) && n > 0))).sort((a, b) => a - b);
}

/**
 * Multi-course selector with a modal list (search + checkboxes + Done), matching
 * the "Applicable To → Specific ..." UX pattern.
 */
export function MultiCoursePicker({
  value,
  onChange,
  labels,
  excludeIds,
  placeholder = 'Click to select courses…',
  title = 'Select courses',
  confirmLabel,
  hint,
  perPage = 20,
  readOnly = false,
  maxSelection,
  className = '',
}: Props) {
  const selected = useMemo(() => uniqSorted(value || []), [value]);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [busy, setBusy] = useState(false);
  const [results, setResults] = useState<MultiCourseOption[]>([]);
  const [labelMap, setLabelMap] = useState<Record<number, string>>(() => ({ ...(labels || {}) }));

  // Draft selection inside the modal; only commits on Done.
  const [draft, setDraft] = useState<number[]>(selected);

  useEffect(() => {
    if (!labels) return;
    setLabelMap((prev) => ({ ...prev, ...labels }));
  }, [labels]);

  useEffect(() => {
    // Ensure already-selected course IDs render with titles (not just "Course #123")
    // even before the user opens the search modal.
    const missing = selected.filter((id) => !labelMap[id]);
    if (missing.length === 0) {
      return;
    }
    let cancelled = false;
    void (async () => {
      try {
        const include = missing.slice(0, 100).join(',');
        const rows = await getWpApi().get<Array<{ id: number; title?: { rendered?: string } }>>(
          `/sik_course?context=edit&per_page=100&include=${encodeURIComponent(include)}&_fields=id,title`
        );
        if (cancelled || !Array.isArray(rows)) return;
        setLabelMap((prev) => {
          const next = { ...prev };
          for (const r of rows) {
            const t = r?.title?.rendered ? String(r.title.rendered).replace(/<[^>]*>/g, '').trim() : '';
            if (r?.id && t) next[r.id] = t;
          }
          return next;
        });
      } catch {
        // Non-fatal: fallback remains "Course #id"
      }
    })();
    return () => {
      cancelled = true;
    };
    // We intentionally depend on `selected` and `labelMap` so this re-checks after user adds items.
  }, [selected, labelMap]);

  useEffect(() => {
    if (!open) return;
    setDraft(selected);
    // reset search each open so the list starts friendly
    setQuery('');
    setResults([]);
  }, [open, selected]);

  const search = useCallback(
    async (term: string) => {
      const exclude = Array.from(new Set([...(excludeIds || [])])).filter((n) => n > 0);
      const excludeSet = new Set(exclude);
      setBusy(true);
      let usedSikshya = false;
      try {
        const r = await getSikshyaApi().get<{ ok?: boolean; courses?: MultiCourseOption[] }>(
          SIKSHYA_ENDPOINTS.pro.coursesSearch({ search: term.trim(), exclude, per_page: perPage })
        );
        if (Array.isArray(r?.courses)) {
          const rows = r.courses.filter((c) => c?.id && !excludeSet.has(c.id));
          setResults(rows);
          setLabelMap((prev) => {
            const next = { ...prev };
            for (const o of rows) {
              next[o.id] = o.title;
            }
            return next;
          });
          usedSikshya = true;
        }
      } catch {
        // Route may be absent (e.g. prerequisites add-on off) — fall back to WP REST.
      }
      if (!usedSikshya) {
        try {
          const params = new URLSearchParams({
            context: 'edit',
            per_page: String(perPage),
            page: '1',
            _fields: 'id,title,status',
          });
          const q = term.trim();
          if (q) {
            params.set('search', q);
          }
          const raw = await getWpApi().get<
            Array<{ id: number; title?: { rendered?: string }; status?: string }>
          >(`/sik_course?${params.toString()}`);
          const mapped = (Array.isArray(raw) ? raw : [])
            .filter((p) => p?.id && !excludeSet.has(p.id))
            .map((p) => ({
              id: p.id,
              title: p.title?.rendered
                ? String(p.title.rendered).replace(/<[^>]*>/g, '').trim()
                : `Course #${p.id}`,
              status: typeof p.status === 'string' ? p.status : 'publish',
            }));
          setResults(mapped);
          setLabelMap((prev) => {
            const next = { ...prev };
            for (const o of mapped) {
              next[o.id] = o.title;
            }
            return next;
          });
        } catch {
          setResults([]);
        }
      }
      setBusy(false);
    },
    [excludeIds, perPage]
  );

  useEffect(() => {
    if (!open) return;
    const t = window.setTimeout(() => void search(query), 200);
    return () => window.clearTimeout(t);
  }, [open, query, search]);

  const toggle = (id: number) => {
    if (max === 1) {
      setDraft((prev) => (prev[0] === id ? [] : [id]));
      return;
    }
    setDraft((prev) => {
      const set = new Set(prev);
      if (set.has(id)) {
        set.delete(id);
      } else if (max !== undefined && set.size >= max) {
        return prev;
      } else {
        set.add(id);
      }
      return uniqSorted(Array.from(set));
    });
  };

  const fieldSummary = (() => {
    if (selected.length === 0) return placeholder;
    if (selected.length === 1) {
      const id = selected[0];
      const t = labelMap[id];
      return t ? `${t} · #${id}` : `Course #${id}`;
    }
    return `${selected.length} courses selected`;
  })();

  const max = maxSelection !== undefined && maxSelection > 0 ? maxSelection : undefined;
  const primaryLabel = confirmLabel ?? (max === 1 ? 'Select course' : 'Select courses');

  return (
    <div className={`space-y-2 ${className}`.trim()}>
      <button
        type="button"
        disabled={readOnly}
        onClick={() => setOpen(true)}
        className={`flex w-full items-center justify-between gap-3 rounded-xl border px-4 py-3 text-left text-sm shadow-sm ${
          readOnly
            ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400'
            : 'border-slate-200 bg-white text-slate-900 hover:border-brand-300 dark:border-slate-700 dark:bg-slate-950 dark:text-white'
        }`}
        aria-label={title}
      >
        <span className={`min-w-0 truncate ${selected.length === 0 ? 'text-slate-500 dark:text-slate-400' : ''}`}>
          {fieldSummary}
        </span>
        <span className="text-xs text-slate-400">▾</span>
      </button>

      <FieldHint>{hint}</FieldHint>

      {selected.length > 0 ? (
        <ul className="flex flex-wrap gap-2">
          {selected.map((id) => (
            <li
              key={id}
              className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
            >
              <span className="max-w-[16rem] truncate">
                {labelMap[id] ? `${labelMap[id]} · #${id}` : `Course #${id}`}
              </span>
            </li>
          ))}
        </ul>
      ) : null}

      <Modal
        open={open}
        title={title}
        description={max === 1 ? 'Search courses, then pick one course (or leave empty for all).' : 'Search courses, then tick one or more items.'}
        size="lg"
        onClose={() => setOpen(false)}
        footer={
          <div className="flex flex-wrap items-center justify-between gap-3">
            <ButtonSecondary type="button" onClick={() => setOpen(false)}>
              Close
            </ButtonSecondary>
            <div className="flex flex-wrap items-center justify-end gap-3">
              <span className="text-xs text-slate-500 dark:text-slate-400">
                {max === 1 ? (draft.length === 0 ? 'No course' : '1 course') : `${draft.length} selected`}
              </span>
              <ButtonPrimary
                type="button"
                // Defensive contrast: some installs don't ship the custom `brand-*` Tailwind palette,
                // which can make the primary button render as white text on a light background.
                className="bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500"
                onClick={() => {
                  if (max !== undefined && draft.length > max) {
                    return;
                  }
                  onChange(max === 1 ? draft.slice(0, 1) : draft);
                  setOpen(false);
                }}
              >
                {primaryLabel}
              </ButtonPrimary>
            </div>
          </div>
        }
      >
        <div className="space-y-3">
          <input
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search courses…"
            className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
          />

          <div className="max-h-[360px] overflow-auto rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-950">
            {busy ? (
              <div className="p-4 text-sm text-slate-500 dark:text-slate-400">Searching…</div>
            ) : results.length === 0 ? (
              <div className="p-4 text-sm text-slate-500 dark:text-slate-400">
                {query.trim() ? 'No matching courses.' : 'Start typing to search courses.'}
              </div>
            ) : (
              <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                {results.map((r) => {
                  const checked = draft.includes(r.id);
                  return (
                    <li key={r.id}>
                      <label className="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-900/60">
                        <span className="flex min-w-0 items-center gap-3">
                          <input
                            type="checkbox"
                            checked={checked}
                            onChange={() => toggle(r.id)}
                            className="h-4 w-4"
                          />
                          <span className="min-w-0">
                            <span className="block truncate text-sm font-medium text-slate-900 dark:text-white">
                              {r.title}
                            </span>
                            <span className="block text-xs text-slate-500 dark:text-slate-400">#{r.id}</span>
                          </span>
                        </span>
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                          {r.status}
                        </span>
                      </label>
                    </li>
                  );
                })}
              </ul>
            )}
          </div>
        </div>
      </Modal>
    </div>
  );
}

