type Variant = 'form' | 'table' | 'cards' | 'generic';

export function FeaturePreviewSkeleton(props: { variant?: Variant }) {
  const v = props.variant ?? 'generic';
  if (v === 'table') {
    return (
      <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div className="h-4 w-40 rounded bg-slate-200 dark:bg-slate-700" />
        <div className="space-y-2">
          {[1, 2, 3, 4, 5].map((i) => (
            <div key={i} className="flex gap-3">
              <div className="h-3 flex-1 rounded bg-slate-100 dark:bg-slate-800" />
              <div className="h-3 w-16 rounded bg-slate-100 dark:bg-slate-800" />
              <div className="h-3 w-20 rounded bg-slate-100 dark:bg-slate-800" />
            </div>
          ))}
        </div>
      </div>
    );
  }
  if (v === 'form') {
    return (
      <div className="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div className="h-4 w-48 rounded bg-slate-200 dark:bg-slate-700" />
        <div className="mt-6 grid gap-4 sm:grid-cols-3">
          <div className="h-20 rounded-lg bg-slate-100 dark:bg-slate-800" />
          <div className="h-20 rounded-lg bg-slate-100 dark:bg-slate-800" />
          <div className="h-20 rounded-lg bg-slate-100 dark:bg-slate-800" />
        </div>
        <div className="mt-6 h-10 w-32 rounded-xl bg-slate-200 dark:bg-slate-700" />
      </div>
    );
  }
  if (v === 'cards') {
    return (
      <div className="space-y-4">
        <div className="h-4 w-56 rounded bg-slate-200 dark:bg-slate-700" />
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="h-28 rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/80" />
          <div className="h-28 rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/80" />
        </div>
        <div className="h-36 rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" />
      </div>
    );
  }
  return (
    <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
      <div className="h-5 w-2/3 max-w-md rounded bg-slate-200 dark:bg-slate-700" />
      <div className="h-3 w-full max-w-xl rounded bg-slate-100 dark:bg-slate-800" />
      <div className="h-3 w-5/6 max-w-lg rounded bg-slate-100 dark:bg-slate-800" />
      <div className="mt-4 h-24 rounded-lg bg-slate-50 dark:bg-slate-800/80" />
    </div>
  );
}
