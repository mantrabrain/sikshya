type UnitOpt = { value: string; label: string };

export function NumberWithUnitField(props: {
  id: string;
  label: string;
  hint?: string;
  value: string;
  onValueChange: (v: string) => void;
  unit: string;
  onUnitChange: (u: string) => void;
  units: UnitOpt[];
  fieldClassName: string;
  labelClassName: string;
  hintClassName?: string;
  placeholder?: string;
}) {
  const {
    id,
    label,
    hint,
    value,
    onValueChange,
    unit,
    onUnitChange,
    units,
    fieldClassName,
    labelClassName,
    hintClassName,
    placeholder,
  } = props;

  return (
    <div>
      <label className={labelClassName} htmlFor={id}>
        {label}
      </label>
      {hint ? <p className={hintClassName || 'mt-1 text-xs text-slate-500 dark:text-slate-400'}>{hint}</p> : null}

      <div className="mt-1.5 flex flex-wrap items-stretch gap-2">
        <div className="min-w-[min(100%,14rem)] flex-1">
          <input
            id={id}
            inputMode="decimal"
            className={fieldClassName}
            value={value}
            onChange={(e) => onValueChange(e.target.value)}
            placeholder={placeholder}
          />
        </div>
        <div className="w-[10.5rem] min-w-[9.5rem]">
          <select
            className={fieldClassName}
            value={unit}
            onChange={(e) => onUnitChange(e.target.value)}
            aria-label={`${label} unit`}
          >
            {units.map((u) => (
              <option key={u.value} value={u.value}>
                {u.label}
              </option>
            ))}
          </select>
        </div>
      </div>
    </div>
  );
}

