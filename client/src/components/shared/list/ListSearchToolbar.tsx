import type { ReactNode } from 'react';
import { NavIcon } from '../../NavIcon';

export type SortFieldOption = { value: string; label: string };

type Props = {
  searchValue: string;
  onSearchChange: (v: string) => void;
  searchPlaceholder: string;
  /** Grey out search (e.g. when another filter makes search unavailable). */
  searchDisabled?: boolean;
  sortField: string;
  sortFieldOptions: SortFieldOption[];
  onSortFieldChange: (v: string) => void;
  sortOrder: 'asc' | 'desc';
  onSortOrderToggle: () => void;
  /** Extra controls (e.g. column visibility) shown after sort controls. */
  trailing?: ReactNode;
};

const selectClass =
  'rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200';

/**
 * Search + sort row for entity list pages (reusable).
 */
export function ListSearchToolbar({
  searchValue,
  onSearchChange,
  searchPlaceholder,
  searchDisabled = false,
  sortField,
  sortFieldOptions,
  onSortFieldChange,
  sortOrder,
  onSortOrderToggle,
  trailing,
}: Props) {
  return (
    <div className="flex flex-col gap-3 border-b border-slate-100 p-4 dark:border-slate-800 lg:flex-row lg:flex-wrap lg:items-center lg:gap-x-4 lg:gap-y-3">
      <div className="relative min-w-0 flex-1 lg:min-w-[12rem]">
        <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
          <NavIcon name="search" className="h-4 w-4" />
        </span>
        <input
          type="search"
          value={searchValue}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder={searchPlaceholder}
          disabled={searchDisabled}
          className="w-full rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800/80 dark:text-white dark:placeholder:text-slate-500 dark:focus:bg-slate-800"
          autoComplete="off"
        />
      </div>
      <div className="flex w-full flex-wrap items-center gap-2 lg:ml-auto lg:w-auto lg:flex-nowrap lg:justify-end">
        <label className="sr-only" htmlFor="sikshya-list-sort-by">
          Sort by
        </label>
        <select
          id="sikshya-list-sort-by"
          value={sortField}
          onChange={(e) => onSortFieldChange(e.target.value)}
          className={selectClass}
        >
          {sortFieldOptions.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
        <button
          type="button"
          onClick={onSortOrderToggle}
          className={`${selectClass} inline-flex items-center gap-1.5`}
          title={sortOrder === 'asc' ? 'Ascending' : 'Descending'}
        >
          {sortOrder === 'asc' ? 'Asc' : 'Desc'}
          <NavIcon name="chevronDown" className="h-3.5 w-3.5 opacity-60" />
        </button>
        {trailing ? (
          <div className="flex w-full flex-wrap items-center gap-2 lg:w-auto lg:flex-nowrap">{trailing}</div>
        ) : null}
      </div>
    </div>
  );
}
