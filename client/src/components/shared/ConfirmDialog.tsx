import { useEffect, useId, useRef } from 'react';
import type { ReactNode } from 'react';

export type ConfirmDialogProps = {
  open: boolean;
  /** `confirm` shows primary + cancel; `alert` shows a single dismiss button. */
  type?: 'confirm' | 'alert';
  title: string;
  children: ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  dismissLabel?: string;
  variant?: 'default' | 'danger';
  busy?: boolean;
  onClose: () => void;
  onConfirm?: () => void | Promise<void>;
};

/**
 * Accessible modal for confirmations and alerts (replaces `window.confirm` / `window.alert`).
 */
export function ConfirmDialog({
  open,
  type = 'confirm',
  title,
  children,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  dismissLabel = 'OK',
  variant = 'default',
  busy = false,
  onClose,
  onConfirm,
}: ConfirmDialogProps) {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const previouslyFocused = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!open) {
      return;
    }
    previouslyFocused.current = document.activeElement as HTMLElement | null;
    const t = window.setTimeout(() => {
      panelRef.current?.querySelector<HTMLElement>('button:not([disabled])')?.focus();
    }, 0);
    return () => window.clearTimeout(t);
  }, [open]);

  useEffect(() => {
    if (!open) {
      return;
    }
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !busy) {
        e.preventDefault();
        onClose();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, busy, onClose]);

  useEffect(() => {
    if (!open && previouslyFocused.current?.focus) {
      previouslyFocused.current.focus();
    }
  }, [open]);

  if (!open) {
    return null;
  }

  const isAlert = type === 'alert';
  const danger = variant === 'danger';
  const primaryBtn =
    danger && !isAlert
      ? 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500/30 dark:bg-red-600 dark:hover:bg-red-500'
      : 'bg-brand-600 text-white hover:bg-brand-700 focus:ring-brand-500/30 dark:bg-brand-500 dark:hover:bg-brand-400';

  return (
    <div
      className="fixed inset-0 z-[200] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-[2px] sm:items-center"
      role="presentation"
    >
      <button
        type="button"
        className="absolute inset-0 z-0 cursor-default"
        aria-label="Close dialog"
        disabled={busy}
        onClick={onClose}
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className="relative z-10 w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
      >
        <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
          <h2 id={titleId} className="text-base font-semibold text-slate-900 dark:text-white">
            {title}
          </h2>
        </div>
        <div className="px-5 py-4 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{children}</div>
        <div className="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
          {isAlert ? (
            <button
              type="button"
              disabled={busy}
              onClick={onClose}
              className={`rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 ${primaryBtn}`}
            >
              {dismissLabel}
            </button>
          ) : (
            <>
              <button
                type="button"
                disabled={busy}
                onClick={onClose}
                className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
              >
                {cancelLabel}
              </button>
              <button
                type="button"
                disabled={busy}
                onClick={() => void onConfirm?.()}
                className={`rounded-xl px-4 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 disabled:opacity-50 ${primaryBtn}`}
              >
                {busy ? 'Please wait…' : confirmLabel}
              </button>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
