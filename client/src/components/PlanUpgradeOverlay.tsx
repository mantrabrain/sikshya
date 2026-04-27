import { useEffect, useId, useRef } from 'react';
import { appViewHref } from '../lib/appUrl';
import { getCatalogEntry, getLicensing, requiredPlanLabelForFeature } from '../lib/licensing';
import type { SikshyaReactConfig } from '../types';
import { PREMIUM_LOCK_CARD_CLASS, PremiumGatedSurface } from './PremiumGatedSurface';

type Props = {
  config: SikshyaReactConfig;
  featureId: string;
  /** Used when the catalog row is missing */
  featureTitle: string;
  description: string;
};

function sellingBullets(fullDescription: string): [string, string] {
  const cleaned = fullDescription.trim();
  const sentences = cleaned
    .split(/(?<=[.!?])\s+/)
    .map((s) => s.trim())
    .filter((s) => s.length > 8);
  if (sentences.length >= 2) {
    return [sentences[0], sentences[1]];
  }
  if (sentences.length === 1) {
    return [
      sentences[0],
      'Upgrade to the right plan to turn this on for your site—no guesswork.',
    ];
  }
  return [
    'This capability is part of the Pro add-on—unlock it when your plan includes it.',
    'Compare plans and upgrade in one click. You can also enable modules from Addons after upgrading.',
  ];
}

/**
 * Plan-gate overlay: full-bleed premium surface, conversion-focused copy, dialog semantics.
 * @see docs/AI_ADDON_PREMIUM_UX_IMPLEMENTATION_BLUEPRINT.md Part D
 */
export function PlanUpgradeOverlay(props: Props) {
  const { config, featureId, featureTitle, description } = props;
  const lic = getLicensing(config);
  const brandName = config.branding?.pluginName?.trim() || 'Sikshya';
  const entry = getCatalogEntry(config, featureId);
  const title = entry?.label || featureTitle;
  const body = (entry?.description && entry.description.trim()) || description;
  const plan = requiredPlanLabelForFeature(config, featureId);
  const upgradeHref = config.brandLinks?.upgradeUrl || lic?.upgradeUrl || 'https://sikshya.com/pricing/';
  const titleId = useId();
  const descId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const [bulletA, bulletB] = sellingBullets(body);

  useEffect(() => {
    const node = panelRef.current?.querySelector<HTMLElement>('a[data-sikshya-primary-upgrade]');
    node?.focus();
  }, []);

  return (
    <PremiumGatedSurface>
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={descId}
        tabIndex={-1}
        className={PREMIUM_LOCK_CARD_CLASS}
      >
        <div
          className="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-600"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-gradient-to-br from-yellow-300/25 to-amber-600/10 blur-2xl dark:from-amber-500/15 dark:to-yellow-600/5"
          aria-hidden
        />
        <div className="relative px-6 pb-7 pt-8 sm:px-8 sm:pb-8 sm:pt-9">
          <div className="flex flex-col items-center text-center sm:items-start sm:text-left">
            <span className="inline-flex items-center gap-2 rounded-full border border-amber-400/50 bg-gradient-to-r from-amber-100 via-yellow-50 to-amber-100 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-amber-950 ring-1 ring-amber-300/60 dark:border-amber-500/40 dark:from-amber-950/90 dark:via-amber-900/70 dark:to-amber-950/90 dark:text-amber-100 dark:ring-amber-700/50">
              <span
                className="inline-block h-1.5 w-1.5 rounded-full bg-amber-500 shadow-[0_0_10px_rgba(245,158,11,0.9)]"
                aria-hidden
              />
              {brandName} Pro
            </span>

            <div className="mt-5 flex w-full flex-col items-center gap-4 sm:flex-row sm:items-start sm:gap-5">
              <div
                className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 via-yellow-500 to-amber-700 shadow-lg shadow-amber-900/35 ring-2 ring-amber-200/80 dark:from-amber-400 dark:via-yellow-600 dark:to-amber-800 dark:ring-amber-700/50"
                aria-hidden
              >
                <svg className="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                  />
                </svg>
              </div>
              <div className="min-w-0 flex-1">
                <p className="text-xs font-semibold uppercase tracking-wide text-amber-900/80 dark:text-amber-200/90">
                  Included on <span className="text-amber-700 dark:text-amber-300">{plan}</span> plan
                </p>
                <h2
                  id={titleId}
                  className="mt-1.5 text-xl font-bold leading-snug tracking-tight text-stone-900 dark:text-amber-50 sm:text-2xl"
                >
                  Unlock {title}
                </h2>
              </div>
            </div>

            <p id={descId} className="mt-5 text-sm leading-relaxed text-stone-700 dark:text-amber-100/85">
              {body}
            </p>

            <ul className="mt-5 w-full space-y-2.5 text-left text-sm text-stone-800 dark:text-amber-50/90">
              <li className="flex gap-2.5">
                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-200 text-amber-900 dark:bg-amber-800/90 dark:text-amber-100">
                  <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5} aria-hidden>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </span>
                <span>{bulletA}</span>
              </li>
              <li className="flex gap-2.5">
                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-200 text-amber-900 dark:bg-amber-800/90 dark:text-amber-100">
                  <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5} aria-hidden>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </span>
                <span>{bulletB}</span>
              </li>
            </ul>

            <div className="mt-8 flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
              <a
                data-sikshya-primary-upgrade
                href={upgradeHref}
                target="_blank"
                rel="noreferrer noopener"
                className="inline-flex min-h-[48px] flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-600 px-6 py-3 text-center text-base font-semibold text-white shadow-lg shadow-amber-900/35 transition hover:from-amber-500 hover:via-yellow-400 hover:to-amber-500 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-stone-900"
              >
                View plans &amp; upgrade
              </a>
              <a
                href={appViewHref(config, 'addons')}
                className="inline-flex min-h-[48px] flex-1 items-center justify-center rounded-xl border border-amber-300/90 bg-white/95 px-5 py-3 text-center text-sm font-semibold text-amber-950 shadow-sm transition hover:bg-amber-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 dark:border-amber-700/60 dark:bg-stone-900/90 dark:text-amber-100 dark:hover:bg-stone-800 dark:focus-visible:ring-offset-stone-900"
              >
                Manage add-ons
              </a>
            </div>

            <p className="mt-6 max-w-md text-center text-xs leading-relaxed text-stone-600 dark:text-amber-200/70 sm:text-left">
              You’re seeing a preview of this screen. Upgrade your plan to activate this feature—your data stays safe and nothing
              changes until you’re ready.
            </p>
          </div>
        </div>
      </div>
    </PremiumGatedSurface>
  );
}
