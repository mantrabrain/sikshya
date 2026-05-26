import { __, sprintf } from '../../../lib/i18n';

export type BulkActionOption = { value: string; label: string };

export type BulkActionsBarProps = {
  /** When true (e.g. sample data), bulk UI is disabled. */
  disabled?: boolean;
  selectedCount: number;
  value: string;
  onChange: (value: string) => void;
  onApply: () => void;
  applyBusy?: boolean;
  /** Trash tab: only permanent delete is offered (ignored when `customOptions` is set). */
  trashMode: boolean;
  /** Pro / custom lists: replaces WP post bulk options. */
  customOptions?: BulkActionOption[];
  /** `id`/`htmlFor` for the select (avoid duplicate ids when multiple bars exist). */
  selectId?: string;
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
  customOptions,
  selectId = 'sikshya-bulk',
}: BulkActionsBarProps) {
  const blocked = disabled || applyBusy;
  const canApply = !blocked && selectedCount > 0 && value !== '';
  const useCustom = Array.isArray(customOptions) && customOptions.length > 0;

  return (
    <div className="flex flex-wrap items-center gap-2">
      <label htmlFor={selectId} className="sr-only">
        {__('Bulk actions', 'sikshya')}
      </label>
      <select
        id={selectId}
        disabled={blocked}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/50"
      >
        <option value="">{__('Bulk actions', 'sikshya')}</option>
        {useCustom ? (
          customOptions.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))
        ) : trashMode ? (
          <>
            <option value="restore_draft">{__('Restore to draft', 'sikshya')}</option>
            <option value="delete_permanent">{__('Delete permanently', 'sikshya')}</option>
          </>
        ) : (
          <>
            <option value="move_trash">{__('Move to trash', 'sikshya')}</option>
            <option value="publish">{__('Publish', 'sikshya')}</option>
            <option value="draft">{__('Move to draft', 'sikshya')}</option>
            <option value="pending">{__('Mark pending review', 'sikshya')}</option>
            <option value="private">{__('Move to private', 'sikshya')}</option>
          </>
        )}
      </select>
      <button
        type="button"
        disabled={!canApply}
        onClick={onApply}
        className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
      >
        {applyBusy ? __('Applying…', 'sikshya') : __('Apply', 'sikshya')}
      </button>
      {selectedCount > 0 ? (
        <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
          {sprintf(__('%d selected', 'sikshya'), selectedCount)}
        </span>
      ) : null}
    </div>
  );
}
