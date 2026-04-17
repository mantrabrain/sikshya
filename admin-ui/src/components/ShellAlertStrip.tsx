import type { ShellAlert } from '../types';

const variantBar: Record<ShellAlert['variant'], string> = {
  info: 'border-l-sky-500 bg-sky-50/90 text-sky-950 dark:border-l-sky-400 dark:bg-sky-950/35 dark:text-sky-50',
  success: 'border-l-emerald-500 bg-emerald-50/90 text-emerald-950 dark:border-l-emerald-400 dark:bg-emerald-950/35 dark:text-emerald-50',
  warning: 'border-l-amber-500 bg-amber-50/90 text-amber-950 dark:border-l-amber-400 dark:bg-amber-950/35 dark:text-amber-50',
  error: 'border-l-rose-500 bg-rose-50/90 text-rose-950 dark:border-l-rose-400 dark:bg-rose-950/35 dark:text-rose-50',
};

export function ShellAlertStrip({ alerts }: { alerts: ShellAlert[] }) {
  if (!alerts.length) return null;

  return (
    <div
      className="-mx-6 -mt-6 mb-6 divide-y divide-slate-200/80 border-b border-slate-200/80 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900/95"
      role="region"
      aria-label="Site notices"
    >
      {alerts.map((a) => (
        <div
          key={a.id}
          className={`flex flex-wrap items-start gap-3 border-l-4 px-6 py-3.5 text-sm ${variantBar[a.variant] || variantBar.info}`}
        >
          <div className="min-w-0 flex-1">
            {/* Single text block avoids a “title only” frame before the body paints. */}
            <p className="m-0 text-sm leading-relaxed">
              <span className="font-semibold">{a.title}</span>
              {a.message ? (
                <span className="mt-1 block text-xs font-normal leading-relaxed opacity-90">{a.message}</span>
              ) : null}
            </p>
            {a.actions?.length ? (
              <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                {a.actions.map((act, i) => (
                  <a
                    key={`${a.id}-act-${i}`}
                    href={act.href}
                    target={act.external ? '_blank' : undefined}
                    rel={act.external ? 'noopener noreferrer' : undefined}
                    className="text-xs font-semibold underline decoration-current/40 underline-offset-2 hover:decoration-current"
                  >
                    {act.label}
                  </a>
                ))}
              </div>
            ) : null}
          </div>
        </div>
      ))}
    </div>
  );
}
