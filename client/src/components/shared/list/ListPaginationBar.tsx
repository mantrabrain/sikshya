export const DEFAULT_LIST_PER_PAGE = 20;

export type ListPaginationBarProps = {
  page: number;
  totalPages: number | null;
  total: number | null;
  perPage: number;
  onPageChange: (nextPage: number) => void;
  disabled?: boolean;
  /** `top`: border below bar (above table). `bottom`: border above bar (below table). */
  placement?: 'top' | 'bottom';
};

/** Page numbers with ellipsis (e.g. 1 … 48 49 50 … 102). */
function buildPageNumbers(current: number, total: number, delta = 2): Array<number | 'ellipsis'> {
  if (total <= 1) {
    return [1];
  }
  const set = new Set<number>();
  set.add(1);
  set.add(total);
  for (let i = current - delta; i <= current + delta; i++) {
    if (i >= 1 && i <= total) {
      set.add(i);
    }
  }
  const sorted = [...set].sort((a, b) => a - b);
  const out: Array<number | 'ellipsis'> = [];
  for (let i = 0; i < sorted.length; i++) {
    const n = sorted[i];
    const prev = sorted[i - 1];
    if (i > 0 && n - prev > 1) {
      out.push('ellipsis');
    }
    out.push(n);
  }
  return out;
}

const pageBtnBase =
  'min-w-[2.25rem] shrink-0 rounded-lg border px-2.5 py-1.5 text-sm font-medium shadow-sm transition disabled:cursor-not-allowed disabled:opacity-40';
const pageBtnIdle =
  'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700';
const pageBtnActive = 'border-brand-500 bg-brand-50 text-brand-800 dark:border-brand-500 dark:bg-brand-950/40 dark:text-brand-200';

/**
 * Numbered pagination for WP REST collections. One row: range summary on the left,
 * First / Prev / page numbers / Next / Last on the right.
 */
export function ListPaginationBar({
  page,
  totalPages,
  total,
  perPage,
  onPageChange,
  disabled,
  placement = 'bottom',
}: ListPaginationBarProps) {
  if (total == null || total < 1) {
    return null;
  }

  const pages = Math.max(1, totalPages ?? Math.ceil(total / perPage));
  const from = (page - 1) * perPage + 1;
  const to = Math.min(page * perPage, total);
  const nums = buildPageNumbers(page, pages);

  const borderClass =
    placement === 'top'
      ? 'border-b border-slate-100 dark:border-slate-800'
      : 'border-t border-slate-100 dark:border-slate-800';

  return (
    <div
      className={`flex flex-nowrap items-center gap-3 px-4 py-2.5 text-sm ${borderClass}`}
    >
      <p className="shrink-0 text-slate-600 dark:text-slate-400">
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
        <div className="flex min-w-0 flex-1 justify-end overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:thin] [&::-webkit-scrollbar]:h-1 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-300 dark:[&::-webkit-scrollbar-thumb]:bg-slate-600">
          <div className="flex flex-nowrap items-center gap-1">
            <button
              type="button"
              disabled={disabled || page <= 1}
              onClick={() => onPageChange(1)}
              className={`${pageBtnBase} ${pageBtnIdle}`}
              title="First page"
            >
              First
            </button>
            <button
              type="button"
              disabled={disabled || page <= 1}
              onClick={() => onPageChange(page - 1)}
              className={`${pageBtnBase} ${pageBtnIdle}`}
            >
              Previous
            </button>
            {nums.map((item, idx) =>
              item === 'ellipsis' ? (
                <span
                  key={`e-${idx}`}
                  className="shrink-0 px-1.5 text-slate-400 select-none dark:text-slate-500"
                  aria-hidden
                >
                  …
                </span>
              ) : (
                <button
                  key={item}
                  type="button"
                  disabled={disabled}
                  onClick={() => onPageChange(item)}
                  className={`${pageBtnBase} ${item === page ? pageBtnActive : pageBtnIdle}`}
                  aria-current={item === page ? 'page' : undefined}
                >
                  {item}
                </button>
              )
            )}
            <button
              type="button"
              disabled={disabled || page >= pages}
              onClick={() => onPageChange(page + 1)}
              className={`${pageBtnBase} ${pageBtnIdle}`}
            >
              Next
            </button>
            <button
              type="button"
              disabled={disabled || page >= pages}
              onClick={() => onPageChange(pages)}
              className={`${pageBtnBase} ${pageBtnIdle}`}
              title="Last page"
            >
              Last
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
