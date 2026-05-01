import { useRef, useState } from 'react';
import { NavIcon } from '../../NavIcon';
import { useClickOutside } from '../../../hooks/useClickOutside';

export type ColumnToggleDef = { id: string; label: string };

const btnClass =
  'inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700';

/**
 * Sikshya-style column visibility popover (checkboxes per toggleable column).
 */
export function ColumnVisibilityMenu({
  columns,
  visibility,
  onChange,
}: {
  columns: ColumnToggleDef[];
  visibility: Record<string, boolean>;
  onChange: (id: string, next: boolean) => void;
}) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  useClickOutside(ref, () => setOpen(false), open);

  if (columns.length === 0) {
    return null;
  }

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        aria-expanded={open}
        aria-haspopup="true"
        className={btnClass}
        onClick={() => setOpen((o) => !o)}
      >
        <NavIcon name="columns" className="h-4 w-4 text-slate-500 dark:text-slate-400" />
        Columns
        <NavIcon name="chevronDown" className="h-3.5 w-3.5 opacity-60" />
      </button>
      {open ? (
        <div className="absolute right-0 z-30 mt-1 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg ring-1 ring-black/5 dark:border-slate-700 dark:bg-slate-900 dark:ring-white/10">
          <p className="px-2 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Visible columns</p>
          <ul className="max-h-64 overflow-auto">
            {columns.map((c) => (
              <li key={c.id}>
                <label className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800">
                  <input
                    type="checkbox"
                    className="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"
                    checked={visibility[c.id] ?? true}
                    onChange={(e) => onChange(c.id, e.target.checked)}
                  />
                  <span className="text-sm text-slate-700 dark:text-slate-200">{c.label}</span>
                </label>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}
