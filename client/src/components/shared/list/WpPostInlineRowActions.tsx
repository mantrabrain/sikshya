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
 * Full WP-like row actions for post types: leading links + optional Preview/View + status/trash actions.
 *
 * Mirrors WordPress core list-table behavior:
 *   - Published / scheduled posts → "View" using the public permalink (`row.link`).
 *   - Drafts / pending / private / auto-drafts → "Preview" using a nonce-signed
 *     URL exposed by the Sikshya REST field `sikshya_preview_link`. The field
 *     is only emitted server-side when the requester can edit the post, so we
 *     can render the action unconditionally when the value is present.
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
  const isPublished = row.status === 'publish' || row.status === 'future';
  const resolvedView = viewHref !== undefined ? viewHref : row.link;
  const showView =
    isPublished &&
    typeof resolvedView === 'string' &&
    resolvedView.length > 0 &&
    resolvedView !== '#';
  const previewHref = row.sikshya_preview_link;
  const showPreview =
    !isPublished &&
    typeof previewHref === 'string' &&
    previewHref.length > 0 &&
    previewHref !== '#';

  const primary: RowActionItem[] = [
    ...leadingItems,
    ...(showPreview
      ? [{ key: 'preview', label: 'Preview', href: previewHref as string, external: true as const }]
      : []),
    ...(showView
      ? [{ key: 'view', label: 'View', href: resolvedView as string, external: true as const }]
      : []),
  ];
  const statusItems = wpPostStatusRowActions(restBase, row, refresh, confirm);
  const all = [...primary, ...statusItems];
  const ariaLabel = `Actions for ${stripTitle(row.title.rendered) || 'item'}`;

  return <InlineRowActions items={all} ariaLabel={ariaLabel} />;
}
