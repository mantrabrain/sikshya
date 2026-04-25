import { Card } from './Card';

const pulse = 'animate-pulse rounded-md bg-slate-200/80';

export function SkeletonLine({ className = '' }: { className?: string }) {
  return <div className={`${pulse} h-4 ${className}`} aria-hidden />;
}

export function SkeletonCircle({ className = 'h-10 w-10' }: { className?: string }) {
  return <div className={`${pulse} ${className}`} aria-hidden />;
}

/** Generic card-sized placeholder. */
export function SkeletonCard({ rows = 3 }: { rows?: number }) {
  return (
    <div className="space-y-3 p-2" aria-busy="true" aria-label="Loading">
      {Array.from({ length: rows }).map((_, i) => (
        <SkeletonLine key={i} className={i === 0 ? 'w-2/3' : 'w-full'} />
      ))}
    </div>
  );
}

/** Stat tiles (dashboard). */
export function SkeletonStatGrid({ count = 4 }: { count?: number }) {
  return (
    <div
      className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
      aria-busy="true"
      aria-label="Loading statistics"
    >
      {Array.from({ length: count }).map((_, i) => (
        <div
          key={i}
          className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
          <SkeletonLine className="h-8 w-16" />
          <SkeletonLine className="mt-3 h-3 w-32" />
        </div>
      ))}
    </div>
  );
}

type TableSkeletonProps = {
  columns: number;
  rows?: number;
};

/** Table body placeholder — use inside a table layout matching {@link DataTable}. */
export function TableSkeleton({ columns, rows = 8 }: TableSkeletonProps) {
  return (
    <tbody className="divide-y divide-slate-100" aria-busy="true" aria-label="Loading table">
      {Array.from({ length: rows }).map((_, ri) => (
        <tr key={ri}>
          {Array.from({ length: columns }).map((_, ci) => (
            <td key={ci} className="px-4 py-3">
              <SkeletonLine className={ci === 0 ? 'h-4 w-3/4 max-w-xs' : 'h-4 w-20'} />
            </td>
          ))}
        </tr>
      ))}
    </tbody>
  );
}

/** Course builder: left step rail + main form panels. */
export function CourseBuilderSkeleton() {
  return (
    <div className="flex flex-col gap-6 lg:flex-row" aria-busy="true" aria-label="Loading course builder">
      <div className="shrink-0 space-y-2 lg:w-72">
        <SkeletonLine className="h-3 w-24" />
        {Array.from({ length: 5 }).map((_, i) => (
          <div key={i} className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <div className="flex gap-3">
              <SkeletonCircle className="h-7 w-7 shrink-0 rounded-full" />
              <div className="min-w-0 flex-1 space-y-2">
                <SkeletonLine className="h-4 w-3/4" />
                <SkeletonLine className="h-3 w-full" />
              </div>
            </div>
          </div>
        ))}
      </div>
      <div className="min-w-0 flex-1 space-y-6">
        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <SkeletonLine className="h-3 w-40" />
          <SkeletonLine className="mt-4 h-7 w-2/3" />
          <SkeletonLine className="mt-2 h-4 w-full max-w-md" />
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <SkeletonCard rows={5} />
        </div>
      </div>
    </div>
  );
}

/** Full table chrome matching DataTable layout. */
export function DataTableSkeleton({ headers, rows = 8 }: { headers: string[]; rows?: number }) {
  const n = headers.length;
  return (
    <Card>
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              {headers.map((h) => (
                <th key={h} className="px-4 py-3" scope="col">
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <TableSkeleton columns={n} rows={rows} />
        </table>
      </div>
    </Card>
  );
}
