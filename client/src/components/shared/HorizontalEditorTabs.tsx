import { useId } from 'react';
import { NavIcon } from '../NavIcon';

export type HorizontalEditorTabDef = { id: string; label: string; icon?: string };

type Props = {
  tabs: HorizontalEditorTabDef[];
  value: string;
  onChange: (id: string) => void;
  /** Accessible name for the tab list */
  ariaLabel?: string;
  className?: string;
};

/**
 * Segmented pill-style tab strip used by admin hub pages (Content library,
 * Reports, Integrations, …) and by content editors (Quiz Content/Settings).
 *
 * The active tab is rendered as a raised "card" pill so the strip reads
 * clearly as a tab group even when it sits on a neutral page background —
 * which the old underline-only style did not.
 */
export function HorizontalEditorTabs({
  tabs,
  value,
  onChange,
  ariaLabel = 'Editor sections',
  className = '',
}: Props) {
  const uid = useId();

  return (
    <div
      role="tablist"
      aria-label={ariaLabel}
      className={`inline-flex flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800/60 ${className}`}
    >
      {tabs.map((t) => {
        const selected = value === t.id;
        const tabDomId = `${uid}-${t.id}`;
        return (
          <button
            key={t.id}
            type="button"
            role="tab"
            aria-selected={selected}
            id={tabDomId}
            onClick={() => onChange(t.id)}
            className={[
              'relative inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors',
              'focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 focus-visible:ring-offset-1 focus-visible:ring-offset-slate-50 dark:focus-visible:ring-offset-slate-800',
              selected
                ? 'bg-white text-brand-700 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:text-brand-300 dark:ring-slate-700'
                : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-slate-100',
            ].join(' ')}
          >
            {t.icon ? (
              <NavIcon
                name={t.icon}
                className={`h-4 w-4 ${selected ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400 dark:text-slate-500'}`}
              />
            ) : null}
            <span>{t.label}</span>
          </button>
        );
      })}
    </div>
  );
}
