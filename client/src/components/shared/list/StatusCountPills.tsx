import type { WpPostCollectionStatus } from '../../../hooks/useWpPostStatusCounts';

export type StatusPillDef = {
  id: WpPostCollectionStatus;
  label: string;
};

type Props = {
  pills: StatusPillDef[];
  value: WpPostCollectionStatus;
  onChange: (id: WpPostCollectionStatus) => void;
  counts: Partial<Record<WpPostCollectionStatus, number>> | null;
  countsLoading?: boolean;
};

/**
 * Segmented status filters with optional totals (WordPress REST `status` param).
 */
export function StatusCountPills({ pills, value, onChange, counts, countsLoading }: Props) {
  return (
    <div className="flex flex-wrap gap-1.5">
      {pills.map((p) => {
        const active = value === p.id;
        const n = counts?.[p.id];
        const countLabel = countsLoading ? '…' : n !== undefined ? String(n) : '—';
        return (
          <button
            key={p.id}
            type="button"
            onClick={() => onChange(p.id)}
            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors sm:text-sm ${
              active
                ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
            }`}
          >
            {p.label}{' '}
            <span className={active ? 'opacity-90' : 'opacity-70'}>({countLabel})</span>
          </button>
        );
      })}
    </div>
  );
}
