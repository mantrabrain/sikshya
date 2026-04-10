import type { SikshyaLicensing } from '../types';

type Props = {
  title: string;
  description: string;
  licensing: SikshyaLicensing | null;
  /** Optional small label above the title (keep empty to avoid tier branding in the shell). */
  badgeLabel?: string;
};

/**
 * Shown when a screen is gated by licensing (`featureStates` from PHP).
 */
export function FeatureUpsell({ title, description, licensing, badgeLabel }: Props) {
  const href = licensing?.upgradeUrl || 'https://sikshya.com/pricing/';
  return (
    <div className="rounded-2xl border border-brand-200/80 bg-gradient-to-br from-brand-50/90 via-white to-slate-50 p-8 text-center shadow-sm dark:border-brand-900/40 dark:from-brand-950/50 dark:via-slate-900 dark:to-slate-950">
      {badgeLabel ? (
        <p className="text-xs font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-300">{badgeLabel}</p>
      ) : null}
      <h2 className={`text-xl font-semibold text-slate-900 dark:text-white ${badgeLabel ? 'mt-2' : ''}`}>{title}</h2>
      <p className="mx-auto mt-2 max-w-lg text-sm leading-relaxed text-slate-600 dark:text-slate-400">{description}</p>
      <a
        href={href}
        target="_blank"
        rel="noreferrer"
        className="mt-6 inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600"
      >
        View plans & upgrade
      </a>
      {licensing?.isProActive && !licensing.featureStates?.marketplace_multivendor && licensing.siteTier === 'business' ? (
        <p className="mt-4 text-xs text-slate-500 dark:text-slate-400">
          Your current plan does not include marketplace tools. Upgrade to unlock multi-vendor selling.
        </p>
      ) : null}
    </div>
  );
}
