import { NavIcon } from '../../NavIcon';
import type { RowActionItem } from './RowActionsMenu';

function RowSep() {
  return <span className="mx-1 text-slate-300 select-none dark:text-slate-600" aria-hidden>|</span>;
}

/** Icon name from shared icons.json; optional per action key. */
function iconForAction(item: RowActionItem): string {
  const k = item.key;
  if (k === 'view') {
    return 'arrowTopRightOnSquare';
  }
  if (k === 'trash' || k === 'delete_perm' || k === 'delete') {
    return 'trash';
  }
  if (k === 'restore') {
    return 'arrowUturnLeft';
  }
  if (k === 'publish') {
    return 'iconPublish';
  }
  if (k === 'draft') {
    return 'iconSaveDraft';
  }
  if (k === 'pending') {
    return 'schedule';
  }
  if (k === 'private') {
    return 'cog';
  }
  if (k === 'builder' || k === 'edit') {
    return 'pencil';
  }
  return 'chevronRight';
}

const wrapClass =
  'inline-flex items-center gap-0.5 rounded px-0.5 py-0.5 text-[11px] font-normal text-slate-400 transition-colors hover:text-slate-600 focus:outline-none focus-visible:ring-1 focus-visible:ring-brand-500/50 dark:text-slate-500 dark:hover:text-slate-300';

const iconClass = 'h-3 w-3 shrink-0 opacity-70';

function renderItem(item: RowActionItem) {
  const icon = iconForAction(item);
  if ('href' in item) {
    return (
      <a
        href={item.href}
        className={wrapClass}
        {...(item.external ? { target: '_blank', rel: 'noreferrer noopener' } : {})}
      >
        <NavIcon name={icon} className={iconClass} />
        <span>{item.label}</span>
      </a>
    );
  }
  return (
    <button type="button" className={`${wrapClass} ${item.danger ? '!text-red-500/80 hover:!text-red-600 dark:!text-red-400/80' : ''}`} onClick={item.onClick}>
      <NavIcon name={item.danger ? 'trash' : icon} className={iconClass} />
      <span>{item.label}</span>
    </button>
  );
}

/**
 * WordPress-style row actions under the title cell. Hidden until the table row is hovered
 * (see {@link DataTable} `group` on rows) or an action is focused.
 */
export function InlineRowActions({ items, ariaLabel }: { items: RowActionItem[]; ariaLabel?: string }) {
  if (items.length === 0) {
    return null;
  }
  return (
    <div
      className="row-actions mt-1.5 opacity-0 transition-opacity duration-150 group-hover:opacity-100 focus-within:opacity-100"
      {...(ariaLabel ? { 'aria-label': ariaLabel } : {})}
    >
      <div className="flex flex-wrap items-center gap-x-0 normal-case tracking-normal">
        {items.map((item, i) => (
          <span key={item.key} className="inline-flex items-center">
            {i > 0 ? <RowSep /> : null}
            {renderItem(item)}
          </span>
        ))}
      </div>
    </div>
  );
}
