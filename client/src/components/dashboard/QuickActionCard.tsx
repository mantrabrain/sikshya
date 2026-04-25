import { NavIcon } from '../NavIcon';

/**
 * Large touch target linking to a primary LMS workflow (or `onClick` for in-app flows).
 */
export function QuickActionCard({
  href,
  onClick,
  title,
  description,
  icon,
}: {
  href?: string;
  onClick?: () => void;
  title: string;
  description: string;
  /** Key from `assets/admin/icons/icons.json`. */
  icon: string;
}) {
  const className =
    'group flex w-full items-start gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 text-left shadow-sm transition-all hover:border-brand-300/60 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-brand-600/40';

  const inner = (
    <>
      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 transition-colors group-hover:bg-brand-50 group-hover:text-brand-600 dark:bg-slate-800 dark:text-slate-400 dark:group-hover:bg-brand-950/50 dark:group-hover:text-brand-400">
        <NavIcon name={icon} className="h-5 w-5" />
      </span>
      <span className="min-w-0 flex-1">
        <span className="flex items-center gap-1 font-semibold text-slate-900 dark:text-white">
          {title}
          <NavIcon
            name="chevronRight"
            className="h-4 w-4 shrink-0 text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-slate-600"
          />
        </span>
        <span className="mt-0.5 block text-sm text-slate-500 dark:text-slate-400">{description}</span>
      </span>
    </>
  );

  if (onClick) {
    return (
      <button type="button" onClick={onClick} className={className}>
        {inner}
      </button>
    );
  }

  return (
    <a href={href || '#'} className={className}>
      {inner}
    </a>
  );
}
