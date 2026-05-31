import type { ReactNode } from 'react';

/**
 * StatusBadge — single source of truth for status pills across the admin.
 *
 * Three usage modes (in increasing specificity):
 *
 * 1. Status-string mode (legacy, backward-compatible):
 *      <StatusBadge status="paid" />
 *    Looks the status up in the canonical status→tone map. Unknown values
 *    fall back to neutral slate.
 *
 * 2. Tone mode:
 *      <StatusBadge tone="success" label="Active" />
 *      <StatusBadge tone="danger"  label="Rejected" />
 *    Use when your label needs a specific color but isn't a known status key
 *    (e.g. translated labels, custom domain words). The tone palette is the
 *    same one the status map maps into, so visuals stay consistent.
 *
 * 3. Tier preset:
 *      <StatusBadge tier="scale" />
 *      <StatusBadge tier="growth" />
 *    For tier badges on the Addons page, Pricing tables, etc.
 *
 * Size variants: `size="xs"` for inline meta pills, default is the regular
 * status pill size used in tables.
 */

type Tone = 'success' | 'warning' | 'danger' | 'info' | 'premium' | 'brand' | 'neutral';

const TONE_CLASSES: Record<Tone, string> = {
  success:
    'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-500/30',
  warning:
    'bg-amber-50 text-amber-900 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-500/30',
  danger:
    'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-200 dark:ring-rose-500/30',
  info:
    'bg-sky-50 text-sky-900 ring-sky-600/20 dark:bg-sky-950/40 dark:text-sky-200 dark:ring-sky-500/30',
  premium:
    'bg-violet-50 text-violet-800 ring-violet-600/20 dark:bg-violet-950/40 dark:text-violet-200 dark:ring-violet-500/30',
  brand:
    'bg-brand-50 text-brand-800 ring-brand-600/20 dark:bg-brand-950/40 dark:text-brand-200 dark:ring-brand-500/30',
  neutral: 'bg-slate-100 text-slate-700 ring-slate-500/15 dark:bg-slate-800 dark:text-slate-300',
};

/**
 * Status string → tone mapping. Domains that need additional status keys
 * (e.g. marketplace 'reversed', 'accrued') should add them here rather than
 * rolling a new component — single source of truth.
 */
const STATUS_TO_TONE: Record<string, Tone> = {
  // Success
  publish: 'success',
  published: 'success',
  paid: 'success',
  completed: 'success',
  complete: 'success',
  success: 'success',
  active: 'success',
  available: 'success',
  approved: 'success',
  enabled: 'success',
  open: 'success',
  passed: 'success',
  // Warning / pending
  pending: 'warning',
  accrued: 'warning',
  unpaid: 'warning',
  locked: 'warning',
  draft_review: 'warning',
  needs_reply: 'warning',
  in_progress: 'warning',
  'in-progress': 'warning',
  // Danger
  rejected: 'danger',
  reversed: 'danger',
  suspended: 'danger',
  cancelled: 'danger',
  canceled: 'danger',
  refunded: 'danger',
  failed: 'danger',
  trash: 'danger',
  disabled: 'danger',
  revoked: 'danger',
  closed: 'danger',
  // Info
  future: 'info',
  scheduled: 'info',
  processing: 'info',
  unlocked: 'info',
  // Premium
  private: 'premium',
  // Neutral (default)
  draft: 'neutral',
  'on-hold': 'warning',
  on_hold: 'warning',
};

const TIER_TO_TONE: Record<string, { tone: Tone; label: string }> = {
  free: { tone: 'success', label: 'Free' },
  starter: { tone: 'warning', label: 'Starter' },
  growth: { tone: 'brand', label: 'Growth' },
  pro: { tone: 'brand', label: 'Growth' }, // tier-key alias used in some catalogs
  scale: { tone: 'premium', label: 'Scale' },
};

type Size = 'xs' | 'sm';

const SIZE_CLASSES: Record<Size, string> = {
  xs: 'rounded-full px-2 py-0.5 text-xs font-medium',
  sm: 'rounded-full px-2.5 py-0.5 text-xs font-medium',
};

export type StatusBadgeProps = {
  /** Status string lookup (legacy). One of: paid, pending, refunded, etc. */
  status?: string;
  /** Direct tone override; pairs with `label`. */
  tone?: Tone;
  /** Tier preset (free / starter / growth / scale). Wins over `status`. */
  tier?: keyof typeof TIER_TO_TONE;
  /** Explicit label. Defaults to humanized `status` or tier name. */
  label?: ReactNode;
  /** Pill size. */
  size?: Size;
  /** Lowercase the label or render as-is. Defaults to capitalize. */
  caseStyle?: 'capitalize' | 'as-is';
};

export function StatusBadge({
  status,
  tone,
  tier,
  label,
  size = 'sm',
  caseStyle = 'capitalize',
}: StatusBadgeProps) {
  let resolvedTone: Tone = 'neutral';
  let resolvedLabel: ReactNode = label;

  if (tier && TIER_TO_TONE[tier]) {
    resolvedTone = TIER_TO_TONE[tier].tone;
    if (resolvedLabel === undefined) {
      resolvedLabel = TIER_TO_TONE[tier].label;
    }
  } else if (tone) {
    resolvedTone = tone;
    if (resolvedLabel === undefined && status) {
      resolvedLabel = status.replace(/[-_]/g, ' ');
    }
  } else if (status) {
    resolvedTone = STATUS_TO_TONE[status.toLowerCase()] ?? 'neutral';
    if (resolvedLabel === undefined) {
      resolvedLabel = status.replace(/[-_]/g, ' ');
    }
  }

  const caseCls = caseStyle === 'capitalize' ? 'capitalize' : '';
  return (
    <span
      className={`inline-flex items-center ring-1 ring-inset ${SIZE_CLASSES[size]} ${caseCls} ${TONE_CLASSES[resolvedTone]}`}
    >
      {resolvedLabel}
    </span>
  );
}
