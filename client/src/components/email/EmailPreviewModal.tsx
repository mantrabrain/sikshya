import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { NavIcon } from '../NavIcon';

type Props = {
  open: boolean;
  subject: string;
  html: string;
  onClose: () => void;
};

/**
 * Email preview dialog — subject strip + scrollable HTML (wrapped by API), footer Close.
 */
export function EmailPreviewModal(props: Props) {
  const { open, subject, html, onClose } = props;
  const panelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) {
      return;
    }
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [open, onClose]);

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

  return createPortal(
    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" role="presentation">
      <button type="button" className="absolute inset-0 bg-slate-900/60 backdrop-blur-[2px] dark:bg-slate-950/70" aria-label="Close" onClick={onClose} />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="sikshya-email-preview-title"
        className="relative flex max-h-[min(92vh,880px)] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-black/5 dark:border-slate-700 dark:bg-slate-900 dark:ring-white/10"
      >
        <div className="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
          <div className="flex min-w-0 items-start gap-3">
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-600 dark:bg-sky-950/60 dark:text-sky-300">
              <NavIcon name="iconPreview" className="h-6 w-6" />
            </span>
            <div className="min-w-0">
              <h2 id="sikshya-email-preview-title" className="text-lg font-semibold text-slate-900 dark:text-white">
                Email Preview
              </h2>
              <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Preview with sample data</p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            aria-label="Close preview"
          >
            <span className="text-xl leading-none">×</span>
          </button>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
          <div className="mb-4">
            <div className="mb-1.5 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
              <span aria-hidden>✉️</span>
              Subject
            </div>
            <p className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-900 dark:border-slate-600 dark:bg-slate-800/50 dark:text-slate-100">
              {subject || '—'}
            </p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-slate-50/80 dark:border-slate-600 dark:bg-slate-950/40">
            <iframe title="Email preview" className="h-[min(480px,55vh)] w-full rounded-xl border-0 bg-white dark:bg-slate-900" srcDoc={html} />
          </div>
        </div>

        <div className="flex shrink-0 justify-end border-t border-slate-100 bg-slate-50/90 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/40">
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
          >
            Close
          </button>
        </div>
      </div>
    </div>,
    document.body
  );
}
