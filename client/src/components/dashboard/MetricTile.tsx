import type { ReactNode } from 'react';

type Accent = 'brand' | 'emerald' | 'violet' | 'amber' | 'sky' | 'slate';

const accentRing: Record<Accent, string> = {
  brand: 'bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400',
  emerald: 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
  violet: 'bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400',
  amber: 'bg-amber-500/10 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
  sky: 'bg-sky-500/10 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
  slate: 'bg-slate-500/10 text-slate-600 dark:bg-slate-500/20 dark:text-slate-400',
};

/**
 * KPI tile for admin overview grids (dashboard, future analytics).
 */
export function MetricTile({
  icon,
  label,
  value,
  hint,
  accent = 'brand',
}: {
  icon: ReactNode;
  label: string;
  value: string | number;
  hint?: string;
  accent?: Accent;
}) {
  return (
    <div className="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
      <div className="flex items-start justify-between gap-3">
        <div
          className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${accentRing[accent]}`}
          aria-hidden
        >
          {icon}
        </div>
      </div>
      <div className="mt-4">
        <p className="text-2xl font-semibold tracking-tight text-slate-900 tabular-nums dark:text-white">
          {value}
        </p>
        <p className="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">{label}</p>
        {hint ? <p className="mt-2 text-xs leading-relaxed text-slate-400 dark:text-slate-500">{hint}</p> : null}
      </div>
    </div>
  );
}
