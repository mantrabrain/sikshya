import { forwardRef } from 'react';
import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import { formatDateInput, formatDateTimeLocalInput, parseDateInput, parseDateTimeLocalInput } from '../../lib/localDateTime';

type BaseProps = {
  label?: string;
  description?: string;
  disabled?: boolean;
  className?: string;
};

type DateOnlyProps = BaseProps & {
  kind: 'date';
  value: string;
  onChange: (next: string) => void;
  placeholder?: string;
};

type DateTimeProps = BaseProps & {
  kind: 'datetime';
  value: string;
  onChange: (next: string) => void;
  placeholder?: string;
};

type Props = DateOnlyProps | DateTimeProps;

const Input = forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(function Input(props, ref) {
  const { className = '', ...rest } = props;
  return (
    <input
      ref={ref}
      {...rest}
      className={`w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-brand-400 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-700 dark:bg-slate-950 dark:text-white ${className}`}
    />
  );
});

/**
 * Shared date / datetime picker field using `react-datepicker` (no HTML5 date inputs).
 * Stores values in the same string formats we previously used:
 * - date: YYYY-MM-DD
 * - datetime: YYYY-MM-DDTHH:mm
 */
export function DateTimePickerField(props: Props) {
  const {
    label,
    description,
    disabled = false,
    className = '',
    placeholder = props.kind === 'date' ? 'Select a date…' : 'Select date & time…',
  } = props;

  const selected =
    props.kind === 'date' ? parseDateInput(props.value) : parseDateTimeLocalInput(props.value);

  const onPick = (d: Date | null) => {
    if (props.kind === 'date') {
      props.onChange(formatDateInput(d));
    } else {
      props.onChange(formatDateTimeLocalInput(d));
    }
  };

  return (
    <label className={`block text-sm ${className}`}>
      {label ? <span className="text-slate-600 dark:text-slate-400">{label}</span> : null}
      {description ? <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">{description}</span> : null}
      <div className="mt-1">
        <DatePicker
          selected={selected}
          onChange={onPick}
          disabled={disabled}
          placeholderText={placeholder}
          dateFormat={props.kind === 'date' ? 'yyyy-MM-dd' : 'yyyy-MM-dd HH:mm'}
          showTimeSelect={props.kind === 'datetime'}
          timeIntervals={15}
          timeCaption="Time"
          customInput={<Input />}
          popperPlacement="bottom-start"
        />
      </div>
    </label>
  );
}

