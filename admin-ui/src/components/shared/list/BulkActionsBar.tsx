export type BulkActionsBarProps = {
  /** When true (e.g. sample data), bulk UI is disabled. */
  disabled?: boolean;
  selectedCount: number;
  value: string;
  onChange: (value: string) => void;
  onApply: () => void;
  applyBusy?: boolean;
  /** Trash tab: only permanent delete is offered. */
  trashMode: boolean;
};

/**
 * WordPress-style bulk row for entity lists (select + Apply).
 */
export function BulkActionsBar({
  disabled,
  selectedCount,
  value,
  onChange,
  onApply,
  applyBusy = false,
  trashMode,
}: BulkActionsBarProps) {
  const blocked = disabled || applyBusy;
  const canApply = !blocked && selectedCount > 0 && value !== '';

  return (
    <div className="flex flex-wrap items-center gap-2">
      <label htmlFor="sikshya-bulk" className="sr-only">
        Bulk actions
      </label>
      <select
        id="sikshya-bulk"
        disabled={blocked}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/50"
      >
        <option value="">Bulk actions</option>
        {trashMode ? (
          <>
            <option value="restore_draft">Restore to draft</option>
            <option value="delete_permanent">Delete permanently</option>
          </>
        ) : (
          <>
            <option value="move_trash">Move to trash</option>
            <option value="publish">Publish</option>
            <option value="draft">Move to draft</option>
            <option value="pending">Mark pending review</option>
            <option value="private">Move to private</option>
          </>
        )}
      </select>
      <button
        type="button"
        disabled={!canApply}
        onClick={onApply}
        className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
      >
        {applyBusy ? 'Applying…' : 'Apply'}
      </button>
      {selectedCount > 0 ? (
        <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
          {selectedCount} selected
        </span>
      ) : null}
    </div>
  );
}
