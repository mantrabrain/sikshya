import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { NavIcon } from '../NavIcon';

export type ToastKind = 'success' | 'error' | 'info';

export type ToastState = {
  open: boolean;
  kind: ToastKind;
  title: string;
  message?: string;
  /** Auto-dismiss after this many ms. Set 0 to require manual close. */
  ttlMs?: number;
};

export function useTopRightToast(defaultTtlMs = 3800) {
  const [toast, setToast] = useState<ToastState | null>(null);

  useEffect(() => {
    if (!toast?.open) return;
    const ttl = toast.ttlMs ?? defaultTtlMs;
    if (!ttl || ttl <= 0) return;
    const t = window.setTimeout(() => setToast(null), ttl);
    return () => window.clearTimeout(t);
  }, [toast, defaultTtlMs]);

  const api = useMemo(() => {
    return {
      toast,
      clear: () => setToast(null),
      show: (next: Omit<ToastState, 'open'> & { open?: boolean }) =>
        setToast({ ttlMs: defaultTtlMs, ...next, open: next.open ?? true }),
      success: (title: string, message?: string) => setToast({ open: true, kind: 'success', title, message, ttlMs: defaultTtlMs }),
      error: (title: string, message?: string) => setToast({ open: true, kind: 'error', title, message, ttlMs: defaultTtlMs }),
      info: (title: string, message?: string) => setToast({ open: true, kind: 'info', title, message, ttlMs: defaultTtlMs }),
    };
  }, [toast, defaultTtlMs]);

  return api;
}

export function TopRightToast(props: { toast: ToastState | null; onDismiss: () => void }) {
  const { toast, onDismiss } = props;
  if (!toast?.open || typeof document === 'undefined') return null;

  const palette =
    toast.kind === 'success'
      ? {
          border: 'border-emerald-200 dark:border-emerald-900/50',
          bg: 'bg-emerald-50/95 text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-100',
          iconBg: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
          icon: 'badge' as const,
        }
      : toast.kind === 'error'
        ? {
            border: 'border-rose-200 dark:border-rose-900/50',
            bg: 'bg-rose-50/95 text-rose-900 dark:bg-rose-950/60 dark:text-rose-100',
            iconBg: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
            icon: 'helpCircle' as const,
          }
        : {
            border: 'border-slate-200 dark:border-slate-800',
            bg: 'bg-white/95 text-slate-900 dark:bg-slate-900/90 dark:text-slate-100',
            iconBg: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
            icon: 'helpCircle' as const,
          };

  return createPortal(
    <div className="fixed right-6 top-6 z-[9999] w-[360px] max-w-[calc(100vw-48px)]">
      <div
        className={`rounded-2xl border ${palette.border} px-4 py-3 shadow-lg backdrop-blur dark:backdrop-blur ${palette.bg}`}
        role="status"
        aria-live="polite"
      >
        <div className="flex items-start gap-3">
          <span className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${palette.iconBg}`} aria-hidden>
            <NavIcon name={palette.icon} className="h-5 w-5" />
          </span>
          <div className="min-w-0 flex-1">
            <div className="text-sm font-semibold">{toast.title}</div>
            {toast.message ? (
              <div className="mt-0.5 whitespace-pre-line text-xs leading-snug opacity-90">{toast.message}</div>
            ) : null}
          </div>
          <button
            type="button"
            onClick={onDismiss}
            className="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold opacity-70 hover:opacity-100"
            aria-label="Dismiss"
          >
            ✕
          </button>
        </div>
      </div>
    </div>,
    document.body
  );
}

