import type { ReactNode } from 'react';
import { NavIcon } from '../NavIcon';
import { Card } from './Card';

export type Column<T> = {
  id: string;
  header: ReactNode;
  /** Label for the column picker when `header` is not a plain string. */
  columnPickerLabel?: string;
  /**
   * REST `orderby` value; when {@link DataTableProps.sortState} and `onSortColumn` are set,
   * the header becomes a sort control (WordPress list-table style).
   */
  sortKey?: string;
  /** If true, column stays visible and is omitted from the column picker. */
  alwaysVisible?: boolean;
  /** Initial hidden state until toggled (localStorage overrides after first save). */
  defaultHidden?: boolean;
  headerClassName?: string;
  cellClassName?: string;
  render: (row: T) => ReactNode;
};

export type DataTableSortState = {
  orderby: string;
  order: 'asc' | 'desc';
};

type DataTableProps<T> = {
  columns: Column<T>[];
  rows: T[];
  rowKey: (row: T) => string | number;
  /** Plain-text fallback when `emptyContent` is not set. */
  emptyMessage?: string;
  /** Full empty state (illustration, CTA). Takes precedence over `emptyMessage`. */
  emptyContent?: ReactNode;
  /** When false, omit outer {@link Card} (e.g. inside {@link ListPanel}). */
  wrapInCard?: boolean;
  /** Extra classes for each body row (e.g. faded trashed rows on “All”). */
  getRowClassName?: (row: T) => string | undefined;
  /** Server sort; pair with `onSortColumn` and column `sortKey`. */
  sortState?: DataTableSortState;
  /** Toggle or switch sort when a sortable header is activated. */
  onSortColumn?: (orderby: string) => void;
};

/**
 * App-owned data table (not WP_List_Table). Use with {@link TableSkeleton} for loading state.
 */
function renderSortableHeader(
  col: Column<unknown>,
  sortState: DataTableSortState | undefined,
  onSortColumn: ((orderby: string) => void) | undefined
): ReactNode {
  const sk = col.sortKey;
  if (!sk || !sortState || !onSortColumn) {
    return col.header;
  }
  const active = sortState.orderby === sk;
  const orderWord = active ? (sortState.order === 'asc' ? 'ascending' : 'descending') : undefined;
  return (
    <button
      type="button"
      onClick={() => onSortColumn(sk)}
      className="inline-flex max-w-full items-center gap-1 text-left font-semibold uppercase tracking-wide text-slate-500 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 dark:text-slate-400 dark:hover:text-slate-200"
    >
      <span className="min-w-0 truncate">{col.header}</span>
      <NavIcon
        name="chevronDown"
        className={`h-3.5 w-3.5 shrink-0 transition-transform ${active ? (sortState.order === 'asc' ? '-rotate-180' : '') : 'opacity-30'}`}
        aria-hidden
      />
      {active && orderWord ? <span className="sr-only">, sorted {orderWord}</span> : null}
    </button>
  );
}

export function DataTable<T>({
  columns,
  rows,
  rowKey,
  emptyMessage = 'No rows to display.',
  emptyContent,
  wrapInCard = true,
  getRowClassName,
  sortState,
  onSortColumn,
}: DataTableProps<T>) {
  const table = (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
        <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
          <tr>
            {columns.map((col) => (
              <th key={col.id} className={`px-4 py-3 ${col.headerClassName || ''}`} scope="col">
                {renderSortableHeader(col as Column<unknown>, sortState, onSortColumn)}
              </th>
            ))}
          </tr>
        </thead>
        {rows.length === 0 ? (
          <tbody>
            <tr>
              <td colSpan={Math.max(1, columns.length)} className="p-0">
                {emptyContent ?? (
                  <div className="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                    {emptyMessage}
                  </div>
                )}
              </td>
            </tr>
          </tbody>
        ) : (
          <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
            {rows.map((row) => (
              <tr
                key={rowKey(row)}
                className={`group hover:bg-slate-50/80 dark:hover:bg-slate-800/40 ${getRowClassName?.(row) ?? ''}`.trim()}
              >
                {columns.map((col) => (
                  <td key={col.id} className={`px-4 py-3 ${col.cellClassName || ''}`}>
                    {col.render(row)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        )}
      </table>
    </div>
  );

  if (!wrapInCard) {
    return table;
  }

  return <Card>{table}</Card>;
}
