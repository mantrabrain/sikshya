import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { NavIcon } from './NavIcon';
import { __ } from '../lib/i18n';

type Hit = {
  id: number;
  title: string;
  subtitle: string;
  url: string;
};

type SearchResponse = {
  ok?: boolean;
  query?: string;
  results?: Record<string, Hit[] | undefined> & {
    users?: Hit[];
    courses?: Hit[];
    orders?: Hit[];
  };
};

/** Display labels for known buckets. Unknown buckets (Pro-contributed) get a TitleCase fallback. */
const BUCKET_LABELS: Record<string, string> = {
  courses: 'Courses',
  users: 'Users',
  orders: 'Orders',
};

const KNOWN_BUCKETS = new Set(['users', 'courses', 'orders']);

function titleCase(s: string): string {
  return s
    .split(/[-_]+/)
    .filter(Boolean)
    .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
    .join(' ');
}

const isMacLike = () =>
  typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent || '');

/**
 * Global admin search — Cmd/Ctrl+K opens a small palette that fans out across
 * users, courses, and orders by hitting `/admin/search`. Closes on Esc or
 * outside click. Designed to live in the TopBar.
 */
export function GlobalSearchPalette() {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const [hits, setHits] = useState<SearchResponse['results']>({ users: [], courses: [], orders: [] });
  const [loading, setLoading] = useState(false);
  const inputRef = useRef<HTMLInputElement | null>(null);
  const panelRef = useRef<HTMLDivElement | null>(null);
  const reqRef = useRef(0);

  // Cmd/Ctrl+K opens the palette globally.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const meta = isMacLike() ? e.metaKey : e.ctrlKey;
      if (meta && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        setOpen(true);
        return;
      }
      if (e.key === 'Escape' && open) {
        setOpen(false);
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open]);

  // Focus the input when the palette opens.
  useEffect(() => {
    if (open) {
      requestAnimationFrame(() => inputRef.current?.focus());
    } else {
      setQ('');
      setHits({ users: [], courses: [], orders: [] });
    }
  }, [open]);

  // Close on outside click while open.
  useEffect(() => {
    if (!open) return;
    const onClick = (e: MouseEvent) => {
      const el = panelRef.current;
      if (el && !el.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [open]);

  // Debounced fetch on query change.
  useEffect(() => {
    if (!open) return;
    if (q.trim().length < 2) {
      setHits({ users: [], courses: [], orders: [] });
      setLoading(false);
      return;
    }
    const myReq = ++reqRef.current;
    setLoading(true);
    const t = window.setTimeout(async () => {
      try {
        const r = await getSikshyaApi().get<SearchResponse>(
          `${SIKSHYA_ENDPOINTS.admin.search}?q=${encodeURIComponent(q.trim())}&limit=5`
        );
        if (myReq !== reqRef.current) return;
        setHits(r?.results ?? { users: [], courses: [], orders: [] });
      } catch {
        if (myReq !== reqRef.current) return;
        setHits({ users: [], courses: [], orders: [] });
      } finally {
        if (myReq === reqRef.current) {
          setLoading(false);
        }
      }
    }, 220);
    return () => window.clearTimeout(t);
  }, [q, open]);

  const totalHits = useMemo(() => {
    if (!hits) return 0;
    return Object.values(hits).reduce<number>((acc, rows) => acc + (rows?.length ?? 0), 0);
  }, [hits]);

  // Bucket render order: core buckets first (Courses, Users, Orders), then any
  // Pro-contributed buckets in stable alphabetical order.
  const orderedBuckets = useMemo(() => {
    const coreOrder = ['courses', 'users', 'orders'];
    const known = coreOrder.filter((k) => (hits?.[k]?.length ?? 0) > 0);
    const extra = Object.keys(hits ?? {})
      .filter((k) => !KNOWN_BUCKETS.has(k) && (hits?.[k]?.length ?? 0) > 0)
      .sort();
    return [...known, ...extra];
  }, [hits]);

  const renderGroup = useCallback(
    (label: string, rows: Hit[] | undefined) => {
      if (!rows || rows.length === 0) return null;
      return (
        <div className="border-t border-slate-100 px-2 py-2 first:border-t-0 dark:border-slate-800">
          <div className="px-2 pb-1 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
            {label}
          </div>
          <ul role="list" className="space-y-0.5">
            {rows.map((r) => (
              <li key={`${label}-${r.id}`}>
                <a
                  href={r.url}
                  onClick={() => setOpen(false)}
                  className="flex flex-col rounded-lg px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800"
                >
                  <span className="truncate font-medium text-slate-900 dark:text-white">{r.title}</span>
                  {r.subtitle ? (
                    <span className="truncate text-xs text-slate-500 dark:text-slate-400">{r.subtitle}</span>
                  ) : null}
                </a>
              </li>
            ))}
          </ul>
        </div>
      );
    },
    []
  );

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        title={__('Search (⌘K)', 'sikshya')}
        aria-label={__('Open global search', 'sikshya')}
        className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
        data-testid="topbar-global-search-trigger"
      >
        <NavIcon name="search" className="h-4 w-4 text-slate-500 dark:text-slate-400" />
        <span className="hidden sm:inline">{__('Search', 'sikshya')}</span>
        <kbd className="hidden rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 sm:inline">
          ⌘K
        </kbd>
      </button>

      {open ? (
        <div className="fixed inset-0 z-[100] flex items-start justify-center bg-slate-900/40 p-4 pt-[10vh] backdrop-blur-sm dark:bg-slate-950/60">
          <div
            ref={panelRef}
            role="dialog"
            aria-modal="true"
            aria-label={__('Global search', 'sikshya')}
            data-testid="topbar-global-search-panel"
            className="w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-black/5 dark:border-slate-700 dark:bg-slate-900 dark:ring-white/10"
          >
            <div className="flex items-center gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
              <NavIcon name="search" className="h-4 w-4 text-slate-400 dark:text-slate-500" />
              <input
                ref={inputRef}
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder={__('Search users, courses, orders…', 'sikshya')}
                className="w-full bg-transparent text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none dark:text-white"
                data-testid="topbar-global-search-input"
              />
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="text-xs text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300"
              >
                Esc
              </button>
            </div>
            <div className="max-h-[60vh] overflow-y-auto">
              {q.trim().length < 2 ? (
                <p className="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                  {__('Type at least 2 characters to search.', 'sikshya')}
                </p>
              ) : loading ? (
                <p className="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                  {__('Searching…', 'sikshya')}
                </p>
              ) : totalHits === 0 ? (
                <p className="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                  {__('No matches.', 'sikshya')}
                </p>
              ) : (
                <>
                  {orderedBuckets.map((key) => {
                    const label = BUCKET_LABELS[key] ?? titleCase(key);
                    return (
                      <div key={key}>{renderGroup(label, hits?.[key])}</div>
                    );
                  })}
                </>
              )}
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}
