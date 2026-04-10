import { getWpApi, WP_ENDPOINTS } from '../../api';
import { useAsyncData } from '../../hooks/useAsyncData';
import type { WpPost } from '../../types';
import type { Column } from './DataTable';
import { DataTable } from './DataTable';
import { AsyncBoundary } from './AsyncBoundary';
import { DataTableSkeleton } from './Skeleton';

type Props = {
  restBase: string;
  columns: Column<WpPost>[];
  emptyMessage: string;
  /** Column headers (same order as `columns`) for the loading skeleton. */
  skeletonHeaders: string[];
};

/**
 * React-native list for a `wp/v2` post collection (replaces legacy PHP list tables on these screens).
 */
export function PostTypeCollectionBlock({
  restBase,
  columns,
  emptyMessage,
  skeletonHeaders,
}: Props) {
  const { loading, data, error, refetch } = useAsyncData(
    () => getWpApi().get<WpPost[]>(WP_ENDPOINTS.postTypeCollection(restBase)),
    [restBase]
  );

  const rows = Array.isArray(data) ? data : [];

  return (
    <AsyncBoundary
      loading={loading}
      error={error}
      onRetry={refetch}
      skeleton={<DataTableSkeleton headers={skeletonHeaders} />}
    >
      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(r) => r.id}
        emptyMessage={emptyMessage}
      />
    </AsyncBoundary>
  );
}
