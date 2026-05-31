import { useEffect, useState } from 'react';
import { getErrorSummary } from '../../api/errors';
import { getWpApi } from '../../api';
import { appViewHref } from '../../lib/appUrl';
import { useAdminRouting } from '../../lib/adminRouting';
import type { SikshyaReactConfig } from '../../types';
import { ButtonPrimary } from './buttons';
import { Modal } from './Modal';
import { __, sprintf } from '../../lib/i18n';

type Props = {
  config: SikshyaReactConfig;
  open: boolean;
  onClose: () => void;
};

export function CreateCertificateModal({ config, open, onClose }: Props) {
  const { navigateHref } = useAdminRouting();
  const [title, setTitle] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) {
      return;
    }
    setTitle('');
    setSubmitting(false);
    setError(null);
  }, [open]);

  const handleClose = () => {
    if (!submitting) {
      onClose();
    }
  };

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const name = title.trim();
    if (!name) {
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      const created = await getWpApi().post<{ id: number }>('/sikshya_certificate', {
        title: name,
        status: 'draft',
      });
      if (!created?.id) {
        throw new Error(__('Could not create certificate.', 'sikshya'));
      }
      navigateHref(
        appViewHref(config, 'edit-content', {
          post_type: 'sikshya_certificate',
          post_id: String(created.id),
        })
      );
    } catch (err) {
      setError(getErrorSummary(err));
      setSubmitting(false);
    }
  };

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title={__('Create certificate', 'sikshya')}
      description={__(
        'Give your certificate a title first. When you click Build, Sikshya opens the full-page certificate builder directly.',
        'sikshya'
      )}
      size="md"
      footer={
        <div className="flex flex-wrap items-center justify-end gap-2">
          <button
            type="button"
            onClick={handleClose}
            disabled={submitting}
            className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 focus-visible:ring-offset-1 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus-visible:ring-offset-slate-900"
          >
            {__('Cancel', 'sikshya')}
          </button>
          <ButtonPrimary type="submit" form="sikshya-create-certificate-form" disabled={submitting}>
            {submitting ? __('Opening builder…', 'sikshya') : __('Build certificate', 'sikshya')}
          </ButtonPrimary>
        </div>
      }
    >
      <form id="sikshya-create-certificate-form" onSubmit={onSubmit} className="space-y-4">
        <div>
          <label htmlFor="sikshya-new-certificate-title" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {__('Certificate title', 'sikshya')}
          </label>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {__('This becomes the template name shown in your certificates list.', 'sikshya')}
          </p>
          <input
            id="sikshya-new-certificate-title"
            type="text"
            name="title"
            required
            autoComplete="off"
            maxLength={200}
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder={__('e.g. Course Completion Certificate', 'sikshya')}
            disabled={submitting}
            className="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
          />
        </div>
        {error ? (
          <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200" role="alert">
            {error}
          </div>
        ) : null}
        <p className="text-xs text-slate-500 dark:text-slate-400">
          {sprintf(
            __('The certificate is created as a %s and opened directly in the builder.', 'sikshya'),
            __('draft', 'sikshya')
          )}
        </p>
      </form>
    </Modal>
  );
}
