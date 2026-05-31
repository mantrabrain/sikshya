/**
 * Shared loading spinner — one component, three semantic variants.
 *
 * Usage:
 *   <Spinner />                          // small (h-4 w-4), brand-tone (default)
 *   <Spinner variant="on-primary" />     // for use INSIDE a primary button (white)
 *   <Spinner variant="on-light" />       // for use on light/outline buttons (slate→brand)
 *   <Spinner size="sm" />                // h-3 w-3 micro
 *   <Spinner size="md" />                // h-5 w-5 row-level
 *   <Spinner size="lg" />                // h-6 w-6 panel-level
 *
 * Replaces the inline border classes that LicensePage and a few other pages
 * rolled — keeps spinner color/border-width/animation in one place.
 */

type Variant = 'on-primary' | 'on-light' | 'neutral';
type Size = 'sm' | 'md' | 'lg';

const SIZE: Record<Size, string> = {
  sm: 'h-3 w-3 border',
  md: 'h-4 w-4 border-2',
  lg: 'h-6 w-6 border-2',
};

const VARIANT: Record<Variant, string> = {
  // White on a brand-colored background (primary buttons).
  'on-primary': 'border-white/40 border-t-white',
  // Brand-toned spinner — for outline / ghost / icon-only buttons on light
  // surfaces (so a white spinner isn't invisible).
  'on-light': 'border-slate-300 border-t-brand-600 dark:border-slate-600 dark:border-t-brand-400',
  // Mid-tone neutral spinner.
  neutral: 'border-slate-500/30 border-t-white',
};

export function Spinner({
  variant = 'on-light',
  size = 'md',
  className = '',
}: {
  variant?: Variant;
  size?: Size;
  className?: string;
}) {
  return (
    <span
      role="status"
      aria-label="Loading"
      className={`inline-block shrink-0 animate-spin rounded-full align-[-2px] ${SIZE[size]} ${VARIANT[variant]} ${className}`.trim()}
    />
  );
}
