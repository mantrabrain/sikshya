import type { ReactNode } from 'react';

/**
 * Optional helper under a control. Always reserves vertical space so grid/toolbar
 * columns stay aligned when some fields have copy and others do not.
 */
export function FieldHint({ children }: { children?: ReactNode }) {
  const has = children != null && children !== '';
  return (
    <div className="min-h-[2.25rem] shrink-0 text-xs leading-snug text-slate-500 dark:text-slate-400">
      {has ? (
        children
      ) : (
        <span className="inline-block min-h-[1.125rem] select-none opacity-0" aria-hidden>
          &nbsp;
        </span>
      )}
    </div>
  );
}
