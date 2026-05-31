import { useId } from 'react';
import { getCatalogEntry, requiredPlanLabelForFeature } from '../lib/licensing';
import { __, sprintf } from '../lib/i18n';
import { sikshyaPricingUrl } from '../lib/upgradeUrl';
import type { SikshyaReactConfig } from '../types';
import {
  PREMIUM_HERO_GRADIENT_CLASS,
  PREMIUM_LOCK_CARD_CLASS,
  PremiumGatedSurface,
} from './PremiumGatedSurface';

type Props = {
  config: SikshyaReactConfig;
  featureId: string;
  /** Used when the catalog row is missing */
  featureTitle: string;
  description: string;
};

type ValueRow = {
  title: string;
  detail: string;
};

/**
 * Build a 4-row value grid from the catalog description. If the description has
 * multiple sentences we use them; otherwise we fall back to category-generic
 * Pro benefits so the grid always feels populated.
 */
function buildValueRows(fullDescription: string): ValueRow[] {
  const sentences = fullDescription
    .trim()
    .split(/(?<=[.!?])\s+/)
    .map((s) => s.trim())
    .filter((s) => s.length > 8);

  const generic: ValueRow[] = [
    {
      title: __('Full feature access', 'sikshya'),
      detail: __('Use this module without any limits.', 'sikshya'),
    },
    {
      title: __('Priority support', 'sikshya'),
      detail: __('Direct email support from our team.', 'sikshya'),
    },
    {
      title: __('Regular updates', 'sikshya'),
      detail: __('New features and improvements as they ship.', 'sikshya'),
    },
    {
      title: __('Self-hosted, no SaaS', 'sikshya'),
      detail: __('Runs on your WordPress — your data stays with you.', 'sikshya'),
    },
  ];

  const rows: ValueRow[] = [];
  for (let i = 0; i < Math.min(2, sentences.length); i++) {
    const s = sentences[i];
    const words = s.split(/\s+/);
    const titleWords = words.slice(0, Math.min(6, Math.max(3, Math.floor(words.length / 3))));
    const titleText = titleWords.join(' ').replace(/[.,;:]$/, '');
    const detailText = words.slice(titleWords.length).join(' ').replace(/^[,;:]\s*/, '') || s;
    rows.push({ title: titleText, detail: detailText });
  }
  for (const g of generic) {
    if (rows.length >= 4) break;
    if (!rows.some((r) => r.title.toLowerCase() === g.title.toLowerCase())) {
      rows.push(g);
    }
  }
  return rows.slice(0, 4);
}

/**
 * Plan-gate upgrade screen — feels like a real upgrade pitch. Brand-gradient
 * hero header (purple-led, since "Upgrade" surfaces use the logo accent purple
 * across the product), value-grid of 4 highlights, and a strong purple CTA.
 */
export function PlanUpgradeOverlay(props: Props) {
  const { config, featureId, featureTitle, description } = props;
  const brandName = config.branding?.pluginName?.trim() || 'Sikshya';
  const entry = getCatalogEntry(config, featureId);
  const title = entry?.label || featureTitle;
  const body = (entry?.description && entry.description.trim()) || description;
  const plan = requiredPlanLabelForFeature(config, featureId);
  // Both upgrade CTAs route to the canonical Sikshya LMS pricing page with
  // UTM tags so the campaign analytics on mantrabrain.com can distinguish
  // primary "Upgrade to {Plan}" clicks from secondary "See pricing" clicks
  // and attribute them back to the specific feature that triggered the gate.
  const upgradeHref = sikshyaPricingUrl('upgrade-cta', featureId);
  const seePlansHref = sikshyaPricingUrl('see-plans', featureId);
  const titleId = useId();
  const descId = useId();
  const valueRows = buildValueRows(body);

  return (
    <PremiumGatedSurface>
      <div
        role="region"
        aria-labelledby={titleId}
        aria-describedby={descId}
        className={PREMIUM_LOCK_CARD_CLASS}
      >
        {/* Hero — purple-led gradient (logo accent → darker accent → navy edge) */}
        <div className={`relative overflow-hidden ${PREMIUM_HERO_GRADIENT_CLASS} px-7 pt-8 pb-7 sm:px-9 sm:pt-9 sm:pb-8`}>
          {/* Subtle decorative radials */}
          <div
            className="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/[0.07] blur-2xl"
            aria-hidden
          />
          <div
            className="pointer-events-none absolute -left-12 -bottom-12 h-48 w-48 rounded-full bg-white/[0.05] blur-3xl"
            aria-hidden
          />

          <div className="relative">
            {/* Lock + Pro badge */}
            <div className="flex items-center gap-2.5">
              <div
                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/15 ring-1 ring-inset ring-white/25 backdrop-blur-sm"
                aria-hidden
              >
                <svg
                  className="h-[18px] w-[18px] text-white"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  strokeWidth={2}
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                  />
                </svg>
              </div>
              <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-xs font-bold uppercase tracking-[0.08em] text-white ring-1 ring-inset ring-white/25 backdrop-blur-sm">
                {sprintf(__('%1$s Pro · %2$s', 'sikshya'), brandName, plan)}
              </span>
            </div>

            {/* Title — smaller, like the earlier version */}
            <h2
              id={titleId}
              className="mt-5 text-xl font-bold leading-snug tracking-tight text-white sm:text-[1.375rem]"
            >
              {sprintf(__('Unlock %s', 'sikshya'), title)}
            </h2>
            <p
              id={descId}
              className="mt-2 text-sm leading-relaxed text-white/85"
            >
              {body}
            </p>
          </div>
        </div>

        {/* Value grid */}
        <div className="px-7 pt-6 pb-2 sm:px-9 sm:pt-7">
          <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            {__('What you unlock', 'sikshya')}
          </p>
          <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-3">
            {valueRows.map((row, i) => (
              <div
                key={i}
                className="flex gap-3 rounded-xl border border-slate-100 bg-slate-50/60 p-3.5 dark:border-slate-800 dark:bg-slate-800/40"
              >
                <span
                  className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-accent-100 text-accent-700 dark:bg-accent-950/60 dark:text-accent-300"
                  aria-hidden
                >
                  <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </span>
                <div className="min-w-0">
                  <div className="text-xs font-semibold text-slate-900 dark:text-slate-100">
                    {row.title}
                  </div>
                  <div className="mt-0.5 text-[12.5px] leading-relaxed text-slate-600 dark:text-slate-400">
                    {row.detail}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Action band — purple "Upgrade to {plan}" CTA */}
        <div className="px-7 pt-6 pb-7 sm:px-9 sm:pb-8">
          <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center">
            <a
              href={upgradeHref}
              target="_blank"
              rel="noreferrer noopener"
              className="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg bg-accent-600 px-6 py-2.5 text-[14.5px] font-semibold text-white shadow-sm transition hover:bg-accent-700 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
            >
              {sprintf(__('Upgrade to %s', 'sikshya'), plan)}
              <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.25} aria-hidden>
                <path strokeLinecap="round" strokeLinejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </a>
            <a
              href={seePlansHref}
              target="_blank"
              rel="noreferrer noopener"
              className="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-900"
            >
              {__('See pricing', 'sikshya')}
            </a>
          </div>
          <p className="mt-4 flex items-center gap-2 text-[12.5px] text-slate-500 dark:text-slate-400">
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
            {__('14-day refund · Cancel anytime · Existing data stays exactly as it is', 'sikshya')}
          </p>
        </div>
      </div>
    </PremiumGatedSurface>
  );
}
