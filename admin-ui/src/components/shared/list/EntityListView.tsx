import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { getWpApi } from '../../../api';
import { ApiErrorPanel } from '../ApiErrorPanel';
import type { Column } from '../DataTable';
import { DataTable } from '../DataTable';
import { DataTableSkeleton } from '../Skeleton';
import { useSikshyaDialog } from '../SikshyaDialogContext';
import { useDebouncedValue } from '../../../hooks/useDebouncedValue';
import { useWpPostCollection, type WpPostCollectionQuery } from '../../../hooks/useWpPostCollection';
import {
  useWpPostStatusCounts,
  type WpPostCollectionStatus,
} from '../../../hooks/useWpPostStatusCounts';
import { columnVisibilityStorageKey, loadColumnVisibility, saveColumnVisibility } from '../../../lib/columnVisibility';
import type { WpPost } from '../../../types';
import { BulkActionsBar } from './BulkActionsBar';
import { ColumnVisibilityMenu } from './ColumnVisibilityMenu';
import { ListEmptyState } from './ListEmptyState';
import { ListPanel } from './ListPanel';
import { ListSearchToolbar, type SortFieldOption } from './ListSearchToolbar';
import { StatusCountPills, type StatusPillDef } from './StatusCountPills';

const DEFAULT_PILLS: StatusPillDef[] = [
  { id: 'any', label: 'All' },
  { id: 'publish', label: 'Published' },
  { id: 'draft', label: 'Draft' },
  { id: 'pending', label: 'Pending' },
  { id: 'future', label: 'Scheduled' },
  { id: 'private', label: 'Private' },
  { id: 'trash', label: 'Trash' },
];

type Props = {
  restBase: string;
  searchPlaceholder: string;
  sortFieldOptions: SortFieldOption[];
  defaultSortField: string;
  /** Optional override for status tabs (order + labels). */
  statusPills?: StatusPillDef[];
  columns: Column<WpPost>[];
  emptyMessage: string;
  /**
   * Optional header labels for the loading skeleton (same order as `columns`).
   * Defaults to each column’s `header` (empty headers become a space).
   */
  skeletonHeaders?: string[];
  /** Namespace for column picker + `localStorage`. Omit to hide the picker. */
  columnPickerStorageKey?: string;
  /** Merged into the WP collection query (e.g. `{ embed: '1' }` for `_embed`). */
  collectionQueryExtras?: Partial<WpPostCollectionQuery>;
  /** Sample catalog rows when the API returns none (e.g. dev or `useEntityListMock` in config). */
  useMockPlaceholder?: boolean;
  /** Rows to show when mock mode is on (from {@link getMockRowsForRestBase} or custom). */
  mockPlaceholderRows?: WpPost[];
  /** Optional line under the toolbar when showing mocks. */
  mockBannerMessage?: string;
  emptyStateTitle?: string;
  emptyStateDescription?: string;
  emptyStateAction?: ReactNode;
  /** Row checkboxes + bulk move to trash / permanent delete (trash tab). Default true. */
  bulkDeleteEnabled?: boolean;
};

/**
 * Reusable WP post-type list: search, sort, status pills with counts, table.
 * Use on Courses, Lessons, Quizzes, etc.
 */
export function EntityListView({
  restBase,
  searchPlaceholder,
  sortFieldOptions,
  defaultSortField,
  statusPills = DEFAULT_PILLS,
  columns,
  emptyMessage,
  skeletonHeaders: skeletonHeadersProp,
  columnPickerStorageKey,
  collectionQueryExtras,
  useMockPlaceholder = false,
  mockPlaceholderRows = [],
  mockBannerMessage = 'Sample data preview — no items returned yet.',
  emptyStateTitle,
  emptyStateDescription,
  emptyStateAction,
  bulkDeleteEnabled = true,
}: Props) {
  const { confirm } = useSikshyaDialog();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 320);
  const [status, setStatus] = useState<WpPostCollectionStatus>('any');
  const [orderby, setOrderby] = useState(defaultSortField);
  const [order, setOrder] = useState<'asc' | 'desc'>('asc');
  const [selectedIds, setSelectedIds] = useState<Set<number>>(() => new Set());
  const [bulkActionValue, setBulkActionValue] = useState('');
  const [bulkBusy, setBulkBusy] = useState(false);
  const [bulkError, setBulkError] = useState<unknown>(null);
  const headerSelectRef = useRef<HTMLInputElement>(null);

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

  const countsQuery = useWpPostStatusCounts(restBase);
  const listQuery = useWpPostCollection(restBase, {
    search: debouncedSearch,
    status,
    orderby,
    order,
    perPage: 50,
    ...collectionQueryExtras,
  });

  const apiRows = listQuery.data?.data ?? [];
  const showMockRows =
    useMockPlaceholder &&
    mockPlaceholderRows.length > 0 &&
    !listQuery.loading &&
    !listQuery.error &&
    apiRows.length === 0 &&
    debouncedSearch.trim() === '' &&
    status === 'any';

  const rows = showMockRows ? mockPlaceholderRows : apiRows;
  const includeBulkCol = bulkDeleteEnabled && !showMockRows;
  const trashMode = status === 'trash';

  useEffect(() => {
    setSelectedIds(new Set());
    setBulkActionValue('');
    setBulkError(null);
  }, [debouncedSearch, status, orderby, order, restBase]);

  useEffect(() => {
    setBulkActionValue('');
  }, [trashMode]);

  const toggleSelectAll = useCallback(() => {
    setSelectedIds((prev) => {
      const ids = rows.map((r) => r.id);
      const allSel = ids.length > 0 && ids.every((id) => prev.has(id));
      const next = new Set(prev);
      if (allSel) {
        ids.forEach((id) => next.delete(id));
      } else {
        ids.forEach((id) => next.add(id));
      }
      return next;
    });
  }, [rows]);

  const toggleOne = useCallback((id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  const visibleRowIds = useMemo(() => rows.map((r) => r.id), [rows]);
  const checkedOnPage = useMemo(
    () => visibleRowIds.filter((id) => selectedIds.has(id)).length,
    [visibleRowIds, selectedIds]
  );
  const allVisibleSelected = visibleRowIds.length > 0 && checkedOnPage === visibleRowIds.length;

  useEffect(() => {
    const el = headerSelectRef.current;
    if (!el || !includeBulkCol) {
      return;
    }
    el.indeterminate = checkedOnPage > 0 && checkedOnPage < visibleRowIds.length;
  }, [checkedOnPage, visibleRowIds.length, includeBulkCol]);

  const selectionColumn: Column<WpPost> = useMemo(
    () => ({
      id: '_bulk_select',
      header: (
        <input
          ref={headerSelectRef}
          type="checkbox"
          className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-700"
          aria-label="Select all on this page"
          checked={allVisibleSelected}
          onChange={toggleSelectAll}
        />
      ),
      alwaysVisible: true,
      headerClassName: 'w-12',
      cellClassName: 'w-12',
      render: (r) => (
        <input
          type="checkbox"
          className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-700"
          aria-label={`Select row ${r.id}`}
          checked={selectedIds.has(r.id)}
          onChange={() => toggleOne(r.id)}
        />
      ),
    }),
    [allVisibleSelected, selectedIds, toggleOne, toggleSelectAll]
  );

  const displayColumns = useMemo(
    () => (includeBulkCol ? [selectionColumn, ...visibleColumns] : visibleColumns),
    [includeBulkCol, selectionColumn, visibleColumns]
  );

  const tableSkeletonHeaders = useMemo(() => {
    const base = skeletonHeadersProp?.length
      ? skeletonHeadersProp
      : columns.map((c) => c.header || '\u00a0');
    return includeBulkCol ? ['', ...base] : base;
  }, [columns, skeletonHeadersProp, includeBulkCol]);

  const onBulkApply = useCallback(async () => {
    if (!includeBulkCol || selectedIds.size === 0) {
      return;
    }
    if (trashMode && bulkActionValue !== 'delete_permanent') {
      return;
    }
    if (!trashMode && bulkActionValue !== 'move_trash') {
      return;
    }

    const n = selectedIds.size;
    const ok = await confirm({
      title: trashMode ? 'Delete permanently?' : 'Move to trash?',
      message: trashMode
        ? `Permanently delete ${n} item(s)? This cannot be undone.`
        : `Move ${n} item(s) to the trash? You can restore them from the Trash tab.`,
      variant: 'danger',
      confirmLabel: trashMode ? 'Delete permanently' : 'Move to trash',
    });
    if (!ok) {
      return;
    }

    setBulkBusy(true);
    setBulkError(null);
    const api = getWpApi();
    const ids = [...selectedIds];
    try {
      for (const id of ids) {
        const path = trashMode ? `/${restBase}/${id}?force=true` : `/${restBase}/${id}`;
        await api.delete(path);
      }
      setSelectedIds(new Set());
      setBulkActionValue('');
      listQuery.refetch();
      countsQuery.refetch();
    } catch (e) {
      setBulkError(e);
    } finally {
      setBulkBusy(false);
    }
  }, [
    includeBulkCol,
    selectedIds,
    trashMode,
    bulkActionValue,
    confirm,
    restBase,
    listQuery,
    countsQuery,
  ]);

  const totalLine = useMemo(() => {
    if (showMockRows) {
      return `Showing sample rows (${rows.length})`;
    }
    const t = listQuery.data?.total;
    if (t == null) {
      return null;
    }
    return `Showing ${rows.length} of ${t}`;
  }, [listQuery.data?.total, rows.length, showMockRows]);

  const onSortOrderToggle = () => setOrder((o) => (o === 'asc' ? 'desc' : 'asc'));

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
        <BulkActionsBar
          disabled={!includeBulkCol}
          selectedCount={selectedIds.size}
          value={bulkActionValue}
          onChange={setBulkActionValue}
          onApply={() => void onBulkApply()}
          applyBusy={bulkBusy}
          trashMode={trashMode}
        />
        <StatusCountPills
          pills={statusPills}
          value={status}
          onChange={setStatus}
          counts={countsQuery.data}
          countsLoading={countsQuery.loading}
        />
      </div>

      {bulkError ? (
        <div className="border-b border-red-100 px-4 py-3 dark:border-red-900/40">
          <ApiErrorPanel error={bulkError} title="Bulk action failed" onRetry={() => setBulkError(null)} />
        </div>
      ) : null}

      {showMockRows ? (
        <div className="border-b border-amber-200/80 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
          {mockBannerMessage}
        </div>
      ) : null}

      {totalLine ? (
        <div className="border-b border-slate-100 px-4 py-2 text-xs font-medium text-slate-500 dark:border-slate-800 dark:text-slate-400">
          {totalLine}
        </div>
      ) : null}

      {listQuery.error ? (
        <div className="p-4">
          <ApiErrorPanel error={listQuery.error} onRetry={listQuery.refetch} title="Could not load list" />
        </div>
      ) : listQuery.loading ? (
        <DataTableSkeleton headers={tableSkeletonHeaders} rows={8} />
      ) : (
        <DataTable
          columns={displayColumns}
          rows={rows}
          rowKey={(r) => r.id}
          emptyContent={emptyContent}
          wrapInCard={false}
        />
      )}
    </ListPanel>
  );
}
