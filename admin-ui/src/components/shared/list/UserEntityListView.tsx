import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { ApiErrorPanel } from '../ApiErrorPanel';
import type { Column } from '../DataTable';
import { DataTable } from '../DataTable';
import { DataTableSkeleton } from '../Skeleton';
import { useDebouncedValue } from '../../../hooks/useDebouncedValue';
import { useWpUserCollection } from '../../../hooks/useWpUserCollection';
import { columnVisibilityStorageKey, loadColumnVisibility, saveColumnVisibility } from '../../../lib/columnVisibility';
import type { WpRestUser } from '../../../types';
import { BulkActionsBar } from './BulkActionsBar';
import { ColumnVisibilityMenu } from './ColumnVisibilityMenu';
import { ListEmptyState } from './ListEmptyState';
import { ListPanel } from './ListPanel';
import { ListSearchToolbar, type SortFieldOption } from './ListSearchToolbar';
import { DEFAULT_LIST_PER_PAGE, ListPaginationBar } from './ListPaginationBar';

type Props = {
  roleSlug: string;
  /** Shown on the toolbar row (e.g. “WordPress users with the Student role”). */
  contextHint: string;
  searchPlaceholder: string;
  sortFieldOptions: SortFieldOption[];
  defaultSortField: string;
  columns: Column<WpRestUser>[];
  emptyMessage: string;
  skeletonHeaders?: string[];
  columnPickerStorageKey?: string;
  emptyStateTitle?: string;
  emptyStateDescription?: string;
  emptyStateAction?: ReactNode;
};

/**
 * Same chrome as {@link EntityListView} but for `/wp/v2/users` (no post status pills).
 */
export function UserEntityListView({
  roleSlug,
  contextHint,
  searchPlaceholder,
  sortFieldOptions,
  defaultSortField,
  columns,
  emptyMessage,
  skeletonHeaders: skeletonHeadersProp,
  columnPickerStorageKey,
  emptyStateTitle,
  emptyStateDescription,
  emptyStateAction,
}: Props) {
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 320);
  const [page, setPage] = useState(1);
  const [orderby, setOrderby] = useState(defaultSortField);
  const [order, setOrder] = useState<'asc' | 'desc'>('asc');

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, orderby, order, roleSlug]);

  const pickable = useMemo(() => columns.filter((c) => !c.alwaysVisible), [columns]);
  const [colVis, setColVis] = useState<Record<string, boolean>>({});

  useEffect(() => {
    if (!columnPickerStorageKey || pickable.length === 0) {
      setColVis({});
      return;
    }
    const key = columnVisibilityStorageKey(columnPickerStorageKey);
    setColVis(
      loadColumnVisibility(
        key,
        pickable.map((c) => ({ id: c.id, defaultHidden: c.defaultHidden }))
      )
    );
  }, [columnPickerStorageKey, pickable]);

  const onColumnToggle = useCallback(
    (id: string, next: boolean) => {
      if (!columnPickerStorageKey) {
        return;
      }
      const key = columnVisibilityStorageKey(columnPickerStorageKey);
      setColVis((prev) => {
        const merged = { ...prev, [id]: next };
        saveColumnVisibility(key, merged);
        return merged;
      });
    },
    [columnPickerStorageKey]
  );

  const pickerVisibility = useMemo(() => {
    const o: Record<string, boolean> = {};
    for (const c of pickable) {
      const v = colVis[c.id];
      o[c.id] = v === undefined ? !c.defaultHidden : v;
    }
    return o;
  }, [pickable, colVis]);

  const visibleColumns = useMemo(() => {
    if (!columnPickerStorageKey) {
      return columns;
    }
    return columns.filter((c) => {
      if (c.alwaysVisible) {
        return true;
      }
      const v = colVis[c.id];
      if (v === undefined) {
        return !c.defaultHidden;
      }
      return v;
    });
  }, [columns, columnPickerStorageKey, colVis]);

  const skeletonHeaders = useMemo(() => {
    if (skeletonHeadersProp?.length) {
      return skeletonHeadersProp;
    }
    return columns.map((c) => c.header || '\u00a0');
  }, [columns, skeletonHeadersProp]);

  const listQuery = useWpUserCollection({
    roleSlug,
    search: debouncedSearch,
    orderby,
    order,
    page,
    perPage: DEFAULT_LIST_PER_PAGE,
  });

  const rows = listQuery.data?.data ?? [];

  const totalLine = useMemo(() => {
    const t = listQuery.data?.total;
    if (t == null) {
      return null;
    }
    return `Showing ${rows.length} of ${t}`;
  }, [listQuery.data?.total, rows.length]);

  const onSortOrderToggle = () => setOrder((o) => (o === 'asc' ? 'desc' : 'asc'));

  const onSortColumn = useCallback(
    (key: string) => {
      if (key === orderby) {
        setOrder((o) => (o === 'asc' ? 'desc' : 'asc'));
      } else {
        setOrderby(key);
        setOrder('asc');
      }
    },
    [orderby]
  );

  const columnPicker =
    columnPickerStorageKey && pickable.length > 0 ? (
      <ColumnVisibilityMenu columns={pickable.map((c) => ({ id: c.id, label: c.header || 'Column' }))} visibility={pickerVisibility} onChange={onColumnToggle} />
    ) : null;

  const emptyContent = (
    <ListEmptyState
      title={emptyStateTitle ?? 'No results'}
      description={emptyStateDescription ?? emptyMessage}
      action={emptyStateAction}
    />
  );

  return (
    <ListPanel>
      <ListSearchToolbar
        searchValue={search}
        onSearchChange={setSearch}
        searchPlaceholder={searchPlaceholder}
        sortField={orderby}
        sortFieldOptions={sortFieldOptions}
        onSortFieldChange={setOrderby}
        sortOrder={order}
        onSortOrderToggle={onSortOrderToggle}
        trailing={columnPicker}
      />

      <div className="flex flex-col gap-4 border-b border-slate-100 px-4 py-3 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
        <BulkActionsBar />
        <p className="text-xs text-slate-500 dark:text-slate-400">{contextHint}</p>
      </div>

      {totalLine ? (
        <div className="border-b border-slate-100 px-4 py-2 text-xs font-medium text-slate-500 dark:border-slate-800 dark:text-slate-400">
          {totalLine}
        </div>
      ) : null}

      {listQuery.error ? (
        <div className="p-4">
          <ApiErrorPanel error={listQuery.error} onRetry={listQuery.refetch} title="Could not load users" />
        </div>
      ) : listQuery.loading ? (
        <DataTableSkeleton headers={skeletonHeaders} rows={8} />
      ) : (
        <>
          <ListPaginationBar
            placement="top"
            page={page}
            total={listQuery.data?.total ?? null}
            totalPages={listQuery.data?.totalPages ?? null}
            perPage={DEFAULT_LIST_PER_PAGE}
            onPageChange={setPage}
            disabled={listQuery.loading}
          />
          <DataTable
            columns={visibleColumns}
            rows={rows}
            rowKey={(r) => r.id}
            emptyContent={emptyContent}
            wrapInCard={false}
            sortState={{ orderby, order }}
            onSortColumn={onSortColumn}
          />
          <ListPaginationBar
            placement="bottom"
            page={page}
            total={listQuery.data?.total ?? null}
            totalPages={listQuery.data?.totalPages ?? null}
            perPage={DEFAULT_LIST_PER_PAGE}
            onPageChange={setPage}
            disabled={listQuery.loading}
          />
        </>
      )}
    </ListPanel>
  );
}
