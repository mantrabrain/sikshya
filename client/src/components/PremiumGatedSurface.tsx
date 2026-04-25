import type { ReactNode } from 'react';

type Props = {
  children: ReactNode;
  /** Extra classes on the outer positioning wrapper (usually leave default). */
  className?: string;
};

/**
 * Min height for gated regions so overlays fill the visible admin canvas (below top bar + main padding).
 * Tweak if shell chrome height changes.
 */
export const PREMIUM_GATE_VIEWPORT_MIN_H =
  'min-h-[calc(100dvh-7rem)] sm:min-h-[calc(100dvh-7.5rem)]';

/** Shared shell for gold “premium lock” cards (plan upgrade + addon enable). Full width of parent. */
export const PREMIUM_LOCK_CARD_CLASS =
  'relative w-full overflow-hidden rounded-3xl border border-amber-300/70 bg-gradient-to-b from-amber-50 via-white to-amber-100/40 shadow-[0_32px_100px_-18px_rgba(180,83,9,0.42)] ring-1 ring-amber-200/80 backdrop-blur-xl dark:border-amber-600/35 dark:from-amber-950/95 dark:via-stone-900 dark:to-amber-950/90 dark:shadow-[0_32px_100px_-18px_rgba(0,0,0,0.55)] dark:ring-amber-800/40';

/**
 * Full width/height overlay: warm amber/gold “premium” scrim over gated content.
 * Centers children; use for plan-lock and addon-off premium moments.
 */
export function PremiumGatedSurface(props: Props) {
  const { children, className = '' } = props;

  return (
    <div
      className={`pointer-events-none absolute inset-0 z-20 flex min-h-full w-full flex-col ${className}`.trim()}
      data-sikshya-premium-gate
    >
      {/* Deep base + gold wash */}
      <div
        className="pointer-events-auto absolute inset-0 bg-gradient-to-br from-stone-950/94 via-amber-950/88 to-stone-950/96 backdrop-blur-[14px] dark:from-stone-950/97 dark:via-amber-950/92 dark:to-stone-950/98"
        aria-hidden
      />
      <div
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_100%_70%_at_50%_0%,rgba(251,191,36,0.28),transparent_58%)] dark:bg-[radial-gradient(ellipse_100%_70%_at_50%_0%,rgba(245,158,11,0.18),transparent_58%)]"
        aria-hidden
      />
      <div
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_70%_50%_at_90%_80%,rgba(234,179,8,0.14),transparent_55%)] dark:bg-[radial-gradient(ellipse_70%_50%_at_90%_80%,rgba(217,119,6,0.12),transparent_55%)]"
        aria-hidden
      />
      <div
        className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_bottom,transparent,rgba(69,26,3,0.5))] dark:bg-[linear-gradient(to_bottom,transparent,rgba(28,25,23,0.65))]"
        aria-hidden
      />
      {/* Subtle gold film */}
      <div
        className="pointer-events-none absolute inset-0 bg-amber-400/[0.06] mix-blend-overlay dark:bg-amber-500/[0.05]"
        aria-hidden
      />

      {/* Content — vertically and horizontally centered, scrolls on small viewports */}
      <div className="pointer-events-none relative z-10 flex min-h-full w-full flex-1 items-stretch justify-center overflow-y-auto p-4 sm:p-6 md:p-8 lg:p-10">
        <div className="pointer-events-auto flex w-full max-w-none min-w-0 flex-1 flex-col justify-center py-4">{children}</div>
      </div>
    </div>
  );
}
