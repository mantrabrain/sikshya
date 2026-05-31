import { ApiErrorPanel } from './shared/ApiErrorPanel';
import { ButtonPrimary } from './shared/buttons';
import { PREMIUM_LOCK_CARD_CLASS } from './PremiumGatedSurface';
import { TopRightToast, useTopRightToast } from './shared/TopRightToast';
import { getErrorSummary } from '../api';
import { __, sprintf } from '../lib/i18n';

export function AddonEnablePanel(props: {
  title: string;
  description: string;
  canEnable: boolean;
  enableLabel?: string;
  enableBusy?: boolean;
  onEnable: () => Promise<void>;
  upgradeUrl?: string;
  error?: unknown;
  /** When inside the gated surface, matches the plan-gate hero design. */
  variant?: 'default' | 'premium';
}) {
  const { title, description, canEnable, enableLabel, enableBusy, onEnable, upgradeUrl, error, variant = 'default' } = props;
  const toast = useTopRightToast(3200);

  const onEnableClick = async () => {
    toast.clear();
    try {
      await onEnable();
      const base = title.replace(/\s+is\s+not\s+enabled\s*$/i, '').trim();
      toast.success(
        __('Enabled', 'sikshya'),
        base ? sprintf(__('%s enabled.', 'sikshya'), base) : __('Add-on enabled.', 'sikshya')
      );
    } catch (e) {
      toast.error(__('Enable failed', 'sikshya'), getErrorSummary(e));
    }
  };

  const isPremium = variant === 'premium';

  if (!isPremium) {
    return (
      <div className="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <TopRightToast toast={toast.toast} onDismiss={toast.clear} />
        <div className="font-semibold text-sm text-slate-900 dark:text-white">{title}</div>
        <div className="mt-1 text-sm text-slate-600 dark:text-slate-400">{description}</div>
        {error ? (
          <div className="mt-4">
            <ApiErrorPanel error={error} title={__('Could not update addon', 'sikshya')} onRetry={() => void 0} />
          </div>
        ) : null}
        <div className="mt-5 flex flex-wrap gap-2">
          {canEnable ? (
            <ButtonPrimary type="button" disabled={enableBusy} onClick={onEnableClick}>
              {enableBusy ? __('Enabling…', 'sikshya') : enableLabel || __('Enable add-on', 'sikshya')}
            </ButtonPrimary>
          ) : upgradeUrl ? (
            <a
              href={upgradeUrl}
              target="_blank"
              rel="noreferrer noopener"
              className="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
            >
              {__('Upgrade to unlock', 'sikshya')}
            </a>
          ) : null}
        </div>
      </div>
    );
  }

  // PREMIUM variant — hero + body + CTA, matching PlanUpgradeOverlay style.
  return (
    <div className={PREMIUM_LOCK_CARD_CLASS}>
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />

      {/* Hero — purple-led gradient */}
      <div className="relative overflow-hidden bg-gradient-to-br from-accent-600 via-accent-700 to-brand-700 px-7 pt-8 pb-7 sm:px-9 sm:pt-9 sm:pb-8">
        <div
          className="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/[0.07] blur-2xl"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute -left-12 -bottom-12 h-48 w-48 rounded-full bg-white/[0.05] blur-3xl"
          aria-hidden
        />

        <div className="relative">
          <div className="flex items-center gap-2.5">
            <div
              className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/15 ring-1 ring-inset ring-white/25 backdrop-blur-sm"
              aria-hidden
            >
              <svg className="h-[18px] w-[18px] text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                />
              </svg>
            </div>
            <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-xs font-bold uppercase tracking-[0.08em] text-white ring-1 ring-inset ring-white/25 backdrop-blur-sm">
              {__('Add-on', 'sikshya')}
            </span>
          </div>

          <h2 className="mt-5 text-xl font-bold leading-snug tracking-tight text-white sm:text-[1.375rem]">
            {title}
          </h2>
          <p className="mt-2 text-sm leading-relaxed text-white/85">
            {description}
          </p>
        </div>
      </div>

      {error ? (
        <div className="px-7 pt-6 sm:px-10">
          <ApiErrorPanel error={error} title={__('Could not update addon', 'sikshya')} onRetry={() => void 0} />
        </div>
      ) : null}

      {/* Action band */}
      <div className="px-7 pt-7 pb-8 sm:px-10 sm:pb-9">
        <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center">
          {canEnable ? (
            <button
              type="button"
              disabled={enableBusy}
              onClick={onEnableClick}
              className="inline-flex min-h-[46px] items-center justify-center gap-2 rounded-lg bg-accent-600 px-6 py-2.5 text-[14.5px] font-semibold text-white shadow-sm transition hover:bg-accent-700 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-slate-900"
            >
              {enableBusy ? __('Enabling…', 'sikshya') : enableLabel || __('Enable add-on', 'sikshya')}
              {!enableBusy ? (
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.25} aria-hidden>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
              ) : null}
            </button>
          ) : upgradeUrl ? (
            <a
              href={upgradeUrl}
              target="_blank"
              rel="noreferrer noopener"
              className="inline-flex min-h-[46px] items-center justify-center gap-2 rounded-lg bg-accent-600 px-6 py-2.5 text-[14.5px] font-semibold text-white shadow-sm transition hover:bg-accent-700 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
            >
              {__('View plans & upgrade', 'sikshya')}
              <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.25} aria-hidden>
                <path strokeLinecap="round" strokeLinejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </a>
          ) : null}
        </div>

        <p className="mt-5 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
          <svg
            className="h-4 w-4 shrink-0 text-emerald-500"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={2}
            aria-hidden
          >
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          {canEnable
            ? __('Enable in one click. Activates the feature immediately for your site.', 'sikshya')
            : upgradeUrl
              ? __('Upgrade to the right tier, then return here to enable.', 'sikshya')
              : __('Contact support if you need help with your license.', 'sikshya')}
        </p>
      </div>
    </div>
  );
}
