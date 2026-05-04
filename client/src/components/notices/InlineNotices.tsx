import { useCallback, useEffect, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../../api';

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
      className="relative overflow-hidden rounded-[10px] border border-slate-200/90 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
      style={{ borderLeftWidth: 4, borderLeftColor: '#2563eb' }}
    >
      <button
        type="button"
        className="absolute right-2 top-2 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        aria-label="Dismiss notice"
        onClick={onDismiss}
      >
        <span aria-hidden="true">×</span>
      </button>
      <div className="flex flex-wrap items-start justify-between gap-4 p-4 pr-10">
        <div className="flex min-w-0 flex-1 gap-3">
          <div
            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-[#2563eb]"
            style={{ background: '#eff6ff' }}
            aria-hidden
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 17.3l-6.18 3.7 1.64-7.03L2 9.24l7.19-.61L12 2l2.81 6.63 7.19.61-5.46 4.73L18.18 21z" />
            </svg>
          </div>
          <div className="min-w-0">
            {title ? <p className="m-0 text-sm font-bold text-slate-900 dark:text-slate-50">{title}</p> : null}
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
            className="inline-flex shrink-0 items-center rounded-lg bg-[#2271b1] px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-[#135e96]"
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
  const ctaUrl = primary?.url || 'https://mantrabrain.com/plugins/sikshya/#pricing';
  const ctaLabel = primary?.label || 'Upgrade to Pro';
  const orderCount = typeof notice.order_count === 'number' ? notice.order_count : 0;

  return (
    <div
      className="relative overflow-hidden rounded-md border border-[#f0e6d8] shadow-sm"
      style={{
        background: 'linear-gradient(135deg, #fdf6f0 0%, #f8f9fa 50%, #fff5ee 100%)',
        borderLeftWidth: 4,
        borderLeftColor: '#ff9500',
      }}
    >
      <div className="absolute right-24 top-2 rounded-full bg-[#ff9500] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
        ⚡ Limited time
      </div>
      <button
        type="button"
        className="absolute right-2 top-2 rounded p-1 text-slate-500 hover:bg-black/5 hover:text-slate-800"
        aria-label="Dismiss notice"
        onClick={onDismiss}
      >
        <span aria-hidden="true">×</span>
      </button>
      <div className="p-4 pr-10">
        <h3 className="m-0 text-[17px] font-semibold text-[#2c3e50] dark:text-slate-100">
          🚀 Upgrade to Sikshya Pro — save 50%+!
        </h3>
        <p className="mt-2 m-0 text-sm leading-relaxed text-[#495057] dark:text-slate-300">
          You’ve recorded <strong className="text-[#ff9500]">{orderCount}</strong> order(s)! Get{' '}
          <strong className="text-[#ff9500]">50%+ OFF</strong> on Sikshya Pro. Unlock premium payment tools, advanced
          modules, automation, and priority support.
        </p>
        <div
          className="mt-3 rounded border-l-[3px] border-[#ff9500] p-2.5 text-[13px] font-semibold text-[#495057] dark:text-slate-200"
          style={{ background: 'rgba(255, 149, 0, 0.08)' }}
        >
          🎉 <strong>Special Offer:</strong> Save 50%+ on your Pro upgrade with premium features and priority support!
        </div>
        <div className="mt-4 flex flex-wrap items-center gap-3">
          <a
            href={ctaUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center rounded bg-[#ff9500] px-3.5 py-1.5 text-[13px] font-semibold text-white shadow hover:opacity-95"
          >
            ⚡ Save 50%+ — {ctaLabel}
          </a>
          <button
            type="button"
            className="text-[13px] text-[#6c757d] underline-offset-2 hover:text-slate-900 hover:underline dark:text-slate-400 dark:hover:text-slate-200"
            onClick={onDismiss}
          >
            Maybe later
          </button>
        </div>
      </div>
    </div>
  );
}
