import type { ReactNode } from 'react';

const basePrimary =
  'inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-1';
const baseSecondary =
  'inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-1';

export function ButtonPrimary({
  children,
  type = 'button',
  onClick,
  disabled,
  className = '',
  form,
}: {
  children: ReactNode;
  type?: 'button' | 'submit';
  onClick?: () => void;
  disabled?: boolean;
  className?: string;
  /** Associate submit with a form elsewhere in the document. */
  form?: string;
}) {
  return (
    <button
      type={type}
      form={form}
      onClick={onClick}
      disabled={disabled}
      className={`${basePrimary} disabled:opacity-50 ${className}`}
    >
      {children}
    </button>
  );
}

export function ButtonSecondary({
  children,
  type = 'button',
  onClick,
  disabled,
  className = '',
  form,
}: {
  children: ReactNode;
  type?: 'button' | 'submit';
  onClick?: () => void;
  disabled?: boolean;
  className?: string;
  form?: string;
}) {
  return (
    <button
      type={type}
      form={form}
      onClick={onClick}
      disabled={disabled}
      className={`${baseSecondary} disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 ${className}`}
    >
      {children}
    </button>
  );
}

export function LinkButtonPrimary({
  href,
  children,
  className = '',
}: {
  href: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <a href={href} className={`${basePrimary} no-underline ${className}`}>
      {children}
    </a>
  );
}

export function LinkButtonSecondary({
  href,
  children,
  className = '',
}: {
  href: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <a href={href} className={`${baseSecondary} no-underline ${className}`}>
      {children}
    </a>
  );
}
