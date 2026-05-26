import { getWpApi } from '../api';
import type { SikshyaConfirmOptions } from '../components/shared/SikshyaDialogContext';
import type { RowActionItem } from '../components/shared/list/RowActionsMenu';
import type { WpPost } from '../types';
import { __ } from './i18n';

type ConfirmFn = (opts: SikshyaConfirmOptions) => Promise<boolean>;

/**
 * Row menu items to change post status / trash (WordPress REST `wp/v2`).
 */
export function wpPostStatusRowActions(
  restBase: string,
  row: WpPost,
  refresh: () => Promise<void>,
  confirm: ConfirmFn
): RowActionItem[] {
  const api = getWpApi();
  const st = row.status;

  const patchStatus = (status: string) => async () => {
    await api.patch<unknown>(`/${restBase}/${row.id}`, { status });
    await refresh();
  };

  if (st === 'trash') {
    return [
      {
        key: 'restore',
        label: __('Restore to draft', 'sikshya'),
        onClick: () => void patchStatus('draft')(),
      },
      {
        key: 'delete_perm',
        label: __('Delete permanently', 'sikshya'),
        danger: true,
        onClick: () =>
          void (async () => {
            const ok = await confirm({
              title: __('Delete permanently?', 'sikshya'),
              message: __('This cannot be undone.', 'sikshya'),
              variant: 'danger',
              confirmLabel: __('Delete permanently', 'sikshya'),
            });
            if (!ok) {
              return;
            }
            await api.delete(`/${restBase}/${row.id}?force=true`);
            await refresh();
          })(),
      },
    ];
  }

  const items: RowActionItem[] = [];

  if (st !== 'publish' && st !== 'future') {
    items.push({
      key: 'publish',
      label: __('Publish', 'sikshya'),
      onClick: () => void patchStatus('publish')(),
    });
  }

  if (st !== 'draft') {
    items.push({
      key: 'draft',
      label: __('Move to draft', 'sikshya'),
      onClick: () => void patchStatus('draft')(),
    });
  }

  if (st !== 'pending') {
    items.push({
      key: 'pending',
      label: __('Mark pending review', 'sikshya'),
      onClick: () => void patchStatus('pending')(),
    });
  }

  if (st !== 'private') {
    items.push({
      key: 'private',
      label: __('Move to private', 'sikshya'),
      onClick: () => void patchStatus('private')(),
    });
  }

  items.push({
    key: 'trash',
    label: __('Move to trash', 'sikshya'),
    danger: true,
    onClick: () =>
      void (async () => {
        const ok = await confirm({
          title: __('Move to trash?', 'sikshya'),
          message: __(
            'While in trash, WordPress may add a __trashed suffix to the stored slug (restored automatically when you restore).',
            'sikshya'
          ),
          variant: 'danger',
          confirmLabel: __('Move to trash', 'sikshya'),
        });
        if (!ok) {
          return;
        }
        await api.delete(`/${restBase}/${row.id}`);
        await refresh();
      })(),
  });

  return items;
}
