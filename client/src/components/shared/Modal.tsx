import { useEffect, useId, useRef, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

type Props = {
  open: boolean;
  title: string;
  description?: string;
  onClose: () => void;
  children: ReactNode;
  footer?: ReactNode;
  /** Narrow dialog vs comfortable form width */
  size?: 'sm' | 'md' | 'lg' | 'xl';
};

/** Monotonic id so nested modals only consume Escape on the topmost layer. */
let modalStackSeq = 0;
type StackEntry = { id: number; close: () => void };
const modalEscapeStack: StackEntry[] = [];

/**
 * Accessible modal shell (backdrop, Escape on top dialog only, focus first field). Reuse across admin flows.
 * Uses a high z-index so nested pickers and footers stay above `#wpadminbar` (WordPress uses ~99999).
 */
export function Modal({ open, title, description, onClose, children, footer, size = 'md' }: Props) {
  const titleId = useId();
  const descId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const onCloseRef = useRef(onClose);
  onCloseRef.current = onClose;

  useEffect(() => {
    if (!open) {
      return;
    }
    const id = ++modalStackSeq;
    const entry: StackEntry = {
      id,
      close: () => {
        onCloseRef.current();
      },
    };
    modalEscapeStack.push(entry);

    const onKey = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') {
        return;
      }
      const top = modalEscapeStack[modalEscapeStack.length - 1];
      if (!top || top.id !== id) {
        return;
      }
      e.preventDefault();
      top.close();
    };
    document.addEventListener('keydown', onKey);

    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', onKey);
      const idx = modalEscapeStack.findIndex((x) => x.id === id);
      if (idx !== -1) {
        modalEscapeStack.splice(idx, 1);
      }
      document.body.style.overflow = prev;
    };
  }, [open]);

  useEffect(() => {
    if (!open || !panelRef.current) {
      return;
    }
    const focusable = panelRef.current.querySelector<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    focusable?.focus();
  }, [open]);

  if (!open || typeof document === 'undefined') {
    return null;
  }

  const maxW =
    size === 'sm' ? 'max-w-md' : size === 'lg' ? 'max-w-4xl' : size === 'xl' ? 'max-w-6xl' : 'max-w-lg';

  return createPortal(
    <div
      className="fixed inset-0 z-[100090] flex items-center justify-center p-4 sm:p-6"
      role="presentation"
    >
      <button
        type="button"
        className="absolute inset-0 z-0 bg-slate-900/60 backdrop-blur-[2px] dark:bg-slate-950/70"
        aria-label="Close dialog"
        onClick={onClose}
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descId : undefined}
        className={`relative z-10 flex max-h-[min(92dvh,920px)] w-full ${maxW} flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-black/5 dark:border-slate-700 dark:bg-slate-900 dark:ring-white/10`}
      >
        <div className="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
          <div className="min-w-0 flex-1 pr-2">
            <h2 id={titleId} className="text-lg font-semibold text-slate-900 dark:text-white">
              {title}
            </h2>
            <div className="mt-1 min-h-[2.75rem] text-sm leading-snug text-slate-500 dark:text-slate-400">
              {description ? (
                <p id={descId}>{description}</p>
              ) : (
                <span className="block select-none opacity-0" aria-hidden>
                  &nbsp;
                </span>
              )}
            </div>
          </div>
          <button
            type="button"
            className="shrink-0 rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
            aria-label="Close"
            onClick={onClose}
          >
            <span className="block text-xl leading-none" aria-hidden>
              ×
            </span>
          </button>
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">{children}</div>
        {footer ? (
          <div className="shrink-0 border-t border-slate-100 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/40">
            {footer}
          </div>
        ) : null}
      </div>
    </div>,
    document.body
  );
}
