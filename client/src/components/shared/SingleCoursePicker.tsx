import { MultiCoursePicker } from './MultiCoursePicker';

/**
 * Single-course filter using the same modal picker UX as {@link MultiCoursePicker}
 * (Create Coupon, Bundles, Prerequisites). Prefer this over ad-hoc typeaheads so all
 * course selection looks consistent.
 */
export function SingleCoursePicker(props: {
  value: number;
  onChange: (id: number) => void;
  label?: string;
  placeholder?: string;
  hint?: string;
  className?: string;
  perPage?: number;
  reserveHintSpace?: boolean;
  density?: 'comfortable' | 'compact';
}) {
  const { value, onChange, label, placeholder, hint, className, perPage, reserveHintSpace, density } = props;

  return (
    <div className={className}>
      {label ? (
        <p className="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{label}</p>
      ) : null}
      <MultiCoursePicker
        value={value > 0 ? [value] : []}
        onChange={(ids) => {
          if (!ids.length) {
            onChange(0);
            return;
          }
          onChange(ids[ids.length - 1] ?? 0);
        }}
        maxSelection={1}
        placeholder={placeholder ?? 'Click to choose a course…'}
        hint={hint}
        title="Select a course"
        perPage={perPage ?? 20}
        reserveHintSpace={reserveHintSpace}
        density={density}
      />
    </div>
  );
}
