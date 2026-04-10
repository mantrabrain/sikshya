import type { ReactNode } from 'react';
import { Card } from './Card';

export type Column<T> = {
  id: string;
  header: string;
  /** If true, column stays visible and is omitted from the column picker. */
  alwaysVisible?: boolean;
  /** Initial hidden state until toggled (localStorage overrides after first save). */
  defaultHidden?: boolean;
  headerClassName?: string;
  cellClassName?: string;
  render: (row: T) => ReactNode;
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
};

/**
 * App-owned data table (not WP_List_Table). Use with {@link TableSkeleton} for loading state.
 */
export function DataTable<T>({
  columns,
  rows,
  rowKey,
  emptyMessage = 'No rows to display.',
  emptyContent,
  wrapInCard = true,
}: DataTableProps<T>) {
  const table = (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
        <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
          <tr>
            {columns.map((col) => (
              <th key={col.id} className={`px-4 py-3 ${col.headerClassName || ''}`} scope="col">
                {col.header}
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
              <tr key={rowKey(row)} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
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
