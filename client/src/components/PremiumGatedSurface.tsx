import type { CSSProperties, ReactNode } from 'react';

type Props = {
  children: ReactNode;
  /** Extra classes on the outer positioning wrapper (usually leave default). */
  className?: string;
};

/**
 * Min height for gated regions so overlays fill the visible admin canvas
 * (below top bar + main padding).
 */
export const PREMIUM_GATE_VIEWPORT_MIN_H =
  'min-h-[calc(100dvh-7rem)] sm:min-h-[calc(100dvh-7.5rem)]';

/**
 * Shared shell for the upgrade card. White surface with rounded corners and a
 * generous shadow. `overflow-hidden` so the brand-gradient hero header inside
 * clips cleanly to the rounded top.
 */
export const PREMIUM_LOCK_CARD_CLASS =
  'relative w-full max-w-2xl mx-auto overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_30px_70px_-25px_rgba(15,23,42,0.25)] dark:border-slate-700 dark:bg-slate-900 dark:shadow-[0_30px_70px_-25px_rgba(0,0,0,0.6)]';

/** Shared Pro / upgrade hero wash — matches {@link PlanUpgradeOverlay} header. */
export const PREMIUM_HERO_GRADIENT_CLASS =
  'bg-gradient-to-br from-accent-600 via-accent-700 to-brand-700';

/** Inline radial highlight used on compact Pro surfaces (modal type cards, etc.). */
export const PREMIUM_HERO_RADIAL_SHEEN_STYLE: CSSProperties = {
  background:
    'radial-gradient(circle at top right, rgba(255,255,255,0.22), transparent 55%), radial-gradient(circle at bottom left, rgba(255,255,255,0.08), transparent 50%)',
};

/**
 * Frosted backdrop over gated content. Light + brand-tinted radial glow at the
 * top so users immediately see the upgrade card without a heavy dark scrim.
 */
export function PremiumGatedSurface(props: Props) {
  const { children, className = '' } = props;

  return (
    <div
      className={`pointer-events-none absolute inset-0 z-20 flex min-h-full w-full flex-col ${className}`.trim()}
      data-sikshya-premium-gate
    >
      {/* Light frosted backdrop */}
      <div
        className="pointer-events-auto absolute inset-0 bg-white/90 backdrop-blur-sm dark:bg-slate-950/88"
        aria-hidden
      />
      {/* Subtle brand-tinted radial glow at the top — calls attention to the
          card without dominating. Uses Sikshya logo navy + accent purple. */}
      <div
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_70%_45%_at_50%_5%,rgba(44,91,168,0.13),transparent_60%)] dark:bg-[radial-gradient(ellipse_70%_45%_at_50%_5%,rgba(122,46,128,0.15),transparent_60%)]"
        aria-hidden
      />

      {/* Content — vertically centered. No internal scroll: the upgrade card
          is sized to fit comfortably in the gated viewport; if a tiny viewport
          ever truncates it, the host page's natural scroll handles it (no
          nested scrollbar inside the gated content area). */}
      <div className="pointer-events-none relative z-10 flex min-h-full w-full flex-1 items-center justify-center p-4 sm:p-6 md:p-8">
        <div className="pointer-events-auto w-full max-w-2xl">{children}</div>
      </div>
    </div>
  );
}
