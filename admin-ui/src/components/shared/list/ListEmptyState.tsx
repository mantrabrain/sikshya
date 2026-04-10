import type { ReactNode } from 'react';

type Props = {
  title: string;
  description: string;
  /** Primary CTA (e.g. “Add course”). */
  action?: ReactNode;
  /** Tighter padding when embedded in a table cell or small panel. */
  compact?: boolean;
};

/**
 * Reusable empty list / no-results state with illustration (use across list pages).
 */
export function ListEmptyState({ title, description, action, compact }: Props) {
  return (
    <div
      className={`flex flex-col items-center text-center ${compact ? 'px-4 py-10' : 'px-6 py-14'}`}
      role="status"
    >
      <EmptyCatalogIllustration className={compact ? 'h-28 w-28 text-slate-200 dark:text-slate-700/80' : 'h-40 w-40 text-slate-200 dark:text-slate-700/80'} />
      <h3 className="mt-2 text-base font-semibold text-slate-900 dark:text-white">{title}</h3>
      <p className="mt-2 max-w-sm text-sm leading-relaxed text-slate-500 dark:text-slate-400">{description}</p>
      {action ? <div className="mt-6">{action}</div> : null}
    </div>
  );
}

/** Decorative SVG — courses / catalog metaphor. */
function EmptyCatalogIllustration({ className = '' }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 200 200"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden
    >
      <rect x="36" y="48" width="128" height="96" rx="12" className="fill-current opacity-40" />
      <rect x="48" y="64" width="76" height="8" rx="4" className="fill-slate-300 dark:fill-slate-600" />
      <rect x="48" y="80" width="104" height="8" rx="4" className="fill-slate-300 dark:fill-slate-600" />
      <rect x="48" y="96" width="88" height="8" rx="4" className="fill-slate-300 dark:fill-slate-600" />
      <circle cx="100" cy="138" r="28" className="fill-brand-100 dark:fill-brand-950/60" />
      <path
        d="M88 138c0-6.627 5.373-12 12-12s12 5.373 12 12-5.373 12-12 12"
        className="stroke-brand-500 dark:stroke-brand-400"
        strokeWidth="3"
        strokeLinecap="round"
      />
      <circle cx="100" cy="132" r="4" className="fill-brand-500 dark:fill-brand-400" />
      <path
        d="M96 140h8M100 136v8"
        className="stroke-brand-600 dark:stroke-brand-300"
        strokeWidth="2"
        strokeLinecap="round"
      />
    </svg>
  );
}
