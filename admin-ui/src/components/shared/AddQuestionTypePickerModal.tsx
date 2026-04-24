import { useEffect, useMemo, useRef } from 'react';
import { NavIcon } from '../NavIcon';
import { ButtonPrimary } from './buttons';
import { ApiErrorPanel } from './ApiErrorPanel';

export type QuestionType =
  | 'true_false'
  | 'multiple_choice'
  | 'multiple_response'
  | 'short_answer'
  | 'fill_blank'
  | 'ordering'
  | 'matching'
  | 'essay';

type PickerOpt = {
  type: QuestionType;
  label: string;
  hint: string;
  icon: 'helpCircle' | 'puzzle' | 'layers' | 'plusDocument';
};

export const QUESTION_PICKER_TYPES: PickerOpt[] = [
  { type: 'true_false', label: 'True / False', hint: 'Fast checks with one correct answer.', icon: 'puzzle' },
  {
    type: 'multiple_choice',
    label: 'Multiple choice',
    hint: 'One correct answer from a list of options.',
    icon: 'puzzle',
  },
  {
    type: 'multiple_response',
    label: 'Multiple response',
    hint: 'Learner can select more than one correct option.',
    icon: 'layers',
  },
  {
    type: 'short_answer',
    label: 'Short answer',
    hint: 'Learner types a short text response.',
    icon: 'plusDocument',
  },
  {
    type: 'fill_blank',
    label: 'Fill in the blank',
    hint: 'A short prompt with a missing word or phrase.',
    icon: 'puzzle',
  },
  {
    type: 'matching',
    label: 'Matching',
    hint: 'Pair items from two columns.',
    icon: 'layers',
  },
  {
    type: 'ordering',
    label: 'Ordering',
    hint: 'Reorder items into the correct sequence.',
    icon: 'layers',
  },
  {
    type: 'essay',
    label: 'Essay',
    hint: 'Long-form response, typically manually graded.',
    icon: 'helpCircle',
  },
];

const FIELD_INPUT =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500';
const FIELD_LABEL = 'block text-sm font-medium text-slate-800 dark:text-slate-200';
const FIELD_HINT = 'mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400';

type Props = {
  open: boolean;
  heading?: string;
  description?: string;
  questionType: QuestionType;
  onQuestionTypeChange: (t: QuestionType) => void;
  title: string;
  onTitleChange: (v: string) => void;
  onClose: () => void;
  onSubmit: () => void;
  busy: boolean;
  error?: unknown;
  submitLabel?: string;
  allowedTypes?: QuestionType[];
};

export function AddQuestionTypePickerModal(props: Props) {
  const {
    open,
    heading = 'Add a question',
    description = 'Pick a question type, then give it a clear name. You can refine answers after it’s created.',
    questionType,
    onQuestionTypeChange,
    title,
    onTitleChange,
    onClose,
    onSubmit,
    busy,
    error,
    submitLabel = 'Create question',
    allowedTypes,
  } = props;

  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (!open) {
      return;
    }
    const t = window.setTimeout(() => {
      inputRef.current?.focus();
      inputRef.current?.select();
    }, 60);
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
      if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        onSubmit();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => {
      window.clearTimeout(t);
      window.removeEventListener('keydown', onKey);
    };
  }, [open, onClose, onSubmit]);

  const types = useMemo(() => {
    const base = allowedTypes ? QUESTION_PICKER_TYPES.filter((x) => allowedTypes.includes(x.type)) : QUESTION_PICKER_TYPES;
    return base;
  }, [allowedTypes]);

  if (!open) {
    return null;
  }

  const active = types.find((x) => x.type === questionType) || types[0];

  return (
    <div
      className="fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-[2px] sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="sikshya-add-question-title"
    >
      <button type="button" className="absolute inset-0 z-0 cursor-default" aria-label="Close" onClick={onClose} />
      <div className="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div className="border-b border-slate-200/70 px-6 py-5 dark:border-slate-800">
          <h2 id="sikshya-add-question-title" className="text-lg font-semibold text-slate-900 dark:text-white">
            {heading}
          </h2>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p>
        </div>

        <div className="grid max-h-[calc(90vh-8rem)] grid-cols-1 gap-0 overflow-y-auto sm:grid-cols-[18rem_1fr]">
          <aside className="border-b border-slate-200/60 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-950/25 sm:border-b-0 sm:border-r">
            <div className="flex items-center justify-between">
              <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Select question type
              </div>
              <span className="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700">
                {types.length}
              </span>
            </div>
            <ul className="mt-3 space-y-1.5">
              {types.map((t) => {
                const isActive = t.type === questionType;
                return (
                  <li key={t.type}>
                    <button
                      type="button"
                      onClick={() => onQuestionTypeChange(t.type)}
                      className={`flex w-full items-start gap-3 rounded-xl border px-3 py-2.5 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 ${
                        isActive
                          ? 'border-brand-500 bg-brand-50 text-brand-900 dark:border-brand-400 dark:bg-brand-950/45 dark:text-brand-100'
                          : 'border-slate-200 bg-white text-slate-800 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600'
                      }`}
                    >
                      <span
                        className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${
                          isActive ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-200'
                        }`}
                        aria-hidden
                      >
                        <NavIcon name={t.icon} className="h-4 w-4" />
                      </span>
                      <span className="min-w-0">
                        <span className="block text-sm font-semibold leading-snug">{t.label}</span>
                        <span className="mt-0.5 block text-xs leading-snug text-slate-500 dark:text-slate-400">{t.hint}</span>
                      </span>
                    </button>
                  </li>
                );
              })}
            </ul>
          </aside>

          <div className="p-6">
            {error ? (
              <div className="mb-4">
                <ApiErrorPanel error={error} title="Could not create question" onRetry={() => void 0} />
              </div>
            ) : null}

            <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950/20">
              <div className="flex items-start gap-3">
                <div className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white">
                  <NavIcon name={active?.icon || 'helpCircle'} className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <div className="text-sm font-semibold text-slate-900 dark:text-white">{active?.label || 'Question'}</div>
                  <p className="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{active?.hint || ''}</p>
                </div>
              </div>

              <label className={`${FIELD_LABEL} mt-4`} htmlFor="sikshya-add-question-title-input">
                Question name
              </label>
              <p className={`${FIELD_HINT} mt-0`}>Internal title for your library. Learners see the question text you write next.</p>
              <input
                ref={inputRef}
                id="sikshya-add-question-title-input"
                type="text"
                className={FIELD_INPUT}
                placeholder="e.g. Safety basics — Q1"
                value={title}
                onChange={(e) => onTitleChange(e.target.value)}
              />

              <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300">
                Tip: Press <span className="font-mono font-semibold">Ctrl</span> + <span className="font-mono font-semibold">Enter</span> to create quickly.
              </div>
            </div>

            <div className="mt-6 flex flex-wrap justify-end gap-2">
              <button
                type="button"
                className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                onClick={onClose}
                disabled={busy}
              >
                Cancel
              </button>
              <ButtonPrimary type="button" className="px-4 py-2.5" disabled={busy || !title.trim()} onClick={onSubmit}>
                {busy ? 'Creating…' : submitLabel}
              </ButtonPrimary>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

