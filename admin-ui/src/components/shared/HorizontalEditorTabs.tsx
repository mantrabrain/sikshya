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
 * Horizontal underline tabs for content editors (Content vs Settings).
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
    <nav
      className={`flex flex-wrap gap-x-0.5 border-b border-slate-200 px-6 dark:border-slate-700 ${className}`}
      role="tablist"
      aria-label={ariaLabel}
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
            className={`relative mb-[-1px] border-b-2 px-4 py-3 text-sm font-semibold transition-colors ${
              selected
                ? 'border-brand-600 text-brand-700 dark:border-brand-500 dark:text-brand-300'
                : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'
            }`}
          >
            <span className="inline-flex items-center gap-2">
              {t.icon ? (
                <NavIcon
                  name={t.icon}
                  className={`h-4 w-4 ${selected ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400'}`}
                />
              ) : null}
              <span>{t.label}</span>
            </span>
          </button>
        );
      })}
    </nav>
  );
}
