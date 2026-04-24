import { useEffect, useMemo, useRef, useState } from 'react';
import { NavIcon } from '../NavIcon';
import { ButtonPrimary } from './buttons';
import { ApiErrorPanel } from './ApiErrorPanel';
import { Modal } from './Modal';
import { getWpApi } from '../../api';

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
  /** Optional: show a question-library picker inside the modal (Content library-like). */
  showLibrary?: boolean;
  onPickExisting?: (questionId: number) => void;
  pickExistingLabel?: string;
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
    showLibrary = true,
    onPickExisting,
    pickExistingLabel = 'Add',
  } = props;

  const inputRef = useRef<HTMLInputElement>(null);
  const [librarySearch, setLibrarySearch] = useState('');
  const [libraryRows, setLibraryRows] = useState<Array<{ id: number; title: string }>>([]);
  const [libraryLoading, setLibraryLoading] = useState(false);
  const [libraryError, setLibraryError] = useState<unknown>(null);

  useEffect(() => {
    if (!open) {
      return;
    }
    const t = window.setTimeout(() => {
      inputRef.current?.focus();
      inputRef.current?.select();
    }, 60);
    const onKey = (e: KeyboardEvent) => {
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

  const active = types.find((x) => x.type === questionType) || types[0];

  useEffect(() => {
    if (!open || !showLibrary) {
      return;
    }
    let cancelled = false;
    setLibraryLoading(true);
    setLibraryError(null);
    const q = new URLSearchParams({ per_page: '50', status: 'any', context: 'edit' });
    if (librarySearch.trim()) q.set('search', librarySearch.trim());
    void getWpApi()
      .get<{ id: number; title?: { raw?: string; rendered?: string } }[]>(`/sik_question?${q.toString()}`)
      .then((rows) => {
        if (cancelled || !Array.isArray(rows)) return;
        setLibraryRows(
          rows
            .map((r) => ({
              id: Number(r.id) || 0,
              title: r.title?.raw || r.title?.rendered?.replace(/<[^>]+>/g, '') || `Question #${r.id}`,
            }))
            .filter((r) => r.id > 0)
        );
      })
      .catch((e) => {
        if (cancelled) return;
        setLibraryError(e);
        setLibraryRows([]);
      })
      .finally(() => {
        if (!cancelled) setLibraryLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [librarySearch, open, showLibrary]);

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={heading}
      description={description}
      size="lg"
      footer={
        <div className="flex flex-wrap items-center justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            disabled={busy}
            className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            Cancel
          </button>
          <ButtonPrimary type="button" disabled={busy || !title.trim()} onClick={onSubmit}>
            {busy ? 'Creating…' : submitLabel}
          </ButtonPrimary>
        </div>
      }
    >
      <div className="grid grid-cols-1 gap-0 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 sm:grid-cols-[18rem_1fr]">
        <aside className="border-b border-slate-200/60 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-950/25 sm:border-b-0 sm:border-r">
          <div className="flex items-center justify-between">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Select question type</div>
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

        <div className="max-h-[60vh] overflow-y-auto bg-white p-6 dark:bg-slate-900">
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

          {showLibrary ? (
            <div className="mt-5 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950/20">
              <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                  <div className="text-sm font-semibold text-slate-900 dark:text-white">Question library</div>
                  <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Search and pick an existing question (same as Content library → Questions).
                  </p>
                </div>
                <div className="w-full sm:w-[280px]">
                  <input
                    type="search"
                    value={librarySearch}
                    onChange={(e) => setLibrarySearch(e.target.value)}
                    placeholder="Search questions…"
                    className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                  />
                </div>
              </div>

              {libraryError ? (
                <div className="mt-3">
                  <ApiErrorPanel error={libraryError} title="Could not load question library" onRetry={() => setLibrarySearch((s) => s)} />
                </div>
              ) : libraryLoading ? (
                <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">Loading questions…</p>
              ) : libraryRows.length ? (
                <div className="mt-3 rounded-xl border border-slate-100 dark:border-slate-800">
                  <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                    {libraryRows.map((q) => (
                      <li key={q.id} className="flex items-center justify-between gap-3 px-3 py-2">
                        <span className="min-w-0 flex-1 truncate text-sm text-slate-700 dark:text-slate-200">
                          {q.title || `Question #${q.id}`}
                        </span>
                        {onPickExisting ? (
                          <button
                            type="button"
                            onClick={() => onPickExisting(q.id)}
                            className="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                          >
                            {pickExistingLabel}
                          </button>
                        ) : null}
                      </li>
                    ))}
                  </ul>
                </div>
              ) : (
                <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">No questions found.</p>
              )}
            </div>
          ) : null}
        </div>
      </div>
    </Modal>
  );
}

