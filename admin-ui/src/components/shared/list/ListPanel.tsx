import type { ReactNode } from 'react';

/**
 * Single white “SaaS” panel wrapping toolbar, filters, and table (Yatra-style).
 */
export function ListPanel({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={`overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 ${className}`}
    >
      {children}
    </div>
  );
}
