import { useEffect, useId, useRef, useState } from 'react';
import { NavIcon } from '../NavIcon';
import { resolveTriggerOptionsForValue } from '../../lib/emailTriggerEvents';

function IconEnvelope(props: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className={props.className} aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
    </svg>
  );
}

function IconBolt(props: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className={props.className} aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
    </svg>
  );
}

function OptionRowIcon({ kind }: { kind: 'none' | 'event' }) {
  if (kind === 'none') {
    return (
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-950/60 dark:text-sky-300">
        <IconEnvelope className="h-5 w-5" />
      </span>
    );
  }
  return (
    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
      <IconBolt className="h-5 w-5" />
    </span>
  );
}

type Props = {
  value: string;
  onChange: (nextKey: string) => void;
  disabled?: boolean;
};

/**
 * Rich trigger picker: title + key badge + description per row (matches Sikshya admin reference UI).
 */
export function TriggerEventSelect(props: Props) {
  const { value, onChange, disabled } = props;
  const listId = useId();
  const wrapRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const options = resolveTriggerOptionsForValue(value);
  const selected = options.find((o) => o.key === value) ?? options[0];

  useEffect(() => {
    if (!open) {
      return;
    }
    const onDoc = (e: MouseEvent) => {
      const el = wrapRef.current;
      if (el && !el.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [open]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setOpen(false);
      }
    };
    if (open) {
      window.addEventListener('keydown', onKey);
      return () => window.removeEventListener('keydown', onKey);
    }
  }, [open]);

  return (
    <div ref={wrapRef} className="relative">
      <button
        type="button"
        disabled={disabled}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={listId}
        onClick={() => !disabled && setOpen((o) => !o)}
        className={`flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left shadow-sm transition dark:border-slate-600 dark:bg-slate-800 ${
          disabled ? 'cursor-not-allowed opacity-60' : 'hover:border-slate-300 dark:hover:border-slate-500'
        }`}
      >
        <OptionRowIcon kind={selected.kind} />
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-sm font-semibold text-slate-900 dark:text-slate-100">{selected.title}</span>
            <code className="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-slate-600 dark:bg-slate-900 dark:text-slate-300">
              {selected.badgeLabel}
            </code>
          </div>
        </div>
        <NavIcon name={open ? 'chevronDown' : 'chevronDown'} className={`h-5 w-5 shrink-0 text-slate-400 transition ${open ? 'rotate-180' : ''}`} />
      </button>

      {open ? (
        <div
          id={listId}
          role="listbox"
          className="absolute left-0 right-0 z-50 mt-1 max-h-[min(24rem,70vh)] overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-600 dark:bg-slate-900"
        >
          <p className="px-3 py-2 text-xs leading-snug text-slate-500 dark:text-slate-400">
            Select an event to trigger this email, or choose “No event” for sequence / manual use.
          </p>
          <div className="border-t border-slate-100 dark:border-slate-800" />
          {options.map((opt) => {
            const isActive = opt.key === value;
            return (
              <button
                key={opt.key}
                type="button"
                role="option"
                aria-selected={isActive}
                onClick={() => {
                  onChange(opt.key);
                  setOpen(false);
                }}
                className={`flex w-full items-start gap-3 px-3 py-2.5 text-left transition ${
                  isActive
                    ? 'bg-sky-50 dark:bg-sky-950/40'
                    : 'hover:bg-slate-50 dark:hover:bg-slate-800/80'
                }`}
              >
                <OptionRowIcon kind={opt.kind} />
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <span
                      className={`text-sm font-semibold ${
                        isActive ? 'text-sky-800 dark:text-sky-200' : 'text-slate-900 dark:text-slate-100'
                      }`}
                    >
                      {opt.title}
                    </span>
                    <code
                      className={`rounded-md px-1.5 py-0.5 font-mono text-[11px] font-medium ${
                        isActive
                          ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-200'
                          : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                      }`}
                    >
                      {opt.badgeLabel}
                    </code>
                  </div>
                  <p className="mt-0.5 text-xs leading-snug text-slate-500 dark:text-slate-400">{opt.description}</p>
                </div>
                {isActive ? (
                  <span className="mt-0.5 shrink-0 text-sky-600 dark:text-sky-400" aria-hidden>
                    <NavIcon name="iconPublish" className="h-5 w-5" />
                  </span>
                ) : (
                  <span className="w-5 shrink-0" aria-hidden />
                )}
              </button>
            );
          })}
        </div>
      ) : null}
    </div>
  );
}
