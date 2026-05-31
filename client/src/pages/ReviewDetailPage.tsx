import { useCallback, useEffect, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { StatusBadge } from '../components/shared/list/StatusBadge';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { useAdminRouting } from '../lib/adminRouting';
import { formatPostDate } from '../lib/formatPostDate';
import type { SikshyaReactConfig } from '../types';
import { __ } from '../lib/i18n';

type ReviewRow = {
  id: number;
  user_id: number;
  course_id: number;
  rating: number;
  review_text: string;
  is_approved: boolean;
  author_name: string;
  author_email: string;
  course_title: string;
  created_at: string;
  created_at_label: string;
  edit_url: string;
  view_url: string;
  reported_count?: number;
  last_reported_at?: string;
  reply_text?: string;
  reply_user_id?: number;
  reply_created_at?: string;
};

type DetailResponse = { success: boolean; data?: ReviewRow; message?: string };

function StarDisplay({ value }: { value: number }) {
  const v = Math.max(0, Math.min(5, Math.round(value)));
  return (
    <span aria-label={`${v} out of 5`} className="inline-flex items-center gap-0.5 text-amber-500">
      {[1, 2, 3, 4, 5].map((n) => (
        <span key={n} className={n <= v ? '' : 'text-slate-300 dark:text-slate-600'}>
          {n <= v ? '★' : '☆'}
        </span>
      ))}
    </span>
  );
}

export function ReviewDetailPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const { confirm } = useSikshyaDialog();
  const { route, navigateView } = useAdminRouting();

  const featureOk = isFeatureEnabled(config, 'course_reviews');
  const addon = useAddonEnabled('course_reviews');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const gateOpen = mode === 'full';

  const reviewId = useMemo(() => parseInt(route.query?.id || '0', 10) || 0, [route.query?.id]);

  const [busy, setBusy] = useState(false);
  const [replyDraft, setReplyDraft] = useState('');

  const loader = useCallback(async () => {
    if (!reviewId) {
      throw new Error(__('Missing review id.', 'sikshya'));
    }
    if (!gateOpen) {
      return null;
    }
    return getSikshyaApi().get<DetailResponse>(SIKSHYA_ENDPOINTS.admin.review(reviewId));
  }, [gateOpen, reviewId]);

  const { loading, data, error, refetch } = useAsyncData(loader, [gateOpen, reviewId]);

  const row =
    gateOpen && data && data.success && data.data ? data.data : null;

  useEffect(() => {
    if (row) {
      setReplyDraft(row.reply_text ?? '');
    }
  }, [row?.id, row?.reply_text]);

  const saveReply = async () => {
    const text = replyDraft.trim();
    if (!text || !reviewId) return;
    setBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.reviewReply(reviewId), { reply_text: text });
      await refetch();
    } finally {
      setBusy(false);
    }
  };

  const removeReply = async () => {
    if (!reviewId) return;
    const ok = await confirm({
      title: __('Remove reply?', 'sikshya'),
      message: __('This removes the public instructor/admin reply from the course page.', 'sikshya'),
      variant: 'danger',
      confirmLabel: __('Remove', 'sikshya'),
    });
    if (!ok) return;
    setBusy(true);
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.admin.reviewReply(reviewId));
      setReplyDraft('');
      await refetch();
    } finally {
      setBusy(false);
    }
  };

  const approve = async () => {
    if (!reviewId) return;
    setBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.reviewApprove(reviewId), {});
      await refetch();
    } finally {
      setBusy(false);
    }
  };

  const unpublish = async () => {
    if (!reviewId) return;
    setBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.reviewReject(reviewId), {});
      await refetch();
    } finally {
      setBusy(false);
    }
  };

  const remove = async () => {
    if (!reviewId) return;
    const ok = await confirm({
      title: __('Delete review?', 'sikshya'),
      message: __('This review will be permanently removed and the course rating recalculated.', 'sikshya'),
      variant: 'danger',
      confirmLabel: __('Delete', 'sikshya'),
    });
    if (!ok) return;
    setBusy(true);
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.admin.reviewDelete(reviewId));
      navigateView('reviews', {}, { replace: true });
    } finally {
      setBusy(false);
    }
  };

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle={__('Moderate this review and post an official reply on the course page.', 'sikshya')}
      pageActions={
        <ButtonSecondary type="button" onClick={() => navigateView('reviews')}>
          ← Back to reviews
        </ButtonSecondary>
      }
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="course_reviews"
        config={config}
        featureTitle={__('Course reviews & ratings', 'sikshya')}
        featureDescription={__('Collect star ratings and written reviews on your course pages, with optional moderation.', 'sikshya')}
        previewVariant="table"
        addonEnableTitle={__('Reviews moderation is not enabled', 'sikshya')}
        addonEnableDescription={__('Enable the Course reviews addon to use this screen.', 'sikshya')}
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => addon.enable()}
        addonError={addon.error}
      >
        {!gateOpen ? null : error ? (
          <ApiErrorPanel error={error} title={__('Could not load review', 'sikshya')} onRetry={() => refetch()} />
        ) : loading ? (
          <div className="p-8 text-center text-sm text-slate-500">{__('Loading…', 'sikshya')}</div>
        ) : !reviewId ? (
          <p className="text-sm text-slate-600 dark:text-slate-400">{__('Missing review id.', 'sikshya')}</p>
        ) : row ? (
          <div className="space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
              <div className="min-w-0 space-y-1">
                <div className="flex flex-wrap items-center gap-3">
                  {row.rating > 0 ? <StarDisplay value={row.rating} /> : <span className="text-slate-400">—</span>}
                  <StatusBadge
                    tone={row.is_approved ? 'success' : 'warning'}
                    label={row.is_approved ? __('Approved', 'sikshya') : __('Pending', 'sikshya')}
                  />
                </div>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                  Submitted {formatPostDate(row.created_at)} · {row.created_at_label}
                </p>
                <p className="text-lg font-semibold text-slate-900 dark:text-white">
                  {row.author_name || `User #${row.user_id}`}
                  {row.author_email ? (
                    <span className="block text-sm font-normal text-slate-500">{row.author_email}</span>
                  ) : null}
                </p>
                <p className="text-sm">
                  <span className="font-medium text-slate-700 dark:text-slate-300">{__('Course:', 'sikshya')}</span>
                  {row.view_url ? (
                    <a
                      href={row.view_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400"
                    >
                      {row.course_title || `#${row.course_id}`}
                    </a>
                  ) : (
                    row.course_title || `#${row.course_id}`
                  )}
                </p>
                <p className="text-sm text-slate-600 dark:text-slate-400">
                  Reports: {Number(row.reported_count ?? 0).toLocaleString()}
                  {row.last_reported_at ? (
                    <span className="text-slate-400"> · Last {formatPostDate(row.last_reported_at)}</span>
                  ) : null}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                {row.is_approved ? (
                  <ButtonSecondary type="button" disabled={busy} onClick={() => void unpublish()}>
                    Unpublish
                  </ButtonSecondary>
                ) : (
                  <ButtonPrimary type="button" disabled={busy} onClick={() => void approve()}>
                    Approve
                  </ButtonPrimary>
                )}
                <ButtonSecondary
                  type="button"
                  disabled={busy}
                  className="border-red-200 text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/40"
                  onClick={() => void remove()}
                >
                  Delete
                </ButtonSecondary>
              </div>
            </div>

            <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
              <h2 className="text-sm font-semibold text-slate-900 dark:text-white">{__('Review', 'sikshya')}</h2>
              {row.review_text ? (
                <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                  {row.review_text}
                </p>
              ) : (
                <p className="mt-2 text-sm italic text-slate-400">(Rating only — no written review)</p>
              )}
            </div>

            <div className="rounded-xl border border-slate-200 bg-slate-50/80 p-5 dark:border-slate-700 dark:bg-slate-950/40">
              <h2 className="text-sm font-semibold text-slate-900 dark:text-white">{__('Official reply', 'sikshya')}</h2>
              <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Shown publicly under this review on the course page.
              </p>
              <textarea
                rows={5}
                value={replyDraft}
                onChange={(e) => setReplyDraft(e.target.value)}
                placeholder={__('Write a short reply…', 'sikshya')}
                className="mt-3 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
              />
              <div className="mt-3 flex flex-wrap justify-end gap-2">
                {row.reply_text ? (
                  <ButtonSecondary type="button" disabled={busy} onClick={() => void removeReply()}>
                    Remove reply
                  </ButtonSecondary>
                ) : null}
                <ButtonPrimary type="button" disabled={busy || replyDraft.trim() === ''} onClick={() => void saveReply()}>
                  Save reply
                </ButtonPrimary>
              </div>
            </div>
          </div>
        ) : !loading && !error ? (
          <p className="text-sm text-slate-600 dark:text-slate-400">{__('Review not found.', 'sikshya')}</p>
        ) : null}
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}
