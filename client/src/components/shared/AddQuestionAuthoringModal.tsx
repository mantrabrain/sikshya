import { useEffect, useMemo, useState } from 'react';
import { getWpApi } from '../../api';
import { NavIcon } from '../NavIcon';
import { ApiErrorPanel } from './ApiErrorPanel';
import { ButtonPrimary } from './buttons';
import { HorizontalEditorTabs } from './HorizontalEditorTabs';
import { Modal } from './Modal';
import { WPMediaPickerField } from './WPMediaPickerField';
import { SkeletonCard } from './Skeleton';
import type { SikshyaReactConfig } from '../../types';
import { appViewHref } from '../../lib/appUrl';
import { isFeatureEnabled } from '../../lib/licensing';
import { useAddonEnabled } from '../../hooks/useAddons';
import {
  PRO_QUESTION_DEFAULTS,
  ProQuestionFields,
  buildProQuestionMeta,
  readProQuestionFromMeta,
  type ProQuestionValues,
} from '../../pages/content-editors/ProIntegrationFields';
import {
  contentFromPost,
  readMeta,
  titleFromPost,
  type WpPostRest,
} from '../../pages/content-editors/useWpContentPost';
import { __, sprintf } from '../../lib/i18n';

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
  /** When true, this question type requires the Advanced Quiz addon (Pro). */
  requiresAdvancedQuiz?: boolean;
};

export const QUESTION_PICKER_TYPES: PickerOpt[] = [
  {
    type: 'true_false',
    label: __('True / False', 'sikshya'),
    hint: __('Fast checks with one correct answer.', 'sikshya'),
    icon: 'puzzle',
  },
  {
    type: 'multiple_choice',
    label: __('Multiple choice', 'sikshya'),
    hint: __('One correct answer from a list of options.', 'sikshya'),
    icon: 'puzzle',
  },
  {
    type: 'multiple_response',
    label: __('Multiple response', 'sikshya'),
    hint: __('Learner can select more than one correct option.', 'sikshya'),
    icon: 'layers',
    requiresAdvancedQuiz: true,
  },
  {
    type: 'short_answer',
    label: __('Short answer', 'sikshya'),
    hint: __('Learner types a short text response.', 'sikshya'),
    icon: 'plusDocument',
  },
  {
    type: 'fill_blank',
    label: __('Fill in the blank', 'sikshya'),
    hint: __('A short prompt with a missing word or phrase.', 'sikshya'),
    icon: 'puzzle',
    requiresAdvancedQuiz: true,
  },
  {
    type: 'matching',
    label: __('Matching', 'sikshya'),
    hint: __('Pair items from two columns.', 'sikshya'),
    icon: 'layers',
    requiresAdvancedQuiz: true,
  },
  {
    type: 'ordering',
    label: __('Ordering', 'sikshya'),
    hint: __('Reorder items into the correct sequence.', 'sikshya'),
    icon: 'layers',
    requiresAdvancedQuiz: true,
  },
  {
    type: 'essay',
    label: __('Essay', 'sikshya'),
    hint: __('Long-form response, typically manually graded.', 'sikshya'),
    icon: 'helpCircle',
    requiresAdvancedQuiz: true,
  },
];

const FIELD =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus-visible:ring-brand-500/40 dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const LABEL = 'block text-sm font-medium text-slate-800 dark:text-slate-200';
const HINT = 'mt-1 text-xs text-slate-500 dark:text-slate-400';

const DEFAULT_MCQ_OPTIONS = ['', '', '', ''];

function metaStringArray(raw: unknown): string[] {
  if (!Array.isArray(raw)) {
    return [];
  }
  return raw.map((x) => String(x));
}

function parseQuestionCorrectJson(raw: string): unknown {
  try {
    return JSON.parse(raw) as unknown;
  } catch {
    return null;
  }
}

/**
 * Populate modal form state from a `sik_question` REST record (same rules as full-page QuestionEditor).
 */
function hydrateAuthoringFormFromQuestionPost(
  p: WpPostRest,
  setters: {
    setTitle: (v: string) => void;
    setContent: (v: string) => void;
    setStatus: (v: string) => void;
    setFeatured: (v: number) => void;
    setQType: (v: string) => void;
    setPoints: (v: number) => void;
    setOptions: (v: string[]) => void;
    setCorrectAnswer: (v: string) => void;
    setMultiCorrect: (v: number[]) => void;
    setMatchLeft: (v: string[]) => void;
    setMatchRight: (v: string[]) => void;
    setMatchMap: (v: number[]) => void;
    setOrderItems: (v: string[]) => void;
    setOrderPerm: (v: number[]) => void;
    setProQuestionValues: (v: ProQuestionValues) => void;
  }
): void {
  const {
    setTitle,
    setContent,
    setStatus,
    setFeatured,
    setQType,
    setPoints,
    setOptions,
    setCorrectAnswer,
    setMultiCorrect,
    setMatchLeft,
    setMatchRight,
    setMatchMap,
    setOrderItems,
    setOrderPerm,
    setProQuestionValues,
  } = setters;
  setTitle(titleFromPost(p));
  setContent(contentFromPost(p));
  setStatus(p.status || 'draft');
  setFeatured(typeof p.featured_media === 'number' ? p.featured_media : 0);
  const m = p.meta as Record<string, unknown> | undefined;
  const t = String(readMeta(m, '_sikshya_question_type') ?? '');
  setQType(t);
  setPoints(Number(readMeta(m, '_sikshya_question_points') ?? 1));
  setProQuestionValues(readProQuestionFromMeta(m));
  const loadedOpts = metaStringArray(readMeta(m, '_sikshya_question_options'));
  const rawCorrect = String(readMeta(m, '_sikshya_question_correct_answer') ?? '');

  if (t === 'matching') {
    setOptions([]);
    const parsed = parseQuestionCorrectJson(rawCorrect) as {
      matching?: { left?: string[]; right?: string[]; map?: number[] };
    } | null;
    const mm = parsed && typeof parsed === 'object' && parsed !== null ? parsed.matching : undefined;
    if (mm && Array.isArray(mm.left) && Array.isArray(mm.right)) {
      setMatchLeft(mm.left.map(String));
      setMatchRight(mm.right.map(String));
      const n = mm.left.length;
      const map =
        Array.isArray(mm.map) && mm.map.length === n ? mm.map.map((x) => Number(x)) : mm.left.map((_, i) => i);
      setMatchMap(map);
    } else {
      setMatchLeft(['', '']);
      setMatchRight(['', '']);
      setMatchMap([0, 0]);
    }
    setCorrectAnswer('');
    setMultiCorrect([]);
    return;
  }

  if (t === 'ordering') {
    const items = loadedOpts.length >= 2 ? loadedOpts : ['Item 1', 'Item 2', 'Item 3'];
    setOrderItems(items);
    const permRaw = parseQuestionCorrectJson(rawCorrect);
    if (Array.isArray(permRaw) && permRaw.length === items.length && permRaw.every((x) => typeof x === 'number')) {
      setOrderPerm(permRaw as number[]);
    } else {
      setOrderPerm(items.map((_, i) => i));
    }
    setOptions(items);
    setCorrectAnswer('');
    setMultiCorrect([]);
    return;
  }

  if (t === 'multiple_response') {
    setOptions(loadedOpts.length >= 2 ? loadedOpts : [...DEFAULT_MCQ_OPTIONS]);
    const parsed = parseQuestionCorrectJson(rawCorrect);
    setMultiCorrect(
      Array.isArray(parsed) ? parsed.map((x) => Number(x)).filter((n) => Number.isInteger(n) && n >= 0) : [],
    );
    setCorrectAnswer('');
    setMatchLeft(['', '']);
    setMatchRight(['', '']);
    setMatchMap([0, 0]);
    return;
  }

  if (t === 'true_false') {
    setOptions(['True', 'False']);
    setCorrectAnswer(rawCorrect === 'false' ? 'false' : 'true');
    setMultiCorrect([]);
    setMatchLeft(['', '']);
    setMatchRight(['', '']);
    setMatchMap([0, 0]);
    return;
  }

  setOptions(loadedOpts.length >= 2 ? loadedOpts : [...DEFAULT_MCQ_OPTIONS]);
  setCorrectAnswer(rawCorrect);
  setMultiCorrect([]);
  setMatchLeft(['', '']);
  setMatchRight(['', '']);
  setMatchMap([0, 0]);
  setOrderItems(['Item 1', 'Item 2', 'Item 3']);
  setOrderPerm([0, 1, 2]);
}

function useAttachmentPreviewUrl(attachmentId: number): string {
  const [url, setUrl] = useState('');
  useEffect(() => {
    if (!attachmentId || attachmentId <= 0) {
      setUrl('');
      return;
    }
    let cancelled = false;
    void getWpApi()
      .get<{ source_url?: string }>(`/media/${attachmentId}`)
      .then((media) => {
        if (!cancelled && media?.source_url) {
          setUrl(media.source_url);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setUrl('');
        }
      });
    return () => {
      cancelled = true;
    };
  }, [attachmentId]);
  return url;
}

function ModalFeaturedImageField(props: {
  fieldId: string;
  attachmentId: number;
  onAttachmentIdChange: (id: number) => void;
  description?: string;
}) {
  const previewUrl = useAttachmentPreviewUrl(props.attachmentId);
  return (
    <div>
      <label className={LABEL} htmlFor={props.fieldId}>
        Featured image
      </label>
      <p className={HINT}>
        {props.description ??
          'Optional image shown with the question in supported themes or future quiz layouts.'}
      </p>
      <WPMediaPickerField
        id={props.fieldId}
        value={previewUrl}
        onChange={() => {}}
        onAttachmentIdChange={props.onAttachmentIdChange}
        className={FIELD}
        placeholder={__(
          'Opens the media library — upload a new image or choose an existing file.',
          'sikshya'
        )}
      />
    </div>
  );
}

function buildCreateBody(params: {
  title: string;
  content: string;
  status: string;
  featured: number;
  qType: string;
  points: number;
  options: string[];
  correctAnswer: string;
  multiCorrect: number[];
  matchLeft: string[];
  matchRight: string[];
  matchMap: number[];
  orderItems: string[];
  orderPerm: number[];
  proQuestionValues: ProQuestionValues;
}): Record<string, unknown> {
  const {
    title,
    content,
    status,
    featured,
    qType,
    points,
    options,
    correctAnswer,
    multiCorrect,
    matchLeft,
    matchRight,
    matchMap,
    orderItems,
    orderPerm,
    proQuestionValues,
  } = params;

  const trimmedOptions = options.map((o) => o.trim()).filter(Boolean);
  let optionsPayload: string[] = [];
  let correctPayload = '';

  if (qType === 'multiple_choice') {
    optionsPayload = trimmedOptions;
    correctPayload = correctAnswer;
  } else if (qType === 'multiple_response') {
    optionsPayload = trimmedOptions;
    correctPayload = JSON.stringify([...multiCorrect].sort((a, b) => a - b));
  } else if (qType === 'true_false') {
    optionsPayload = [];
    correctPayload = correctAnswer === 'false' ? 'false' : 'true';
  } else if (qType === 'short_answer' || qType === 'fill_blank') {
    optionsPayload = [];
    correctPayload = correctAnswer.trim();
  } else if (qType === 'essay') {
    optionsPayload = [];
    correctPayload = '';
  } else if (qType === 'matching') {
    optionsPayload = [];
    correctPayload = JSON.stringify({
      matching: {
        left: matchLeft.map((x) => x.trim()),
        right: matchRight.map((x) => x.trim()),
        map: matchMap.map((x) => Number(x)),
      },
    });
  } else if (qType === 'ordering') {
    const items = orderItems.map((x) => x.trim()).filter(Boolean);
    const useItems = items.length >= 2 ? items : ['Item 1', 'Item 2'];
    optionsPayload = useItems;
    const perm = orderPerm.length === useItems.length ? orderPerm : useItems.map((_, i) => i);
    correctPayload = JSON.stringify(perm);
  }

  return {
    title,
    content,
    status,
    featured_media: featured > 0 ? featured : 0,
    meta: {
      _sikshya_question_type: qType,
      _sikshya_question_points: Math.max(0, points),
      _sikshya_question_options: optionsPayload,
      _sikshya_question_correct_answer: correctPayload,
      // Mirror the explanation textarea to the dedicated meta key so the
      // learn-page renderer can pick it up without depending on raw
      // post_content (which doubles as instructor notes in some workflows).
      _sikshya_question_explanation: content,
      ...buildProQuestionMeta(proQuestionValues),
    },
  };
}

type Props = {
  config: SikshyaReactConfig;
  open: boolean;
  onClose: () => void;
  onCreated: (questionId: number) => void;
  /** When set, modal loads this question and saves via REST update instead of create. */
  editQuestionId?: number | null;
  /** Called after a successful save in edit mode (e.g. refresh titles in the parent list). */
  onUpdated?: (questionId: number) => void;
  onPickExisting?: (questionId: number) => void;
  pickExistingLabel?: string;
};

/**
 * Full “Content library → New question” authoring experience inside a modal (same fields as QuestionEditor).
 */
export function AddQuestionAuthoringModal(props: Props) {
  const { config, open, onClose, onCreated, editQuestionId, onUpdated, onPickExisting, pickExistingLabel = 'Add to quiz' } =
    props;

  const isEditMode = Boolean(editQuestionId && editQuestionId > 0);

  const advFeatureOk = isFeatureEnabled(config, 'quiz_advanced');
  const advAddon = useAddonEnabled('quiz_advanced');
  const canUseAdvancedTypes = useMemo(() => {
    if (!advFeatureOk) return false;
    if (advAddon.loading) return false;
    if (!advAddon.enabled) return false;
    if (advAddon.licenseOk === false) return false;
    return true;
  }, [advAddon.enabled, advAddon.loading, advAddon.licenseOk, advFeatureOk]);
  const addonsHref = useMemo(() => appViewHref(config, 'addons'), [config]);

  const isLockedType = useMemo(() => {
    const locked = new Map<QuestionType, boolean>();
    QUESTION_PICKER_TYPES.forEach((t) => locked.set(t.type, Boolean(t.requiresAdvancedQuiz) && !canUseAdvancedTypes));
    return (t: QuestionType) => Boolean(locked.get(t));
  }, [canUseAdvancedTypes]);

  const [busy, setBusy] = useState(false);
  const [postLoading, setPostLoading] = useState(false);
  const [loadRetry, setLoadRetry] = useState(0);
  const [loadError, setLoadError] = useState<unknown>(null);
  const [error, setError] = useState<unknown>(null);
  const [editorTab, setEditorTab] = useState<'content' | 'settings'>('content');

  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [status, setStatus] = useState('draft');
  const [qType, setQType] = useState('');
  const [points, setPoints] = useState(1);
  const [options, setOptions] = useState<string[]>(() => [...DEFAULT_MCQ_OPTIONS]);
  const [correctAnswer, setCorrectAnswer] = useState('');
  const [multiCorrect, setMultiCorrect] = useState<number[]>([]);
  const [matchLeft, setMatchLeft] = useState<string[]>(['', '']);
  const [matchRight, setMatchRight] = useState<string[]>(['', '']);
  const [matchMap, setMatchMap] = useState<number[]>([0, 0]);
  const [orderItems, setOrderItems] = useState<string[]>(['Item 1', 'Item 2', 'Item 3']);
  const [orderPerm, setOrderPerm] = useState<number[]>([0, 1, 2]);
  const [featured, setFeatured] = useState(0);
  const [proQuestionValues, setProQuestionValues] = useState<ProQuestionValues>(PRO_QUESTION_DEFAULTS);
  const [typeMenuPos, setTypeMenuPos] = useState<{ top: number; left: number } | null>(null);

  const [librarySearch, setLibrarySearch] = useState('');
  const [libraryRows, setLibraryRows] = useState<Array<{ id: number; title: string }>>([]);
  const [libraryLoading, setLibraryLoading] = useState(false);
  const [libraryError, setLibraryError] = useState<unknown>(null);
  const [librarySelected, setLibrarySelected] = useState<number[]>([]);

  useEffect(() => {
    if (!open) {
      setPostLoading(false);
      setLoadError(null);
      setLoadRetry(0);
      return;
    }
    setError(null);
    setLoadError(null);
    setEditorTab('content');
    setTypeMenuPos(null);
    setLibrarySearch('');
    setLibraryRows([]);
    setLibraryError(null);
    setLibrarySelected([]);
    setTitle('');
    setContent('');
    setStatus('draft');
    setQType('');
    setPoints(1);
    setOptions([...DEFAULT_MCQ_OPTIONS]);
    setCorrectAnswer('');
    setMultiCorrect([]);
    setMatchLeft(['', '']);
    setMatchRight(['', '']);
    setMatchMap([0, 0]);
    setOrderItems(['Item 1', 'Item 2', 'Item 3']);
    setOrderPerm([0, 1, 2]);
    setFeatured(0);
    setProQuestionValues(PRO_QUESTION_DEFAULTS);
    if (isEditMode) {
      setPostLoading(true);
    } else {
      setPostLoading(false);
    }
  }, [open, isEditMode]);

  useEffect(() => {
    if (!open || !isEditMode || !editQuestionId || editQuestionId <= 0) {
      return;
    }
    let cancelled = false;
    setPostLoading(true);
    setLoadError(null);
    void getWpApi()
      .get<WpPostRest>(`/sik_question/${editQuestionId}?context=edit`)
      .then((p) => {
        if (cancelled || !p) {
          return;
        }
        hydrateAuthoringFormFromQuestionPost(p, {
          setTitle,
          setContent,
          setStatus,
          setFeatured,
          setQType,
          setPoints,
          setOptions,
          setCorrectAnswer,
          setMultiCorrect,
          setMatchLeft,
          setMatchRight,
          setMatchMap,
          setOrderItems,
          setOrderPerm,
          setProQuestionValues,
        });
      })
      .catch((e) => {
        if (!cancelled) {
          setLoadError(e);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setPostLoading(false);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [open, isEditMode, editQuestionId, loadRetry]);

  useEffect(() => {
    if (!open) {
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
  }, [librarySearch, open]);

  const onTypeChange = (v: string) => {
    setError(null);
    if (isLockedType(v as QuestionType)) {
      setError(new Error('This question type requires the Advanced Quiz add-on.'));
      return;
    }
    setQType(v);
    if (v === 'true_false') {
      setCorrectAnswer('true');
      setOptions(['True', 'False']);
      setMultiCorrect([]);
      return;
    }
    if (v === 'multiple_choice') {
      setOptions([...DEFAULT_MCQ_OPTIONS]);
      setCorrectAnswer('');
      setMultiCorrect([]);
      return;
    }
    if (v === 'multiple_response') {
      setOptions([...DEFAULT_MCQ_OPTIONS]);
      setCorrectAnswer('');
      setMultiCorrect([]);
      return;
    }
    if (v === 'matching') {
      setMatchLeft(['', '']);
      setMatchRight(['', '']);
      setMatchMap([0, 0]);
      setOptions([]);
      setCorrectAnswer('');
      setMultiCorrect([]);
      return;
    }
    if (v === 'ordering') {
      setOrderItems(['Item 1', 'Item 2', 'Item 3']);
      setOrderPerm([0, 1, 2]);
      setOptions(['Item 1', 'Item 2', 'Item 3']);
      setCorrectAnswer('');
      setMultiCorrect([]);
      return;
    }
    setOptions([...DEFAULT_MCQ_OPTIONS]);
    setCorrectAnswer('');
    setMultiCorrect([]);
  };

  const moveOrderSlot = (pos: number, dir: -1 | 1) => {
    setOrderPerm((prev) => {
      const j = pos + dir;
      if (j < 0 || j >= prev.length) {
        return prev;
      }
      const next = [...prev];
      const tmp = next[pos];
      next[pos] = next[j];
      next[j] = tmp;
      return next;
    });
  };

  const openTypeMenu = (el: HTMLElement | null) => {
    if (!el) {
      setTypeMenuPos(null);
      return;
    }
    const rect = el.getBoundingClientRect();
    setTypeMenuPos({ top: rect.bottom + 8, left: Math.min(rect.left, window.innerWidth - 320) });
  };

  const submitPrimary = () => {
    const stem = title.trim();
    if (!stem || !qType) {
      return;
    }
    if (isLockedType(qType as QuestionType)) {
      setError(new Error('This question type requires the Advanced Quiz add-on.'));
      return;
    }
    if (isEditMode && (!editQuestionId || editQuestionId <= 0)) {
      return;
    }
    setBusy(true);
    setError(null);
    const body = buildCreateBody({
      title: stem,
      content,
      status,
      featured,
      qType,
      points,
      options,
      correctAnswer,
      multiCorrect,
      matchLeft,
      matchRight,
      matchMap,
      orderItems,
      orderPerm,
      proQuestionValues,
    });
    if (isEditMode && editQuestionId && editQuestionId > 0) {
      void getWpApi()
        .put<WpPostRest>(`/sik_question/${editQuestionId}?context=edit`, body)
        .then(() => {
          onUpdated?.(editQuestionId);
          onClose();
        })
        .catch((e) => {
          setError(e);
        })
        .finally(() => {
          setBusy(false);
        });
      return;
    }
    void getWpApi()
      .post<{ id: number }>(`/sik_question`, body)
      .then((created) => {
        if (!created?.id) {
          throw new Error('Could not create question.');
        }
        onCreated(created.id);
        onClose();
      })
      .catch((e) => {
        setError(e);
      })
      .finally(() => {
        setBusy(false);
      });
  };

  const canSubmit = Boolean(
    title.trim() && qType && !busy && !(isEditMode && postLoading) && !(isEditMode && loadError)
  );
  const canBulkAdd = Boolean(!busy && onPickExisting && librarySelected.length > 0);

  return (
    <>
      <Modal
        open={open}
        onClose={() => {
          if (!busy) {
            onClose();
          }
        }}
        title={isEditMode ? __('Edit question', 'sikshya') : __('New question', 'sikshya')}
        description={
          isEditMode
            ? __(
                'Update this question in your library. Changes apply everywhere this question is used.',
                'sikshya'
              )
            : __(
                'Same authoring flow as Content library → Questions. Add to this quiz when you are ready.',
                'sikshya'
              )
        }
        size="xl"
        footer={
          <div className="flex flex-wrap items-center justify-end gap-2">
            <button
              type="button"
              onClick={onClose}
              disabled={busy}
              className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
              {__('Cancel', 'sikshya')}
            </button>
            {onPickExisting ? (
              <button
                type="button"
                disabled={!canBulkAdd}
                onClick={() => {
                  if (!onPickExisting) return;
                  const ids = [...new Set(librarySelected)].filter((n) => Number.isFinite(n) && n > 0);
                  if (!ids.length) return;
                  ids.forEach((id) => onPickExisting(id));
                  onClose();
                }}
                className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
              >
                {__('Add selected', 'sikshya')}
              </button>
            ) : null}
            <ButtonPrimary type="button" disabled={!canSubmit} onClick={submitPrimary}>
              {busy
                ? isEditMode
                  ? __('Saving…', 'sikshya')
                  : __('Creating…', 'sikshya')
                : isEditMode
                  ? __('Save changes', 'sikshya')
                  : __('Create and add to quiz', 'sikshya')}
            </ButtonPrimary>
          </div>
        }
      >
        <div className="space-y-4">
          {loadError && isEditMode ? (
            <ApiErrorPanel
              error={loadError}
              title={__('Could not load question', 'sikshya')}
              onRetry={() => {
                setLoadError(null);
                setLoadRetry((n) => n + 1);
              }}
            />
          ) : null}
          {error ? (
            <ApiErrorPanel
              error={error}
              title={
                isEditMode ? __('Could not save question', 'sikshya') : __('Could not create question', 'sikshya')
              }
              onRetry={() => setError(null)}
            />
          ) : null}

          {postLoading && isEditMode && !loadError ? (
            <SkeletonCard rows={5} />
          ) : null}

          {!(isEditMode && (postLoading || loadError)) ? (
            <>
          <HorizontalEditorTabs
            ariaLabel={
              isEditMode ? __('Edit question sections', 'sikshya') : __('New question sections', 'sikshya')
            }
            tabs={[
              { id: 'content', label: __('Content', 'sikshya'), icon: 'plusDocument' },
              { id: 'settings', label: __('Settings', 'sikshya'), icon: 'cog' },
            ]}
            value={editorTab}
            onChange={(id) => setEditorTab(id as 'content' | 'settings')}
          />

          <div className="max-h-[min(70vh,40rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
              {editorTab === 'content' ? (
                <div className="space-y-6" role="tabpanel">
                  <div>
                    <label className={LABEL} htmlFor="sik-q-modal-type">
                      {__('Question type', 'sikshya')}
                    </label>
                    <p className={HINT}>
                      {__(
                        'Each type shows different fields — multiple choice, matching, essay, and so on.',
                        'sikshya'
                      )}
                    </p>
                    <div className="mt-1.5">
                      <button
                        type="button"
                        id="sik-q-modal-type"
                        className="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-left text-sm text-slate-900 shadow-sm transition hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 focus-visible:ring-brand-500/40 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                        aria-haspopup="listbox"
                        aria-expanded={Boolean(typeMenuPos)}
                        onClick={(e) => openTypeMenu(e.currentTarget)}
                      >
                        <span className="min-w-0">
                          <span className="block font-medium">
                            {qType
                              ? QUESTION_PICKER_TYPES.find((t) => t.type === (qType as QuestionType))?.label ||
                                qType.replace(/_/g, ' ')
                              : __('Select question type', 'sikshya')}
                          </span>
                          <span className="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">
                            {qType
                              ? QUESTION_PICKER_TYPES.find((t) => t.type === (qType as QuestionType))?.hint ||
                                __('Answers depend on the chosen type.', 'sikshya')
                              : __(
                                  'Choose the format first — it will unlock the right answer fields.',
                                  'sikshya'
                                )}
                          </span>
                        </span>
                        <NavIcon name="chevronDown" className="h-4 w-4 shrink-0 text-slate-400" />
                      </button>
                    </div>
                  </div>
                  <div>
                    <label className={LABEL} htmlFor="sik-q-modal-stem">
                      {__('Question text', 'sikshya')}
                    </label>
                    <p className={HINT}>{__('What the learner sees (plain text or short HTML).', 'sikshya')}</p>
                    <textarea
                      id="sik-q-modal-stem"
                      rows={4}
                      className={`${FIELD} min-h-[88px] w-full`}
                      placeholder={__('Enter the question stem…', 'sikshya')}
                      value={title}
                      onChange={(e) => setTitle(e.target.value)}
                    />
                  </div>
                  {!qType ? (
                    <div className="rounded-2xl border border-slate-200 bg-slate-50/60 px-4 py-5 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-950/25 dark:text-slate-200">
                      <div className="flex items-start gap-3">
                        <span className="mt-0.5 flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-600 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-700">
                          <NavIcon name="helpCircle" className="h-5 w-5" />
                        </span>
                        <div className="min-w-0">
                          <div className="font-semibold">{__('Choose a question type to continue', 'sikshya')}</div>
                          <p className="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            {__(
                              'Once you pick a type, Sikshya will show the right answer fields (options, matching pairs, ordering, etc.).',
                              'sikshya'
                            )}
                          </p>
                          <div className="mt-3 flex flex-wrap gap-2">
                            {QUESTION_PICKER_TYPES.slice(0, 6).map((t) => (
                              <button
                                key={t.type}
                                type="button"
                                disabled={isLockedType(t.type)}
                                className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                                  isLockedType(t.type)
                                    ? 'cursor-not-allowed border-amber-200 bg-amber-50 text-amber-900 opacity-80 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'
                                }`}
                                onClick={() => onTypeChange(t.type)}
                              >
                                {t.label}
                                {isLockedType(t.type) ? (
                                  <span className="ml-1">{__('• Pro', 'sikshya')}</span>
                                ) : null}
                              </button>
                            ))}
                          </div>
                          {!canUseAdvancedTypes ? (
                            <p className="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                              {__(
                                'Advanced question types are available in Advanced Quiz.',
                                'sikshya'
                              )}{' '}
                              <a
                                href={addonsHref}
                                className="font-semibold text-brand-700 underline underline-offset-2 hover:text-brand-800 dark:text-brand-300 dark:hover:text-brand-200"
                              >
                                {__('Advanced Quiz', 'sikshya')}
                              </a>
                            </p>
                          ) : null}
                        </div>
                      </div>
                    </div>
                  ) : null}
                  {qType === 'multiple_choice' ? (
                    <div className="space-y-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                      <div className="text-sm font-semibold text-slate-800 dark:text-slate-200">
                        {__('Answer choices', 'sikshya')}
                      </div>
                      <p className={HINT}>{__('At least two options; mark the correct one.', 'sikshya')}</p>
                      <ul className="space-y-3">
                        {options.map((opt, idx) => (
                          <li key={idx} className="flex flex-wrap items-start gap-3">
                            <label className="mt-2.5 flex shrink-0 cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-400">
                              <input
                                type="radio"
                                name="sik-q-modal-correct"
                                checked={correctAnswer === String(idx)}
                                onChange={() => setCorrectAnswer(String(idx))}
                                className="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500"
                              />
                              {__('Correct', 'sikshya')}
                            </label>
                            <input
                              className={`${FIELD} min-w-0 flex-1`}
                              value={opt}
                              onChange={(e) =>
                                setOptions((prev) => {
                                  const next = [...prev];
                                  next[idx] = e.target.value;
                                  return next;
                                })
                              }
                              placeholder={sprintf(__('Option %d', 'sikshya'), idx + 1)}
                            />
                            <button
                              type="button"
                              className="mt-1.5 shrink-0 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-white dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                              onClick={() => {
                                setOptions((prev) => prev.filter((_, i) => i !== idx));
                                setCorrectAnswer((ca) => {
                                  const n = Number(ca);
                                  if (Number.isNaN(n)) {
                                    return '';
                                  }
                                  if (n === idx) {
                                    return '';
                                  }
                                  if (n > idx) {
                                    return String(n - 1);
                                  }
                                  return ca;
                                });
                              }}
                              disabled={options.length <= 2}
                            >
                              {__('Remove', 'sikshya')}
                            </button>
                          </li>
                        ))}
                      </ul>
                      <button
                        type="button"
                        className="text-sm font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                        onClick={() => setOptions((prev) => [...prev, ''])}
                      >
                        {__('+ Add option', 'sikshya')}
                      </button>
                    </div>
                  ) : null}
                  {qType === 'multiple_response' ? (
                    <div className="space-y-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                      <div className="text-sm font-semibold text-slate-800 dark:text-slate-200">
                        {__('Answer choices', 'sikshya')}
                      </div>
                      <p className={HINT}>
                        {__('Check every option that should be marked correct.', 'sikshya')}
                      </p>
                      <ul className="space-y-3">
                        {options.map((opt, idx) => (
                          <li key={idx} className="flex flex-wrap items-start gap-3">
                            <label className="mt-2.5 flex shrink-0 cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-400">
                              <input
                                type="checkbox"
                                checked={multiCorrect.includes(idx)}
                                onChange={() =>
                                  setMultiCorrect((prev) =>
                                    prev.includes(idx)
                                      ? prev.filter((x) => x !== idx)
                                      : [...prev, idx].sort((a, b) => a - b)
                                  )
                                }
                                className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                              />
                              {__('Correct', 'sikshya')}
                            </label>
                            <input
                              className={`${FIELD} min-w-0 flex-1`}
                              value={opt}
                              onChange={(e) =>
                                setOptions((prev) => {
                                  const next = [...prev];
                                  next[idx] = e.target.value;
                                  return next;
                                })
                              }
                              placeholder={sprintf(__('Option %d', 'sikshya'), idx + 1)}
                            />
                            <button
                              type="button"
                              className="mt-1.5 shrink-0 rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-white dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                              onClick={() => {
                                setOptions((prev) => prev.filter((_, i) => i !== idx));
                                setMultiCorrect((prev) =>
                                  prev
                                    .filter((i) => i !== idx)
                                    .map((i) => (i > idx ? i - 1 : i))
                                    .sort((a, b) => a - b)
                                );
                              }}
                              disabled={options.length <= 2}
                            >
                              {__('Remove', 'sikshya')}
                            </button>
                          </li>
                        ))}
                      </ul>
                      <button
                        type="button"
                        className="text-sm font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                        onClick={() => setOptions((prev) => [...prev, ''])}
                      >
                        {__('+ Add option', 'sikshya')}
                      </button>
                    </div>
                  ) : null}
                  {qType === 'true_false' ? (
                    <div>
                      <label className={LABEL} htmlFor="sik-q-modal-tf">
                        {__('Correct answer', 'sikshya')}
                      </label>
                      <p className={HINT}>{__('Learners choose between true and false.', 'sikshya')}</p>
                      <select
                        id="sik-q-modal-tf"
                        className={FIELD}
                        value={correctAnswer === 'false' ? 'false' : 'true'}
                        onChange={(e) => setCorrectAnswer(e.target.value)}
                      >
                        <option value="true">{__('True', 'sikshya')}</option>
                        <option value="false">{__('False', 'sikshya')}</option>
                      </select>
                    </div>
                  ) : null}
                  {qType === 'short_answer' || qType === 'fill_blank' ? (
                    <div>
                      <label className={LABEL} htmlFor="sik-q-modal-expected">
                        {qType === 'fill_blank'
                          ? __('Accepted answer(s)', 'sikshya')
                          : __('Expected answer', 'sikshya')}
                      </label>
                      <p className={HINT}>
                        {qType === 'fill_blank'
                          ? __(
                              'Separate multiple correct spellings with | (pipe). Comparison is case-insensitive.',
                              'sikshya'
                            )
                          : __(
                              'Case-insensitive match. Use | between alternative correct answers.',
                              'sikshya'
                            )}
                      </p>
                      <input
                        id="sik-q-modal-expected"
                        className={FIELD}
                        value={correctAnswer}
                        onChange={(e) => setCorrectAnswer(e.target.value)}
                        placeholder={
                          qType === 'fill_blank'
                            ? __('e.g. Paris|paris', 'sikshya')
                            : __('e.g. photosynthesis', 'sikshya')
                        }
                      />
                    </div>
                  ) : null}
                  {qType === 'ordering' ? (
                    <div className="space-y-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                      <div className="text-sm font-semibold text-slate-800 dark:text-slate-200">
                        {__('Steps / items', 'sikshya')}
                      </div>
                      <p className={HINT}>
                        {__('Edit labels, then arrange the correct order (top = first).', 'sikshya')}
                      </p>
                      <ul className="space-y-2">
                        {orderItems.map((lab, idx) => (
                          <li key={idx} className="flex flex-wrap gap-2">
                            <input
                              className={`${FIELD} min-w-0 flex-1`}
                              value={lab}
                              onChange={(e) =>
                                setOrderItems((prev) => {
                                  const next = [...prev];
                                  next[idx] = e.target.value;
                                  return next;
                                })
                              }
                              placeholder={sprintf(__('Item %d', 'sikshya'), idx + 1)}
                            />
                            <button
                              type="button"
                              className="rounded-lg border border-slate-200 px-2 py-1 text-xs dark:border-slate-600"
                              onClick={() => {
                                setOrderItems((prev) => prev.filter((_, i) => i !== idx));
                                setOrderPerm((prev) => {
                                  const filt = prev.filter((p) => p !== idx).map((p) => (p > idx ? p - 1 : p));
                                  return filt.length ? filt : [0];
                                });
                              }}
                              disabled={orderItems.length <= 2}
                            >
                              {__('Remove', 'sikshya')}
                            </button>
                          </li>
                        ))}
                      </ul>
                      <button
                        type="button"
                        className="text-sm font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400"
                        onClick={() => {
                          setOrderItems((prev) => [...prev, '']);
                          setOrderPerm((prev) => [...prev, prev.length]);
                        }}
                      >
                        {__('+ Add item', 'sikshya')}
                      </button>
                      <div className="mt-4 text-sm font-semibold text-slate-800 dark:text-slate-200">
                        {__('Correct order', 'sikshya')}
                      </div>
                      <ol className="mt-2 space-y-2">
                        {orderPerm.map((itemIdx, pos) => (
                          <li
                            key={`${pos}-${itemIdx}`}
                            className="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900"
                          >
                            <span className="min-w-0 flex-1 text-sm text-slate-800 dark:text-slate-100">
                              {orderItems[itemIdx] ?? `Item ${itemIdx}`}
                            </span>
                            <button
                              type="button"
                              className="text-xs font-medium text-slate-600 dark:text-slate-400"
                              onClick={() => moveOrderSlot(pos, -1)}
                              disabled={pos === 0}
                            >
                              {__('Up', 'sikshya')}
                            </button>
                            <button
                              type="button"
                              className="text-xs font-medium text-slate-600 dark:text-slate-400"
                              onClick={() => moveOrderSlot(pos, 1)}
                              disabled={pos >= orderPerm.length - 1}
                            >
                              {__('Down', 'sikshya')}
                            </button>
                          </li>
                        ))}
                      </ol>
                    </div>
                  ) : null}
                  {qType === 'matching' ? (
                    <div className="space-y-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                      <div className="text-sm font-semibold text-slate-800 dark:text-slate-200">
                        {__('Matching pairs', 'sikshya')}
                      </div>
                      <p className={HINT}>
                        {__(
                          'For each prompt on the left, choose the matching answer column index.',
                          'sikshya'
                        )}
                      </p>
                      <ul className="space-y-3">
                        {matchLeft.map((left, i) => (
                          <li key={i} className="grid gap-2 sm:grid-cols-2 sm:items-end">
                            <label className="block min-w-0">
                              <span className={HINT}>{sprintf(__('Prompt %d', 'sikshya'), i + 1)}</span>
                              <input
                                className={`${FIELD} mt-1 w-full`}
                                value={left}
                                onChange={(e) =>
                                  setMatchLeft((prev) => {
                                    const n = [...prev];
                                    n[i] = e.target.value;
                                    return n;
                                  })
                                }
                                placeholder={__('Left column text', 'sikshya')}
                              />
                            </label>
                            <div className="flex flex-wrap gap-2">
                              <label className="block min-w-0 flex-1">
                                <span className={HINT}>{__('Matches answer', 'sikshya')}</span>
                                <select
                                  className={`${FIELD} mt-1 w-full`}
                                  value={matchMap[i] ?? 0}
                                  onChange={(e) =>
                                    setMatchMap((prev) => {
                                      const n = [...prev];
                                      n[i] = Number(e.target.value);
                                      return n;
                                    })
                                  }
                                >
                                  {matchRight.map((_, ri) => (
                                    <option key={ri} value={ri}>
                                      #{ri + 1}
                                    </option>
                                  ))}
                                </select>
                              </label>
                            </div>
                          </li>
                        ))}
                      </ul>
                      <div className="mt-4 text-sm font-semibold text-slate-800 dark:text-slate-200">
                        {__('Answer column', 'sikshya')}
                      </div>
                      <ul className="mt-2 space-y-2">
                        {matchRight.map((r, i) => (
                          <li key={i} className="flex gap-2">
                            <input
                              className={`${FIELD} flex-1`}
                              value={r}
                              onChange={(e) =>
                                setMatchRight((prev) => {
                                  const n = [...prev];
                                  n[i] = e.target.value;
                                  return n;
                                })
                              }
                              placeholder={sprintf(__('Answer %d', 'sikshya'), i + 1)}
                            />
                          </li>
                        ))}
                      </ul>
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          className="text-sm font-semibold text-brand-600"
                          onClick={() => {
                            setMatchLeft((prev) => [...prev, '']);
                            setMatchMap((prev) => [...prev, 0]);
                          }}
                        >
                          {__('+ Add prompt', 'sikshya')}
                        </button>
                        <button
                          type="button"
                          className="text-sm font-semibold text-brand-600"
                          onClick={() => setMatchRight((prev) => [...prev, ''])}
                        >
                          {__('+ Add answer', 'sikshya')}
                        </button>
                      </div>
                    </div>
                  ) : null}
                  {qType === 'essay' ? (
                    <p className="text-sm text-slate-600 dark:text-slate-400">
                      {__(
                        'Essays are graded manually. Use the explanation field below for model answers or staff notes.',
                        'sikshya'
                      )}
                    </p>
                  ) : null}
                  <ModalFeaturedImageField
                    fieldId="sik-q-modal-featured"
                    attachmentId={featured}
                    onAttachmentIdChange={setFeatured}
                  />
                  <div>
                    <label className={LABEL} htmlFor="sik-q-modal-body">
                      {__('Explanation / feedback (optional)', 'sikshya')}
                    </label>
                    <p className={HINT}>
                      {__(
                        'Shown after grading or kept for instructors. Stored as post content.',
                        'sikshya'
                      )}
                    </p>
                    <textarea
                      id="sik-q-modal-body"
                      rows={6}
                      className={`${FIELD} min-h-[120px] w-full`}
                      placeholder={__(
                        'Optional explanation for learners or grading notes…',
                        'sikshya'
                      )}
                      value={content}
                      onChange={(e) => setContent(e.target.value)}
                    />
                  </div>

                  <div className="rounded-2xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-950/20">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                      <div>
                        <div className="text-sm font-semibold text-slate-900 dark:text-white">
                          {__('Question library', 'sikshya')}
                        </div>
                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                          {__(
                            'Pick an existing question instead of creating a new one.',
                            'sikshya'
                          )}
                        </p>
                      </div>
                      <div className="w-full sm:w-[280px]">
                        <input
                          type="search"
                          value={librarySearch}
                          onChange={(e) => setLibrarySearch(e.target.value)}
                          placeholder={__('Search questions…', 'sikshya')}
                          className={FIELD}
                        />
                      </div>
                    </div>
                    {onPickExisting && libraryRows.length ? (
                      <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div className="font-medium text-slate-600 dark:text-slate-300">
                          {librarySelected.length
                            ? sprintf(__('%d selected', 'sikshya'), librarySelected.length)
                            : __('Select multiple to add at once', 'sikshya')}
                        </div>
                        <div className="flex flex-wrap gap-2">
                          <button
                            type="button"
                            className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                            disabled={!libraryRows.length}
                            onClick={() => setLibrarySelected(libraryRows.map((r) => r.id))}
                          >
                            {__('Select all', 'sikshya')}
                          </button>
                          <button
                            type="button"
                            className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                            disabled={!librarySelected.length}
                            onClick={() => setLibrarySelected([])}
                          >
                            {__('Clear', 'sikshya')}
                          </button>
                        </div>
                      </div>
                    ) : null}
                    {libraryError ? (
                      <div className="mt-3">
                        <ApiErrorPanel
                          error={libraryError}
                          title={__('Could not load question library', 'sikshya')}
                          onRetry={() => setLibrarySearch((s) => s)}
                        />
                      </div>
                    ) : libraryLoading ? (
                      <div className="mt-3"><SkeletonCard rows={4} /></div>
                    ) : libraryRows.length ? (
                      <div className="mt-3 rounded-xl border border-slate-100 dark:border-slate-800">
                        <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                          {libraryRows.map((q) => (
                            <li key={q.id} className="flex items-center justify-between gap-3 px-3 py-2">
                              {onPickExisting ? (
                                <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                                  <input
                                    type="checkbox"
                                    checked={librarySelected.includes(q.id)}
                                    onChange={() =>
                                      setLibrarySelected((prev) =>
                                        prev.includes(q.id) ? prev.filter((x) => x !== q.id) : [...prev, q.id]
                                      )
                                    }
                                    className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                  />
                                  <span className="min-w-0 flex-1 truncate text-sm text-slate-700 dark:text-slate-200">
                                    {q.title || `Question #${q.id}`}
                                  </span>
                                </label>
                              ) : (
                                <span className="min-w-0 flex-1 truncate text-sm text-slate-700 dark:text-slate-200">
                                  {q.title || `Question #${q.id}`}
                                </span>
                              )}
                              {onPickExisting ? (
                                <button
                                  type="button"
                                  onClick={() => {
                                    onPickExisting(q.id);
                                    onClose();
                                  }}
                                  className="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                >
                                  {pickExistingLabel}
                                </button>
                              ) : null}
                            </li>
                          ))}
                        </ul>
                      </div>
                    ) : (
                      <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        {__('No questions found.', 'sikshya')}
                      </p>
                    )}
                  </div>
                </div>
              ) : (
                <div className="space-y-6" role="tabpanel">
                  <div>
                    <label className={LABEL} htmlFor="sik-q-modal-points">
                      {__('Points', 'sikshya')}
                    </label>
                    <p className={HINT}>
                      {__('Awarded when the answer is correct (auto-graded types only).', 'sikshya')}
                    </p>
                    <input
                      id="sik-q-modal-points"
                      type="number"
                      min={0}
                      className={FIELD}
                      placeholder="1"
                      value={points}
                      onChange={(e) => setPoints(Number(e.target.value))}
                    />
                  </div>
                  <div>
                    <label className={LABEL} htmlFor="sik-q-modal-status">
                      {__('Status', 'sikshya')}
                    </label>
                    <p className={HINT}>
                      {__('Draft stays hidden from learners until published.', 'sikshya')}
                    </p>
                    <select id="sik-q-modal-status" className={FIELD} value={status} onChange={(e) => setStatus(e.target.value)}>
                      <option value="draft">{__('Draft', 'sikshya')}</option>
                      <option value="publish">{__('Published', 'sikshya')}</option>
                    </select>
                  </div>
                  <ProQuestionFields values={proQuestionValues} onChange={setProQuestionValues} />
                </div>
              )}
          </div>
            </>
          ) : null}
        </div>
      </Modal>
      {/* Type picker renders outside Modal’s portal — z-index must exceed {@link Modal} shell (z-[100090]). */}
      {typeMenuPos ? (
        <div
          className="fixed inset-0 z-[100200]"
          onMouseDown={() => setTypeMenuPos(null)}
          aria-hidden
        />
      ) : null}
      {typeMenuPos ? (
        <div
          className="fixed z-[100210] w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
          style={{ top: typeMenuPos.top, left: typeMenuPos.left }}
          role="listbox"
          aria-label="Question type"
        >
          <div className="border-b border-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400">
            Select question type
          </div>
          <ul className="max-h-[min(60vh,22rem)] overflow-y-auto p-2">
            {QUESTION_PICKER_TYPES.map((t) => {
              const active = qType === t.type;
              const locked = isLockedType(t.type);
              return (
                <li key={t.type}>
                  <button
                    type="button"
                    role="option"
                    aria-selected={active}
                    disabled={locked}
                    className={`flex w-full items-start gap-3 rounded-xl px-3 py-2 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 ${
                      active
                        ? 'bg-brand-50 text-brand-900 dark:bg-brand-950/40 dark:text-brand-100'
                        : locked
                          ? 'cursor-not-allowed text-slate-500 opacity-75 dark:text-slate-400'
                          : 'text-slate-800 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800'
                    }`}
                    onClick={() => {
                      onTypeChange(t.type);
                      setTypeMenuPos(null);
                    }}
                  >
                    <span
                      className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${
                        active ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-200'
                      }`}
                      aria-hidden
                    >
                      <NavIcon name={t.icon} className="h-4 w-4" />
                    </span>
                    <span className="min-w-0">
                      <span className="flex flex-wrap items-center gap-2 text-sm font-semibold leading-snug">
                        <span>{t.label}</span>
                        {locked ? (
                          <span className="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900 dark:border-amber-700/70 dark:bg-amber-950/40 dark:text-amber-200">
                            Pro
                          </span>
                        ) : null}
                      </span>
                      <span className="mt-0.5 block text-xs leading-snug text-slate-500 dark:text-slate-400">{t.hint}</span>
                      {locked ? (
                        <span className="mt-1 block text-xs font-medium text-amber-800 dark:text-amber-200">
                          Enable Advanced Quiz add-on to use this type.
                        </span>
                      ) : null}
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        </div>
      ) : null}
    </>
  );
}
