import type { ReactNode } from 'react';

type ListPanelProps = {
  children: ReactNode;
  className?: string;
  /**
   * Default `hidden` clips children to rounded corners. Use `visible` when a child
   * (e.g. course picker dropdown) must extend past the panel edge.
   */
  overflow?: 'hidden' | 'visible';
};

/**
 * Single white “SaaS” panel wrapping toolbar, filters, and table (Yatra-style).
 */
export function ListPanel({ children, className = '', overflow = 'hidden' }: ListPanelProps) {
  const ov = overflow === 'visible' ? 'overflow-visible' : 'overflow-hidden';
  return (
    <div
      className={`${ov} rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 ${className}`}
    >
      {children}
    </div>
  );
}
