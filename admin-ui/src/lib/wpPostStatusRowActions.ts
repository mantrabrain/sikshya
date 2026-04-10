import { getWpApi } from '../api';
import type { SikshyaConfirmOptions } from '../components/shared/SikshyaDialogContext';
import type { RowActionItem } from '../components/shared/list/RowActionsMenu';
import type { WpPost } from '../types';

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
        label: 'Restore to draft',
        onClick: () => void patchStatus('draft')(),
      },
      {
        key: 'delete_perm',
        label: 'Delete permanently',
        danger: true,
        onClick: () =>
          void (async () => {
            const ok = await confirm({
              title: 'Delete permanently?',
              message: 'This cannot be undone.',
              variant: 'danger',
              confirmLabel: 'Delete permanently',
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
      label: 'Publish',
      onClick: () => void patchStatus('publish')(),
    });
  }

  if (st !== 'draft') {
    items.push({
      key: 'draft',
      label: 'Move to draft',
      onClick: () => void patchStatus('draft')(),
    });
  }

  if (st !== 'pending') {
    items.push({
      key: 'pending',
      label: 'Mark pending review',
      onClick: () => void patchStatus('pending')(),
    });
  }

  if (st !== 'private') {
    items.push({
      key: 'private',
      label: 'Move to private',
      onClick: () => void patchStatus('private')(),
    });
  }

  items.push({
    key: 'trash',
    label: 'Move to trash',
    danger: true,
    onClick: () =>
      void (async () => {
        const ok = await confirm({
          title: 'Move to trash?',
          message:
            'While in trash, WordPress may add a __trashed suffix to the stored slug (restored automatically when you restore).',
          variant: 'danger',
          confirmLabel: 'Move to trash',
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
