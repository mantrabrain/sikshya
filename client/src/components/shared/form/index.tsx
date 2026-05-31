import type {
  ChangeEvent,
  InputHTMLAttributes,
  ReactNode,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
} from 'react';

/**
 * Canonical form atoms.
 *
 * Why these exist: roughly 40+ inputs/selects/textareas/checkboxes across the
 * admin shipped with subtly different focus rings, missing dark-mode pairs,
 * or rounded-lg instead of rounded-xl. New pages that import these atoms
 * inherit the design system automatically; existing pages can be migrated
 * page-at-a-time.
 *
 * Conventions enforced:
 *   - Inputs/selects/textareas: rounded-xl, slate-200/slate-600 border, focus
 *     ring brand-500/20 width-2, dark-mode background slate-800, placeholder
 *     slate-400/slate-500.
 *   - Checkboxes: h-4 w-4, rounded, brand-600 fill, focus ring brand-500/40.
 *   - Labels: eyebrow text-xs uppercase + 'block' OR regular text-sm + font-medium.
 *   - Hints: mt-1 text-xs slate-500/400.
 *   - Errors: mt-1 text-xs red-600/red-400 + role="alert".
 *   - All variants carry disabled:opacity-50 disabled:cursor-not-allowed.
 */

const INPUT_BASE =
  'block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 ' +
  'placeholder:text-slate-400 ' +
  'focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 ' +
  'disabled:cursor-not-allowed disabled:opacity-50 ' +
  'dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500';

const INPUT_ERROR =
  'border-red-500 focus:border-red-500 focus:ring-red-500/30 ' +
  'dark:border-red-500 dark:focus:border-red-400 dark:focus:ring-red-400/30';

const CHECKBOX_BASE =
  'h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 ' +
  'focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:ring-offset-1 ' +
  'disabled:cursor-not-allowed disabled:opacity-50 ' +
  'dark:border-slate-600 dark:bg-slate-700 dark:focus:ring-offset-slate-900';

const LABEL_EYEBROW =
  'block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';

const LABEL_REGULAR = 'block text-sm font-medium text-slate-700 dark:text-slate-300';

const HINT = 'mt-1 text-xs text-slate-500 dark:text-slate-400';

const ERROR_TEXT = 'mt-1 text-xs text-red-600 dark:text-red-400';

export type FormInputProps = InputHTMLAttributes<HTMLInputElement> & {
  invalid?: boolean;
};

export function FormInput({ className = '', invalid, ...rest }: FormInputProps) {
  const cls = `${INPUT_BASE} ${invalid ? INPUT_ERROR : ''} ${className}`.trim();
  return (
    <input
      {...rest}
      aria-invalid={invalid || undefined}
      aria-required={rest.required || undefined}
      className={cls}
    />
  );
}

export type FormSelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  invalid?: boolean;
};

export function FormSelect({ className = '', invalid, children, ...rest }: FormSelectProps) {
  const cls = `${INPUT_BASE} ${invalid ? INPUT_ERROR : ''} ${className}`.trim();
  return (
    <select
      {...rest}
      aria-invalid={invalid || undefined}
      aria-required={rest.required || undefined}
      className={cls}
    >
      {children}
    </select>
  );
}

export type FormTextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
  invalid?: boolean;
};

export function FormTextarea({ className = '', invalid, ...rest }: FormTextareaProps) {
  const cls = `${INPUT_BASE} resize-y min-h-[6rem] ${invalid ? INPUT_ERROR : ''} ${className}`.trim();
  return (
    <textarea
      {...rest}
      aria-invalid={invalid || undefined}
      aria-required={rest.required || undefined}
      className={cls}
    />
  );
}

export type FormCheckboxProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  /** Adjacent label text. If omitted, render the bare input only. */
  label?: ReactNode;
  /** Optional helper text rendered under the label. */
  hint?: ReactNode;
};

/**
 * Checkbox + label + optional hint. Wraps the input + label in a flex row so
 * spacing stays consistent regardless of label length.
 */
export function FormCheckbox({ className = '', label, hint, id, ...rest }: FormCheckboxProps) {
  const input = (
    <input
      {...rest}
      type="checkbox"
      id={id}
      className={`${CHECKBOX_BASE} ${className}`.trim()}
    />
  );
  if (!label) {
    return input;
  }
  return (
    <label
      htmlFor={id}
      className={`flex items-start gap-2 text-sm ${rest.disabled ? 'opacity-50' : ''}`}
    >
      <span className="mt-0.5">{input}</span>
      <span className="min-w-0">
        <span className="text-slate-700 dark:text-slate-200">{label}</span>
        {hint ? <span className={`mt-0.5 block ${HINT.replace('mt-1 ', '')}`}>{hint}</span> : null}
      </span>
    </label>
  );
}

export function FormLabel({
  htmlFor,
  variant = 'regular',
  children,
  className = '',
  required,
}: {
  htmlFor?: string;
  /** 'eyebrow' = text-xs uppercase; 'regular' = text-sm font-medium */
  variant?: 'eyebrow' | 'regular';
  children: ReactNode;
  className?: string;
  required?: boolean;
}) {
  const base = variant === 'eyebrow' ? LABEL_EYEBROW : LABEL_REGULAR;
  return (
    <label htmlFor={htmlFor} className={`${base} ${className}`.trim()}>
      {children}
      {required ? (
        <span className="ml-0.5 text-red-500" aria-hidden="true">
          *
        </span>
      ) : null}
    </label>
  );
}

export function FormHint({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <p className={`${HINT} ${className}`.trim()}>{children}</p>;
}

export function FormError({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <p role="alert" className={`${ERROR_TEXT} ${className}`.trim()}>
      {children}
    </p>
  );
}

/**
 * Field group: label + control + optional hint/error.
 *
 * Convenience component for the most common form shape. Pass the control as
 * the child, and the group wraps it with consistent spacing.
 */
export function FormField({
  label,
  htmlFor,
  required,
  hint,
  error,
  variant = 'regular',
  children,
  className = '',
}: {
  label: ReactNode;
  htmlFor?: string;
  required?: boolean;
  hint?: ReactNode;
  error?: ReactNode;
  variant?: 'eyebrow' | 'regular';
  children: ReactNode;
  className?: string;
}) {
  return (
    <div className={`space-y-1.5 ${className}`.trim()}>
      <FormLabel htmlFor={htmlFor} variant={variant} required={required}>
        {label}
      </FormLabel>
      {children}
      {error ? <FormError>{error}</FormError> : hint ? <FormHint>{hint}</FormHint> : null}
    </div>
  );
}

// Re-export common types so consumers can extend.
export type { ChangeEvent };
