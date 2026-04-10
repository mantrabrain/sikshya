export const DEFAULT_LIST_PER_PAGE = 20;

type Props = {
  page: number;
  totalPages: number | null;
  total: number | null;
  perPage: number;
  onPageChange: (nextPage: number) => void;
  disabled?: boolean;
};

/**
 * Prev/next pagination for WP REST collections (`X-WP-Total` / `X-WP-TotalPages`).
 */
export function ListPaginationBar({ page, totalPages, total, perPage, onPageChange, disabled }: Props) {
  if (total == null || total < 1) {
    return null;
  }

  const pages = Math.max(1, totalPages ?? Math.ceil(total / perPage));
  const from = (page - 1) * perPage + 1;
  const to = Math.min(page * perPage, total);

  return (
    <div className="flex flex-col gap-2 border-t border-slate-100 px-4 py-3 text-sm dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-slate-600 dark:text-slate-400">
        <span className="font-medium text-slate-800 dark:text-slate-200">
          {from}–{to}
        </span>
        {' of '}
        <span className="font-medium text-slate-800 dark:text-slate-200">{total}</span>
        {pages > 1 ? (
          <>
            {' · '}
            Page <span className="font-medium">{page}</span> of {pages}
          </>
        ) : null}
      </p>
      {pages > 1 ? (
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            disabled={disabled || page <= 1}
            onClick={() => onPageChange(page - 1)}
            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
          >
            Previous
          </button>
          <button
            type="button"
            disabled={disabled || page >= pages}
            onClick={() => onPageChange(page + 1)}
            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
          >
            Next
          </button>
        </div>
      ) : null}
    </div>
  );
}
