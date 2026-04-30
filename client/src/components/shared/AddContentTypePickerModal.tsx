import { useEffect, useRef } from 'react';
import { NavIcon } from '../NavIcon';
import { ButtonPrimary } from './buttons';
import { ApiErrorPanel } from './ApiErrorPanel';
import { useAddonEnabled } from '../../hooks/useAddons';
import type { SikshyaReactConfig } from '../../types';
import { term } from '../../lib/terminology';

/**
 * Identifies one of the seven kinds of teaching content the user can add.
 * `lesson_*` variants all create a `sik_lesson` (with `_sikshya_lesson_type`
 * meta), while `quiz` / `assignment` create their own post types. This is the
 * same vocabulary the course-builder curriculum picker uses, so the two
 * surfaces can stay in sync.
 */
export type ContentPickerType =
  | 'lesson_text'
  | 'lesson_video'
  | 'lesson_live'
  | 'lesson_scorm'
  | 'lesson_h5p'
  | 'quiz'
  | 'assignment';

type PickerOpt = {
  type: ContentPickerType;
  label: string;
  /** Matches `NavIcon` registry / curriculum outline parity. */
  icon: string;
  /** Pro addon required (and licensed) to enable this kind. */
  addonId?: 'live_classes' | 'scorm_h5p_pro';
  /** Marketing badge shown when the type is locked behind that addon. */
  proLabel?: string;
};

/**
 * Single source of truth for the seven content tiles, used by both the
 * standalone "Add lesson" CTA on the Content library list and the in-course
 * curriculum builder modal. There is intentionally only one definition.
 */
export const CONTENT_PICKER_TYPES: PickerOpt[] = [
  { type: 'lesson_text', label: 'Text lesson', icon: 'plusDocument' },
  { type: 'lesson_video', label: 'Video lesson', icon: 'curriculumLessonVideo' },
  { type: 'lesson_live', label: 'Live class', icon: 'curriculumLessonLive', addonId: 'live_classes', proLabel: 'Pro' },
  { type: 'lesson_scorm', label: 'SCORM package', icon: 'curriculumLessonScorm', addonId: 'scorm_h5p_pro', proLabel: 'Pro' },
  { type: 'lesson_h5p', label: 'H5P interactive', icon: 'curriculumLessonH5p', addonId: 'scorm_h5p_pro', proLabel: 'Pro' },
  { type: 'quiz', label: 'Quiz', icon: 'curriculumQuiz' },
  { type: 'assignment', label: 'Assignment', icon: 'curriculumAssignment' },
];

export function defaultTitleFor(type: ContentPickerType, config?: SikshyaReactConfig): string {
  const quiz = config ? term(config, 'quiz') : 'Quiz';
  const assignment = config ? term(config, 'assignment') : 'Assignment';
  const lesson = config ? term(config, 'lesson') : 'Lesson';
  const labels: Record<ContentPickerType, string> = {
    lesson_text: `Text ${lesson.toLowerCase()}`,
    lesson_video: `Video ${lesson.toLowerCase()}`,
    lesson_live: 'Live class',
    lesson_scorm: 'SCORM package',
    lesson_h5p: 'H5P interactive',
    quiz,
    assignment,
  };
  const lab = labels[type] || 'Content';
  return `New ${lab.toLowerCase()}`;
}

const FIELD_INPUT =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500';
const FIELD_LABEL = 'block text-sm font-medium text-slate-800 dark:text-slate-200';
const FIELD_HINT = 'mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400';

type Props = {
  open: boolean;
  /** Needed for terminology relabeling. */
  config?: SikshyaReactConfig;
  /** Modal heading. Defaults to the in-builder phrasing. */
  heading?: string;
  /** Subheading directly under the heading. */
  description?: string;
  /**
   * Optional context banner (e.g. the chapter the new item belongs to). Skipped
   * entirely when not provided so the modal collapses for the standalone case.
   */
  contextLabel?: string;
  contextValue?: string;
  /** Restrict which tiles render — defaults to all seven. */
  allowedTypes?: ContentPickerType[];
  contentType: ContentPickerType;
  onContentTypeChange: (t: ContentPickerType) => void;
  title: string;
  onTitleChange: (v: string) => void;
  onClose: () => void;
  onSubmit: () => void;
  busy: boolean;
  /** API error from the parent's create flow; surfaced inline. */
  error?: unknown;
  /** Submit button label; defaults to "Add content". */
  submitLabel?: string;
  /** Optional: link to Addons page for locked tiles. */
  addonsHref?: string;
};

/**
 * Modal that asks "what content are you adding?" with the seven-tile picker
 * (Text / Video / Live class / SCORM / H5P / Quiz / Assignment) plus a name
 * field. Locked Pro tiles render with an upgrade hint and reject clicks.
 *
 * Used by both the in-course curriculum builder and the standalone
 * "Add lesson" button on the Content library list, so the two flows feel
 * identical.
 */
export function AddContentTypePickerModal(props: Props) {
  const {
    open,
    config,
    heading = 'Add a lesson, quiz, or assignment',
    description = 'Pick a type, give it a clear name, then open it from the list to add the actual teaching material. Quiz questions are created inside the quiz editor after you add the quiz here.',
    contextLabel,
    contextValue,
    allowedTypes,
    contentType,
    onContentTypeChange,
    title,
    onTitleChange,
    onClose,
    onSubmit,
    busy,
    error,
    submitLabel = 'Add content',
    addonsHref,
  } = props;

  const inputRef = useRef<HTMLInputElement>(null);
  const liveAddon = useAddonEnabled('live_classes');
  const scormAddon = useAddonEnabled('scorm_h5p_pro');

  useEffect(() => {
    if (!open) {
      return;
    }
    const t = window.setTimeout(() => {
      inputRef.current?.focus();
      inputRef.current?.select();
    }, 50);
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => {
      window.clearTimeout(t);
      window.removeEventListener('keydown', onKey);
    };
  }, [open, onClose]);

  if (!open) {
    return null;
  }

  const tiles = allowedTypes
    ? CONTENT_PICKER_TYPES.filter((opt) => allowedTypes.includes(opt.type))
    : CONTENT_PICKER_TYPES;

  const lesson = config ? term(config, 'lesson') : 'Lesson';
  const quiz = config ? term(config, 'quiz') : 'Quiz';
  const assignment = config ? term(config, 'assignment') : 'Assignment';

  const lockedReason = (opt: PickerOpt): { locked: boolean; badge?: 'Pro' | 'Off' | 'Upgrade'; hint?: string } => {
    if (!opt.addonId) return { locked: false };
    const st = opt.addonId === 'live_classes' ? liveAddon : scormAddon;
    const enabled = Boolean(st.enabled);
    const licenseOk = Boolean(st.licenseOk);
    const loading = Boolean(st.loading) && st.enabled === null;
    if (loading) {
      return { locked: true, badge: 'Pro', hint: 'Checking add-on status…' };
    }
    if (!licenseOk) {
      return { locked: true, badge: 'Upgrade', hint: 'Upgrade your plan to unlock this.' };
    }
    if (!enabled) {
      return { locked: true, badge: 'Off', hint: 'Turn this on in Addons to use it.' };
    }
    return { locked: false };
  };

  return (
    <div
      className="fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/60 p-4 backdrop-blur-[2px] sm:items-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="sikshya-add-content-title"
    >
      <button type="button" className="absolute inset-0 z-0 cursor-default" aria-label="Close" onClick={onClose} />
      <div className="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <h2 id="sikshya-add-content-title" className="text-lg font-semibold text-slate-900 dark:text-white">
          {heading}
        </h2>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p>

        {contextLabel && contextValue ? (
          <p className="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-200">
            <span className="font-medium text-slate-500 dark:text-slate-400">{contextLabel}: </span>
            <span className="font-semibold">{contextValue}</span>
          </p>
        ) : null}

        {error ? (
          <div className="mt-4">
            <ApiErrorPanel error={error} title="Could not create item" onRetry={() => void 0} />
          </div>
        ) : null}

        <div className={`${FIELD_LABEL} mt-5`}>What are you adding?</div>
        <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
          {tiles.map((opt) => {
            const active = contentType === opt.type;
            const gate = lockedReason(opt);
            return (
              <button
                key={opt.type}
                type="button"
                onClick={() => {
                  if (gate.locked) return;
                  onContentTypeChange(opt.type);
                }}
                disabled={gate.locked}
                aria-disabled={gate.locked}
                title={gate.locked ? gate.hint || 'This item is locked.' : opt.label}
                className={`relative flex flex-col items-center gap-1.5 rounded-xl border px-2 py-3 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 sm:text-sm ${
                  gate.locked
                    ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-500'
                    : active
                      ? 'border-brand-500 bg-brand-50 text-brand-800 dark:border-brand-400 dark:bg-brand-950/50 dark:text-brand-200'
                      : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200'
                }`}
              >
                {gate.badge ? (
                  <span
                    className={`absolute right-1.5 top-1.5 rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide ${
                      gate.badge === 'Off'
                        ? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
                        : 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300'
                    }`}
                  >
                    {gate.badge}
                  </span>
                ) : null}
                <NavIcon name={opt.icon} className="h-5 w-5" />
                {opt.label}
              </button>
            );
          })}
        </div>
        {addonsHref ? (
          <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
            Locked items can be enabled from{' '}
            <a
              href={addonsHref}
              className="font-semibold text-brand-700 underline underline-offset-2 hover:text-brand-800 dark:text-brand-300 dark:hover:text-brand-200"
            >
              Addons
            </a>
            .
          </p>
        ) : null}

        <label htmlFor="sikshya-add-content-title-input" className={`${FIELD_LABEL} mt-5`}>
          Name for this item
        </label>
        <p className={`${FIELD_HINT} mt-0`}>Learners see this in the course outline. You can rename it later.</p>
        <input
          ref={inputRef}
          id="sikshya-add-content-title-input"
          type="text"
          className={FIELD_INPUT}
          placeholder={defaultTitleFor(contentType, config)}
          value={title}
          onChange={(e) => onTitleChange(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              onSubmit();
            }
          }}
        />

        <div className="mt-6 flex flex-wrap justify-end gap-2">
          <button
            type="button"
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            onClick={onClose}
            disabled={busy}
          >
            Cancel
          </button>
          <ButtonPrimary
            type="button"
            className="px-4 py-2.5"
            disabled={busy || !title.trim()}
            onClick={onSubmit}
          >
            {busy ? 'Adding…' : submitLabel}
          </ButtonPrimary>
        </div>
      </div>
    </div>
  );
}
