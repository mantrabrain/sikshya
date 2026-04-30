const styles: Record<string, string> = {
  publish:
    'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-500/30',
  paid: 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-500/30',
  completed:
    'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-500/30',
  success:
    'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-500/30',
  draft: 'bg-slate-100 text-slate-700 ring-slate-500/15 dark:bg-slate-800 dark:text-slate-300',
  pending: 'bg-amber-50 text-amber-900 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-200',
  'on-hold': 'bg-orange-50 text-orange-900 ring-orange-600/20 dark:bg-orange-950/40 dark:text-orange-200',
  future: 'bg-sky-50 text-sky-900 ring-sky-600/20 dark:bg-sky-950/40 dark:text-sky-200',
  private: 'bg-violet-50 text-violet-900 ring-violet-600/20 dark:bg-violet-950/40 dark:text-violet-200',
  trash: 'bg-red-50 text-red-800 ring-red-600/20 dark:bg-red-950/40 dark:text-red-200',
};

const defaultStyle = 'bg-slate-100 text-slate-700 ring-slate-500/10 dark:bg-slate-800 dark:text-slate-300';

export function StatusBadge({ status }: { status: string }) {
  const key = status.toLowerCase();
  const cls = styles[key] ?? defaultStyle;
  return (
    <span
      className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ring-1 ring-inset ${cls}`}
    >
      {status.replace(/-/g, ' ')}
    </span>
  );
}
