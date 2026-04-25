import type { ReactNode } from 'react';

export function Card({
  children,
  className = '',
  padding = 'p-0',
}: {
  children: ReactNode;
  className?: string;
  padding?: string;
}) {
  return (
    <div
      className={`overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm ${padding} ${className}`}
    >
      {children}
    </div>
  );
}
