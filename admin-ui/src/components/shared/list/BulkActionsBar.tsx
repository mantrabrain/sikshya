/**
 * Placeholder bulk row — wire actions + selection in a follow-up.
 */
export function BulkActionsBar() {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <label htmlFor="sikshya-bulk" className="sr-only">
        Bulk actions
      </label>
      <select
        id="sikshya-bulk"
        disabled
        className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-slate-600 dark:bg-slate-800/50 dark:text-slate-500"
        defaultValue=""
      >
        <option value="">Bulk actions</option>
      </select>
      <button
        type="button"
        disabled
        className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-400 dark:border-slate-600 dark:bg-slate-800"
      >
        Apply
      </button>
    </div>
  );
}
