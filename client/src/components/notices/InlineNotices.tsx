import { useCallback, useEffect, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../../api';
import { __, sprintf } from '../../lib/i18n';

type NoticeAction = {
  label: string;
  url: string;
  target?: string;
};

export type MarketingNotice = {
  id: string;
  type?: string;
  title?: string;
  message?: string;
  actions?: NoticeAction[];
  order_count?: number;
};

type NoticesPayload = {
  success?: boolean;
  data?: MarketingNotice[];
};

const BUY_PRO_ID = 'buy_pro';

export function InlineNotices() {
  const [notices, setNotices] = useState<MarketingNotice[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getSikshyaApi().get<NoticesPayload>(SIKSHYA_ENDPOINTS.admin.notices);
      if (res?.success && Array.isArray(res.data)) {
        setNotices(res.data);
      } else {
        setNotices([]);
      }
    } catch {
      setNotices([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const dismiss = useCallback(
    async (id: string) => {
      try {
        await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.noticesDismiss(id), {});
      } catch {
        // Still hide locally so the shell stays usable if REST hiccups.
      }
      setNotices((prev) => prev.filter((n) => n.id !== id));
    },
    []
  );

  if (loading || notices.length === 0) {
    return null;
  }

  return (
    <div className="mb-6 space-y-4" aria-live="polite">
      {notices.map((n) =>
        n.id === BUY_PRO_ID ? (
          <BuyProStrip key={n.id} notice={n} onDismiss={() => void dismiss(n.id)} />
        ) : (
          <ReviewCard key={n.id} notice={n} onDismiss={() => void dismiss(n.id)} />
        )
      )}
    </div>
  );
}

function ReviewCard({ notice, onDismiss }: { notice: MarketingNotice; onDismiss: () => void }) {
  const primary = notice.actions?.[0];
  const title = notice.title || '';
  const message = notice.message || '';

  return (
    <div
      className="relative overflow-hidden rounded-xl border border-slate-200/90 border-l-4 border-l-brand-600 bg-white shadow-sm dark:border-slate-700 dark:border-l-brand-500 dark:bg-slate-900"
    >
      <button
        type="button"
        className="absolute right-2 top-2 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        aria-label={__('Dismiss notice', 'sikshya')}
        onClick={onDismiss}
      >
        <span aria-hidden="true">×</span>
      </button>
      <div className="flex flex-wrap items-start justify-between gap-4 p-4 pr-10">
        <div className="flex min-w-0 flex-1 gap-3">
          <div
            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-950/60 dark:text-brand-300"
            aria-hidden
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 17.3l-6.18 3.7 1.64-7.03L2 9.24l7.19-.61L12 2l2.81 6.63 7.19.61-5.46 4.73L18.18 21z" />
            </svg>
          </div>
          <div className="min-w-0">
            {title ? <p className="m-0 text-sm font-semibold text-slate-900 dark:text-slate-50">{title}</p> : null}
            {message ? (
              <p className="mt-1.5 m-0 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{message}</p>
            ) : null}
          </div>
        </div>
        {primary?.url && primary?.label ? (
          <a
            href={primary.url}
            target={primary.target === '_blank' ? '_blank' : undefined}
            rel={primary.target === '_blank' ? 'noopener noreferrer' : undefined}
            className="inline-flex shrink-0 items-center rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 dark:bg-brand-500 dark:hover:bg-brand-400"
          >
            {primary.label}
          </a>
        ) : null}
      </div>
    </div>
  );
}

function BuyProStrip({ notice, onDismiss }: { notice: MarketingNotice; onDismiss: () => void }) {
  const primary = notice.actions?.[0];
  const ctaUrl =
    primary?.url ||
    'https://mantrabrain.com/plugins/sikshya-lms/pricing/?utm_source=sikshya&utm_medium=admin&utm_campaign=upgrade-gate&utm_content=buy-pro-strip';
  const ctaLabel = primary?.label || __('Upgrade to Pro', 'sikshya');
  const orderCount = typeof notice.order_count === 'number' ? notice.order_count : 0;

  return (
    <div className="relative overflow-hidden rounded-2xl border border-amber-200 border-l-4 border-l-amber-500 bg-gradient-to-br from-amber-50 via-white to-amber-50/50 shadow-sm dark:border-amber-900/50 dark:border-l-amber-500 dark:from-amber-950/40 dark:via-slate-900 dark:to-amber-950/30">
      <div className="absolute right-24 top-2 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-white">
        {__('⚡ Limited time', 'sikshya')}
      </div>
      <button
        type="button"
        className="absolute right-2 top-2 rounded-lg p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        aria-label={__('Dismiss notice', 'sikshya')}
        onClick={onDismiss}
      >
        <span aria-hidden="true">×</span>
      </button>
      <div className="p-5 pr-10">
        <h3 className="m-0 text-base font-semibold text-slate-900 dark:text-slate-50">
          {__('🚀 Upgrade to Sikshya Pro — up to 50% off!', 'sikshya')}
        </h3>
        <p className="mt-2 m-0 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
          {sprintf(
            __(
              'You’ve recorded %1$d order(s)! Get up to 50%% off on Sikshya Pro. Unlock premium payment tools, advanced modules, automation, and priority support.',
              'sikshya'
            ),
            orderCount
          )}
        </p>
        <div className="mt-3 rounded-xl border-l-[3px] border-amber-500 bg-amber-50/80 p-3 text-xs font-semibold text-slate-700 dark:bg-amber-950/30 dark:text-slate-200">
          {__(
            '🎉 Special Offer: Save up to 50% on your Pro upgrade with premium features and priority support!',
            'sikshya'
          )}
        </div>
        <div className="mt-4 flex flex-wrap items-center gap-3">
          <a
            href={ctaUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center rounded-lg bg-amber-500 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/40 dark:bg-amber-500 dark:hover:bg-amber-400"
          >
            {sprintf(__('⚡ Save up to 50%% — %s', 'sikshya'), ctaLabel)}
          </a>
          <button
            type="button"
            className="text-xs text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 dark:text-slate-400 dark:hover:text-slate-200"
            onClick={onDismiss}
          >
            {__('Maybe later', 'sikshya')}
          </button>
        </div>
      </div>
    </div>
  );
}
