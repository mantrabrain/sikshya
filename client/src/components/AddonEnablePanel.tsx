import { ApiErrorPanel } from './shared/ApiErrorPanel';
import { ButtonPrimary } from './shared/buttons';
import { PREMIUM_LOCK_CARD_CLASS } from './PremiumGatedSurface';
import { TopRightToast, useTopRightToast } from './shared/TopRightToast';
import { getErrorSummary } from '../api';

export function AddonEnablePanel(props: {
  title: string;
  description: string;
  canEnable: boolean;
  enableLabel?: string;
  enableBusy?: boolean;
  onEnable: () => Promise<void>;
  upgradeUrl?: string;
  error?: unknown;
  /** Matches plan-gate premium card when shown inside `PremiumGatedSurface`. */
  variant?: 'default' | 'premium';
}) {
  const { title, description, canEnable, enableLabel, enableBusy, onEnable, upgradeUrl, error, variant = 'default' } = props;
  const toast = useTopRightToast(3200);

  const onEnableClick = async () => {
    toast.clear();
    try {
      await onEnable();
      const base = title.replace(/\s+is\s+not\s+enabled\s*$/i, '').trim();
      toast.success('Enabled', base ? `${base} enabled.` : 'Add-on enabled.');
    } catch (e) {
      toast.error('Enable failed', getErrorSummary(e));
    }
  };

  const isPremium = variant === 'premium';

  return (
    <div
      className={
        isPremium
          ? PREMIUM_LOCK_CARD_CLASS
          : 'w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900'
      }
    >
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />
      {isPremium ? (
        <div
          className="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-yellow-500 via-amber-400 to-yellow-600"
          aria-hidden
        />
      ) : null}
      {isPremium ? (
        <div
          className="pointer-events-none absolute -left-12 -bottom-10 h-40 w-40 rounded-full bg-gradient-to-tr from-amber-400/20 to-yellow-500/5 blur-2xl dark:from-amber-600/10"
          aria-hidden
        />
      ) : null}

      <div className={isPremium ? 'relative px-6 pb-7 pt-8 sm:px-8 sm:pb-8 sm:pt-9' : ''}>
        {isPremium ? (
          <span className="inline-flex items-center gap-2 rounded-full border border-amber-400/55 bg-gradient-to-r from-amber-100 to-yellow-100 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-amber-950 ring-1 ring-amber-300/60 dark:border-amber-600/45 dark:from-amber-950/85 dark:to-amber-900/70 dark:text-amber-100 dark:ring-amber-800/45">
            <span
              className="inline-block h-1.5 w-1.5 rounded-full bg-yellow-500 shadow-[0_0_10px_rgba(234,179,8,0.85)]"
              aria-hidden
            />
            Add-on
          </span>
        ) : null}

        <div className={isPremium ? 'mt-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-5' : ''}>
          {isPremium ? (
            <div
              className="mx-auto flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-yellow-500 via-amber-500 to-amber-700 shadow-lg shadow-amber-900/30 ring-2 ring-amber-200/70 dark:from-yellow-600 dark:via-amber-600 dark:to-amber-900 dark:ring-amber-800/50 sm:mx-0"
              aria-hidden
            >
              <svg className="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                />
              </svg>
            </div>
          ) : null}
          <div className="min-w-0 flex-1 text-center sm:text-left">
            <div className={`font-semibold text-stone-900 dark:text-amber-50 ${isPremium ? 'text-lg sm:text-xl' : 'text-sm dark:text-white'}`}>
              {title}
            </div>
            <div className={`mt-1 text-stone-600 dark:text-slate-400 ${isPremium ? 'text-sm leading-relaxed dark:text-amber-100/80' : 'text-sm'}`}>
              {description}
            </div>
          </div>
        </div>

        {error ? (
          <div className="mt-4">
            <ApiErrorPanel error={error} title="Could not update addon" onRetry={() => void 0} />
          </div>
        ) : null}

        <div className={`flex flex-wrap items-stretch gap-3 ${isPremium ? 'mt-8 justify-center sm:justify-start' : 'mt-5 gap-2'}`}>
          {canEnable ? (
            isPremium ? (
              <button
                type="button"
                disabled={enableBusy}
                onClick={onEnableClick}
                className="inline-flex min-h-[48px] flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-amber-900/35 transition hover:from-amber-500 hover:via-yellow-400 hover:to-amber-500 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-offset-stone-900 sm:flex-none"
              >
                {enableBusy ? 'Enabling…' : enableLabel || 'Enable add-on'}
              </button>
            ) : (
              <ButtonPrimary type="button" disabled={enableBusy} onClick={onEnableClick}>
                {enableBusy ? 'Enabling…' : enableLabel || 'Enable addon'}
              </ButtonPrimary>
            )
          ) : upgradeUrl ? (
            <a
              href={upgradeUrl}
              target="_blank"
              rel="noreferrer noopener"
              className={
                isPremium
                  ? 'inline-flex min-h-[48px] flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-600 px-6 py-3 text-center text-base font-semibold text-white shadow-lg shadow-amber-900/35 transition hover:from-amber-500 hover:via-yellow-400 hover:to-amber-500 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-stone-900 sm:flex-none'
                  : 'inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700'
              }
            >
              {isPremium ? 'View plans & upgrade' : 'Upgrade to unlock'}
            </a>
          ) : null}
        </div>

        {isPremium ? (
          <p className="mt-6 text-center text-xs leading-relaxed text-stone-600 dark:text-amber-200/70 sm:text-left">
            {canEnable
              ? 'Your plan includes this add-on—enable it here to activate the feature for your site.'
              : upgradeUrl
                ? 'Upgrade Sikshya Pro to the right tier for this add-on, then return here to enable it in one click.'
                : 'Enable this add-on when your license allows it—contact support if you need help with your plan.'}
          </p>
        ) : null}
      </div>
    </div>
  );
}
