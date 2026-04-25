import type { SikshyaConfirmOptions } from '../SikshyaDialogContext';
import type { WpPost } from '../../../types';
import { wpPostStatusRowActions } from '../../../lib/wpPostStatusRowActions';
import type { RowActionItem } from './RowActionsMenu';
import { InlineRowActions } from './InlineRowActions';

type ConfirmFn = (opts: SikshyaConfirmOptions) => Promise<boolean>;

function stripTitle(html: string): string {
  return html.replace(/<[^>]*>/g, '').trim();
}

/**
 * Full WP-like row actions for post types: leading links + optional View + status/trash actions.
 */
export function WpPostInlineRowActions(props: {
  restBase: string;
  row: WpPost;
  refresh: () => Promise<void>;
  confirm: ConfirmFn;
  leadingItems: RowActionItem[];
  /** When omitted, uses `row.link` when present. Pass `null` to hide View. */
  viewHref?: string | null;
}) {
  const { restBase, row, refresh, confirm, leadingItems, viewHref } = props;
  const resolvedView = viewHref !== undefined ? viewHref : row.link;
  const showView = typeof resolvedView === 'string' && resolvedView.length > 0 && resolvedView !== '#';

  const primary: RowActionItem[] = [
    ...leadingItems,
    ...(showView
      ? [{ key: 'view', label: 'View', href: resolvedView as string, external: true as const }]
      : []),
  ];
  const statusItems = wpPostStatusRowActions(restBase, row, refresh, confirm);
  const all = [...primary, ...statusItems];
  const ariaLabel = `Actions for ${stripTitle(row.title.rendered) || 'item'}`;

  return <InlineRowActions items={all} ariaLabel={ariaLabel} />;
}
