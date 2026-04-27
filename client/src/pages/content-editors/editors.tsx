import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getWpApi } from '../../api';
import { NavIcon } from '../../components/NavIcon';
import { appViewHref } from '../../lib/appUrl';
import { useAdminRouting } from '../../lib/adminRouting';
import { ApiErrorPanel } from '../../components/shared/ApiErrorPanel';
import { useSikshyaDialog } from '../../components/shared/SikshyaDialogContext';
import { ButtonPrimary } from '../../components/shared/buttons';
import { getSikshyaApi } from '../../api';
import { HorizontalEditorTabs } from '../../components/shared/HorizontalEditorTabs';
import { WPMediaPickerField } from '../../components/shared/WPMediaPickerField';
import { DateTimePickerField } from '../../components/shared/DateTimePickerField';
import type { SikshyaReactConfig } from '../../types';
import { GatedFeatureWorkspace } from '../../components/GatedFeatureWorkspace';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../../lib/licensing';
import { CertificateVisualBuilder } from './CertificateVisualBuilder';
import {
  DEFAULT_CERTIFICATE_PAGE_FINISH,
  type CertificatePageFinish,
  defaultCertificateLayout,
  getPageAspectCss,
  layoutToHtml,
  layoutToStorage,
  parseCertificatePageFinish,
  parseLayoutFromMeta,
  type CertLayoutFile,
} from './certificateLayout';
import {
  contentFromPost,
  excerptFromPost,
  readMeta,
  titleFromPost,
  useWpContentPost,
} from './useWpContentPost';
import {
  PRO_ASSIGNMENT_DEFAULTS,
  PRO_LESSON_DEFAULTS,
  PRO_QUESTION_DEFAULTS,
  PRO_QUIZ_DEFAULTS,
  ProAssignmentFields,
  ProGradebookAssignmentWeightFields,
  ProGradebookQuizWeightFields,
  ProLessonH5pBlock,
  ProLessonLiveBlock,
  ProLessonScormBlock,
  ProQuestionFields,
  ProQuizFields,
  buildProAssignmentMeta,
  buildProLessonMetaForKind,
  buildProQuestionMeta,
  QUIZ_ADVANCED_BANK_DRAW_HARD_MAX,
  buildProQuizMeta,
  readProAssignmentFromMeta,
  readProLessonFromMeta,
  readProQuestionFromMeta,
  readProQuizFromMeta,
  type ProAssignmentValues,
  type ProLessonValues,
  type ProQuestionValues,
  type ProQuizValues,
} from './ProIntegrationFields';
import { useAddonEnabled } from '../../hooks/useAddons';
import {
  AddQuestionAuthoringModal,
  QUESTION_PICKER_TYPES,
  type QuestionType,
} from '../../components/shared/AddQuestionAuthoringModal';

const FIELD =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const LABEL = 'block text-sm font-medium text-slate-800 dark:text-slate-200';
const HINT = 'mt-1 text-xs text-slate-500 dark:text-slate-400';

function FormSection(props: { title: string; description?: string; children: React.ReactNode }) {
  const { title, description, children } = props;
  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
      <header className="mb-3">
        <h3 className="text-sm font-semibold text-slate-900 dark:text-white">{title}</h3>
        {description ? <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{description}</p> : null}
      </header>
      <div className="space-y-4">{children}</div>
    </section>
  );
}

/** Resolve attachment URL for the WordPress media picker preview. */
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

function EditorFeaturedImageField(props: {
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
          'Optional. Uses the WordPress featured image for this item when your theme or lists show thumbnails.'}
      </p>
      <WPMediaPickerField
        id={props.fieldId}
        value={previewUrl}
        onChange={() => {}}
        onAttachmentIdChange={props.onAttachmentIdChange}
        className={FIELD}
        placeholder="Opens the media library — upload a new image or choose an existing file."
      />
    </div>
  );
}

function statusPillClass(status: string): string {
  const s = String(status || '').toLowerCase();
  if (s === 'publish' || s === 'published') {
    return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900/40';
  }
  if (s === 'pending') {
    return 'bg-amber-50 text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900/40';
  }
  return 'bg-slate-100 text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700';
}

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

/** Full-width editor surface (matches dashboard main column usage). */
const EDITOR_SURFACE =
  'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900';

type EditorShellProps = {
  loading: boolean;
  saving: boolean;
  error: unknown;
  onRetry: () => void;
  saveMsg: string | null;
  children: React.ReactNode;
};

function EditorFormShell({ loading, saving, error, onRetry, saveMsg, children }: EditorShellProps) {
  const [toast, setToast] = useState<{ open: boolean; kind: 'success'; title: string; message?: string } | null>(null);

  useEffect(() => {
    if (!saveMsg) return;
    setToast({ open: true, kind: 'success', title: 'Saved', message: saveMsg });
  }, [saveMsg]);

  useEffect(() => {
    if (!toast?.open) return;
    const t = window.setTimeout(() => setToast(null), 3800);
    return () => window.clearTimeout(t);
  }, [toast]);

  return (
    <>
      {toast?.open ? (
        <div className="pointer-events-none fixed right-6 top-6 z-[120] w-[min(26rem,calc(100vw-3rem))]">
          <div className="pointer-events-auto flex items-start gap-3 rounded-2xl border border-emerald-200 bg-white/95 px-4 py-3 shadow-2xl backdrop-blur dark:border-emerald-900/40 dark:bg-slate-900/95">
            <span
              className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white"
              aria-hidden
            >
              <NavIcon name="badge" className="h-5 w-5" />
            </span>
            <div className="min-w-0 flex-1">
              <div className="text-sm font-semibold text-slate-900 dark:text-white">{toast.title}</div>
              {toast.message ? (
                <div className="mt-0.5 text-xs leading-snug text-slate-600 dark:text-slate-300">{toast.message}</div>
              ) : null}
            </div>
            <button
              type="button"
              className="rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
              onClick={() => setToast(null)}
              aria-label="Dismiss"
            >
              ×
            </button>
          </div>
        </div>
      ) : null}
      {error ? (
        <div className="mb-4">
          <ApiErrorPanel error={error} title="Request failed" onRetry={onRetry} />
        </div>
      ) : null}
      {loading ? (
        <div className="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">
          Loading…
        </div>
      ) : (
        <div className="space-y-8">{children}</div>
      )}
    </>
  );
}

export type ContentEditorProps = {
  config: SikshyaReactConfig;
  postType: string;
  postId: number;
  backHref: string;
  entityLabel: string;
  onSavedNewId?: (newId: number) => void;
  /** Hide “Back to list” for embedded panels (e.g. course builder curriculum). */
  embedded?: boolean;
  /** When set, chapter editor locks parent course to this ID. */
  forcedCourseId?: number;
};

function useMoveToTrash(
  editor: ReturnType<typeof useWpContentPost>,
  backHref: string,
  entityLabel: string
) {
  const { confirm } = useSikshyaDialog();
  const { navigateHref } = useAdminRouting();
  return useCallback(() => {
    void (async () => {
      if (editor.isNew) {
        return;
      }
      const ok = await confirm({
        title: 'Move to trash?',
        message: `Move this ${entityLabel.toLowerCase()} to the trash? You can restore it later from the Trash tab.`,
        variant: 'danger',
        confirmLabel: 'Move to trash',
      });
      if (!ok) {
        return;
      }
      try {
        await editor.remove();
        navigateHref(backHref);
      } catch {
        /* handled by editor error state */
      }
    })();
  }, [editor, backHref, entityLabel, confirm, navigateHref]);
}

export function LessonEditor(props: ContentEditorProps) {
  const { postId, backHref, entityLabel, onSavedNewId, embedded } = props;
  const editor = useWpContentPost('sik_lesson', postId);
  const moveToTrash = useMoveToTrash(editor, backHref, entityLabel);
  const [title, setTitle] = useState('');
  const [excerpt, setExcerpt] = useState('');
  const [content, setContent] = useState('');
  const [status, setStatus] = useState('draft');
  const [featured, setFeatured] = useState(0);
  const [duration, setDuration] = useState('');
  const [lessonType, setLessonType] = useState('text');
  const [videoUrl, setVideoUrl] = useState('');
  const [saveMsg, setSaveMsg] = useState<string | null>(null);
  const [editorTab, setEditorTab] = useState<'content' | 'settings'>('content');
  const [proValues, setProValues] = useState<ProLessonValues>(PRO_LESSON_DEFAULTS);
  const liveAddon = useAddonEnabled('live_classes');
  const scormAddon = useAddonEnabled('scorm_h5p_pro');
  const liveReady = Boolean(liveAddon.enabled && liveAddon.licenseOk);
  const scormReady = Boolean(scormAddon.enabled && scormAddon.licenseOk);
  const liveOffered = Boolean(liveAddon.licenseOk);
  const scormOffered = Boolean(scormAddon.licenseOk);

  useEffect(() => {
    if (editor.isNew) {
      setTitle('');
      setExcerpt('');
      setContent('');
      setStatus('draft');
      setFeatured(0);
      setDuration('');
      setLessonType('text');
      setVideoUrl('');
      setProValues(PRO_LESSON_DEFAULTS);
      return;
    }
    if (!editor.post) {
      return;
    }
    const p = editor.post;
    setTitle(titleFromPost(p));
    setExcerpt(excerptFromPost(p));
    setContent(contentFromPost(p));
    setStatus(p.status || 'draft');
    setFeatured(typeof p.featured_media === 'number' ? p.featured_media : 0);
    const m = p.meta as Record<string, unknown> | undefined;
    setDuration(String(readMeta(m, '_sikshya_lesson_duration') ?? ''));
    setLessonType(String(readMeta(m, '_sikshya_lesson_type') ?? 'text') || 'text');
    setVideoUrl(String(readMeta(m, '_sikshya_lesson_video_url') ?? ''));
    setProValues(readProLessonFromMeta(m));
  }, [editor.post, editor.isNew]);

  const onSave = async () => {
    setSaveMsg(null);
    editor.setError(null);
    const kind = (lessonType.trim() || 'text');
    const body: Record<string, unknown> = {
      title,
      content,
      status,
      excerpt,
      featured_media: featured > 0 ? featured : 0,
      meta: {
        _sikshya_lesson_duration: duration.trim(),
        _sikshya_lesson_type: kind,
        _sikshya_lesson_video_url: kind === 'video' ? videoUrl.trim() : '',
        ...buildProLessonMetaForKind(kind, proValues),
      },
    };
    try {
      const res = await editor.save(body);
      if (editor.isNew && res && typeof res === 'object' && 'id' in res) {
        const id = (res as { id: number }).id;
        if (typeof id === 'number' && id > 0) {
          onSavedNewId?.(id);
          return;
        }
      }
      setSaveMsg('Lesson saved.');
      await editor.load();
    } catch {
      /* error in hook */
    }
  };

  return (
    <EditorFormShell
      loading={editor.loading}
      saving={editor.saving}
      error={editor.error}
      onRetry={() => void editor.load()}
      saveMsg={saveMsg}
    >
      <div className="space-y-3">
        <HorizontalEditorTabs
          ariaLabel="Lesson editor sections"
          tabs={[
            { id: 'content', label: 'Content', icon: 'plusDocument' },
            { id: 'settings', label: 'Settings', icon: 'cog' },
          ]}
          value={editorTab}
          onChange={(id) => setEditorTab(id as 'content' | 'settings')}
        />

        <section className={EDITOR_SURFACE} aria-label="Lesson editor">
        <div className="border-b border-slate-100 px-6 pb-4 pt-6 dark:border-slate-800">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-white">{title?.trim() ? title : 'Lesson'}</h2>
          <p className={HINT}>Lessons are the ordered steps in your course. Pick a kind below — text, video, live class, SCORM, or H5P — and only that kind's fields show up.</p>
            </div>
            <div className="flex shrink-0 flex-wrap items-center justify-start gap-2 sm:justify-end">
              <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize ${statusPillClass(status)}`}>
                {status || 'draft'}
              </span>
              {duration?.trim() ? (
                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700">
                  {duration.trim()}
                </span>
              ) : null}
              <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700">
                {lessonType === 'video'
                  ? 'Video'
                  : lessonType === 'live'
                  ? 'Live class'
                  : lessonType === 'scorm'
                  ? 'SCORM'
                  : lessonType === 'h5p'
                  ? 'H5P'
                  : 'Text'}
              </span>
            </div>
          </div>
        </div>
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-lesson-title">
                  Lesson title
                </label>
                <input
                  id="sik-lesson-title"
                  className={FIELD}
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="e.g. Installing WordPress"
                />
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-lesson-excerpt">
                  Short summary
                </label>
                <p className={HINT}>A short blurb for lesson lists; optional but helps learners scan the outline.</p>
                <textarea
                  id="sik-lesson-excerpt"
                  rows={3}
                  className={`${FIELD} min-h-[72px] resize-y`}
                  value={excerpt}
                  onChange={(e) => setExcerpt(e.target.value)}
                  placeholder="One or two sentences about this lesson"
                />
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-lesson-type">
                  Lesson type
                </label>
                <p className={HINT}>
                  Pick how learners experience this step. Each kind shows its own field set below.
                </p>
                <select
                  id="sik-lesson-type"
                  className={FIELD}
                  value={lessonType}
                  onChange={(e) => setLessonType(e.target.value)}
                >
                  <option value="text">Text lesson</option>
                  <option value="video">Video lesson</option>
                  {liveOffered ? (
                    <option value="live">{liveReady ? 'Live class (Pro)' : 'Live class (Pro · addon off)'}</option>
                  ) : (
                    <option value="live" disabled>Live class (requires Sikshya Pro)</option>
                  )}
                  {scormOffered ? (
                    <option value="scorm">{scormReady ? 'SCORM package (Pro)' : 'SCORM package (Pro · addon off)'}</option>
                  ) : (
                    <option value="scorm" disabled>SCORM package (requires Sikshya Pro)</option>
                  )}
                  {scormOffered ? (
                    <option value="h5p">{scormReady ? 'H5P interactive (Pro)' : 'H5P interactive (Pro · addon off)'}</option>
                  ) : (
                    <option value="h5p" disabled>H5P interactive (requires Sikshya Pro)</option>
                  )}
                </select>
              </div>
              <EditorFeaturedImageField
                fieldId="sik-lesson-featured"
                attachmentId={featured}
                onAttachmentIdChange={setFeatured}
                description="Optional cover image for lesson lists and course outlines when your theme supports featured images."
              />
              {lessonType === 'video' ? (
                <div className="rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                  <label className={LABEL} htmlFor="sik-lesson-video-url-inline">
                    Video URL
                  </label>
                  <p className={HINT}>Paste a public YouTube, Vimeo, or direct .mp4 link. Learners watch this as the main lesson.</p>
                  <input
                    id="sik-lesson-video-url-inline"
                    className={FIELD}
                    value={videoUrl}
                    onChange={(e) => setVideoUrl(e.target.value)}
                    placeholder="https://www.youtube.com/watch?v=…"
                  />
                </div>
              ) : null}
              {lessonType === 'live' ? (
                <ProLessonLiveBlock values={proValues} onChange={setProValues} />
              ) : null}
              {lessonType === 'scorm' ? (
                <ProLessonScormBlock values={proValues} onChange={setProValues} />
              ) : null}
              {lessonType === 'h5p' ? (
                <ProLessonH5pBlock values={proValues} onChange={setProValues} />
              ) : null}
              <div>
                <label className={LABEL} htmlFor="sik-lesson-body">
                  {lessonType === 'video'
                    ? 'Transcript / notes'
                    : lessonType === 'live'
                    ? 'Briefing for the session'
                    : lessonType === 'scorm' || lessonType === 'h5p'
                    ? 'Notes shown alongside the activity'
                    : 'Lesson content'}
                </label>
                <p className={HINT}>
                  {lessonType === 'video'
                    ? 'Optional but recommended for accessibility and SEO.'
                    : lessonType === 'live'
                    ? 'Agenda, prep work, joining instructions — shown above the “Join live class” button.'
                    : lessonType === 'scorm' || lessonType === 'h5p'
                    ? 'Optional context shown below the embedded activity.'
                    : 'Main instructional content (HTML supported).'}
                </p>
                <textarea
                  id="sik-lesson-body"
                  rows={16}
                  className={`${FIELD} min-h-[320px] w-full font-mono text-[13px] leading-relaxed`}
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                  placeholder={lessonType === 'video' ? '<p>Optional notes under the video…</p>' : '<p>Your teaching content (HTML allowed)…</p>'}
                />
              </div>
            </div>
          ) : (
            <div className="space-y-6" role="tabpanel">
              <FormSection
                title="Publishing"
                description="Controls whether this lesson is visible to learners when the course is published."
              >
                <div className="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label className={LABEL} htmlFor="sik-lesson-status">
                      Status
                    </label>
                    <select
                      id="sik-lesson-status"
                      className={FIELD}
                      value={status}
                      onChange={(e) => setStatus(e.target.value)}
                    >
                      <option value="draft">Draft</option>
                      <option value="publish">Published</option>
                      <option value="private">Private</option>
                      <option value="pending">Pending review</option>
                    </select>
                  </div>
                  <div>
                    <label className={LABEL} htmlFor="sik-lesson-duration">
                      Duration
                    </label>
                    <p className={HINT}>Shown next to the lesson in the outline (optional).</p>
                    <input
                      id="sik-lesson-duration"
                      className={FIELD}
                      value={duration}
                      onChange={(e) => setDuration(e.target.value)}
                      placeholder="e.g. 12 min"
                    />
                  </div>
                </div>
              </FormSection>
            </div>
          )}
        </div>
        </section>
      </div>
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} canSave={Boolean(title.trim())} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
          canSave={Boolean(title.trim())}
          onSave={() => void onSave()}
          onTrash={moveToTrash}
        />
      )}
    </EditorFormShell>
  );
}

/** Course builder side panel: save CPT only; no trash / no full-width action bar. */
function EmbeddedSaveBar(props: { saving: boolean; entityLabel: string; canSave?: boolean; onSave: () => void }) {
  const { saving, entityLabel, canSave = true, onSave } = props;
  return (
    <div id="sikshya-embedded-save" className="mt-6 border-t border-slate-100/90 pt-4 dark:border-slate-800/90">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
          Saves this {entityLabel.toLowerCase()} only. Use the course toolbar for draft / publish.
        </p>
        <button
          type="button"
          disabled={saving || !canSave}
          onClick={onSave}
          className="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
        >
          {saving ? 'Saving…' : `Save ${entityLabel.toLowerCase()}`}
        </button>
      </div>
      {!canSave ? (
        <p className="mt-2 text-[11px] text-slate-500 dark:text-slate-400">Add the required fields (usually title) to enable saving.</p>
      ) : null}
    </div>
  );
}

function EditorActions(props: {
  backHref: string;
  entityLabel: string;
  saving: boolean;
  isNew: boolean;
  canSave?: boolean;
  onSave: () => void;
  onTrash: () => void;
}) {
  const { backHref, saving, isNew, canSave = true, onSave, onTrash } = props;
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-6 dark:border-slate-800">
      <a
        href={backHref}
        className="rounded-lg border border-slate-200/90 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
      >
        ← Back to list
      </a>
      <div className="flex flex-wrap gap-2">
        <ButtonPrimary type="button" disabled={saving || !canSave} onClick={onSave} className="rounded-xl px-5 py-2.5">
          {saving ? 'Saving…' : 'Save'}
        </ButtonPrimary>
        {!isNew ? (
        <button
          type="button"
          disabled={saving}
          onClick={onTrash}
          className="rounded-xl px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 dark:text-red-400 dark:hover:bg-red-950/30"
        >
          Move to trash
        </button>
        ) : null}
      </div>
      {!canSave ? (
        <p className="w-full text-[11px] text-slate-500 dark:text-slate-400">Add the required fields (usually title) to enable saving.</p>
      ) : null}
    </div>
  );
}

export function QuizEditor(props: ContentEditorProps) {
  const { postId, backHref, entityLabel, onSavedNewId, embedded, config } = props;
  const editor = useWpContentPost('sik_quiz', postId);
  const moveToTrash = useMoveToTrash(editor, backHref, entityLabel);
  const advQuiz = useAddonEnabled('quiz_advanced');
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [status, setStatus] = useState('draft');
  const [timeLimit, setTimeLimit] = useState(0);
  const [passing, setPassing] = useState(70);
  const [attempts, setAttempts] = useState(3);
  const [quizQuestionIds, setQuizQuestionIds] = useState<number[]>([]);
  const [questionSearch, setQuestionSearch] = useState('');
  const [questionRows, setQuestionRows] = useState<{ id: number; title: string }[]>([]);
  const [questionLoading, setQuestionLoading] = useState(false);
  const [questionError, setQuestionError] = useState<unknown>(null);
  const [questionRefresh, setQuestionRefresh] = useState(0);
  const [addQuestionOpen, setAddQuestionOpen] = useState(false);
  const [saveMsg, setSaveMsg] = useState<string | null>(null);
  const [featured, setFeatured] = useState(0);
  const [editorTab, setEditorTab] = useState<'content' | 'settings' | 'questions'>('content');
  const [proQuizValues, setProQuizValues] = useState<ProQuizValues>(PRO_QUIZ_DEFAULTS);
  const [quizAdvMaxDraw, setQuizAdvMaxDraw] = useState(QUIZ_ADVANCED_BANK_DRAW_HARD_MAX);

  const QUIZ_Q_DND_MIME = 'application/x-sikshya-quiz-questions-v1';
  const reorderIds = (ids: number[], fromId: number, beforeId: number | null) => {
    const fromIdx = ids.indexOf(fromId);
    if (fromIdx < 0) {
      return ids;
    }
    if (beforeId === fromId) {
      return ids;
    }
    const next = [...ids];
    next.splice(fromIdx, 1);
    let insertAt = next.length;
    if (beforeId != null) {
      const i = next.indexOf(beforeId);
      if (i >= 0) {
        insertAt = i;
      }
    }
    next.splice(insertAt, 0, fromId);
    return next;
  };

  useEffect(() => {
    if (editor.isNew) {
      setTitle('');
      setContent('');
      setStatus('draft');
      setTimeLimit(0);
      setPassing(70);
      setAttempts(3);
      setQuizQuestionIds([]);
      setFeatured(0);
      setProQuizValues(PRO_QUIZ_DEFAULTS);
      return;
    }
    if (!editor.post) {
      return;
    }
    const p = editor.post;
    setTitle(titleFromPost(p));
    setContent(contentFromPost(p));
    setStatus(p.status || 'draft');
    setFeatured(typeof p.featured_media === 'number' ? p.featured_media : 0);
    const m = p.meta as Record<string, unknown> | undefined;
    setTimeLimit(Number(readMeta(m, '_sikshya_quiz_time_limit') ?? 0));
    setPassing(Number(readMeta(m, '_sikshya_quiz_passing_score') ?? 70));
    setAttempts(Number(readMeta(m, '_sikshya_quiz_attempts_allowed') ?? 3));
    const qids = readMeta(m, '_sikshya_quiz_questions');
    if (Array.isArray(qids)) {
      setQuizQuestionIds(qids.map((x) => Number(x)).filter((n) => Number.isFinite(n) && n > 0));
    } else {
      setQuizQuestionIds([]);
    }
    setProQuizValues(readProQuizFromMeta(m));
  }, [editor.post, editor.isNew]);

  useEffect(() => {
    if (editorTab !== 'questions') {
      return;
    }
    let cancelled = false;
    setQuestionLoading(true);
    setQuestionError(null);
    const q = new URLSearchParams({
      per_page: '50',
      status: 'any',
      context: 'edit',
    });
    if (questionSearch.trim()) {
      q.set('search', questionSearch.trim());
    }
    void getWpApi()
      .get<{ id: number; title?: { raw?: string; rendered?: string } }[]>(`/sik_question?${q.toString()}`)
      .then((rows) => {
        if (cancelled || !Array.isArray(rows)) {
          return;
        }
        setQuestionRows(
          rows.map((r) => ({
            id: Number(r.id) || 0,
            title: r.title?.raw || r.title?.rendered?.replace(/<[^>]+>/g, '') || `Question #${r.id}`,
          }))
        );
      })
      .catch((e) => {
        if (!cancelled) {
          setQuestionError(e);
          setQuestionRows([]);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setQuestionLoading(false);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [editorTab, questionSearch, questionRefresh]);

  const openAddQuestion = () => {
    setAddQuestionOpen(true);
  };

  const onSave = async () => {
    setSaveMsg(null);
    editor.setError(null);
    const body: Record<string, unknown> = {
      title,
      content,
      status,
      featured_media: featured > 0 ? featured : 0,
      meta: {
        _sikshya_quiz_time_limit: Math.max(0, timeLimit),
        _sikshya_quiz_passing_score: Math.min(100, Math.max(0, passing)),
        _sikshya_quiz_attempts_allowed: Math.max(1, attempts),
        _sikshya_quiz_questions: quizQuestionIds,
        ...buildProQuizMeta(
          proQuizValues,
          advQuiz.enabled && advQuiz.licenseOk ? quizAdvMaxDraw : QUIZ_ADVANCED_BANK_DRAW_HARD_MAX
        ),
      },
    };
    try {
      const res = await editor.save(body);
      if (editor.isNew && res && typeof res === 'object' && 'id' in res) {
        const id = (res as { id: number }).id;
        if (typeof id === 'number' && id > 0) {
          onSavedNewId?.(id);
          return;
        }
      }
      setSaveMsg('Quiz saved.');
      await editor.load();
    } catch {
      /* hook sets error */
    }
  };

  return (
    <EditorFormShell
      loading={editor.loading}
      saving={editor.saving}
      error={editor.error}
      onRetry={() => void editor.load()}
      saveMsg={saveMsg}
    >
      <div className="space-y-3">
        <HorizontalEditorTabs
          ariaLabel="Quiz editor sections"
          tabs={[
            { id: 'content', label: 'Content', icon: 'plusDocument' },
            { id: 'settings', label: 'Settings', icon: 'cog' },
            { id: 'questions', label: 'Questions', icon: 'helpCircle' },
          ]}
          value={editorTab}
          onChange={(id) => setEditorTab(id as 'content' | 'settings' | 'questions')}
        />

        <section className={EDITOR_SURFACE} aria-label="Quiz editor">
        <div className="border-b border-slate-100 px-6 pb-4 pt-6 dark:border-slate-800">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-white">{title?.trim() ? title : 'Quiz'}</h2>
          {embedded ? (
            <p className={`${HINT} mt-1`}>
              Use <span className="font-medium text-slate-700 dark:text-slate-200">Content</span> for the name, instructions,
              and optional cover image; use <span className="font-medium text-slate-700 dark:text-slate-200">Settings</span> for
              timing and scoring. Questions live in the{' '}
              <a
                href={appViewHref(config, 'content-library', { tab: 'questions' })}
                className="font-medium text-brand-600 underline decoration-brand-600/30 underline-offset-2 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
              >
                Questions
              </a>{' '}
              library.
            </p>
          ) : (
            <p className={HINT}>
              Use <span className="font-medium text-slate-700 dark:text-slate-200">Content</span> for the quiz name and what
              students read before starting; use <span className="font-medium text-slate-700 dark:text-slate-200">Settings</span> for
              timer, pass mark, and attempts. Attach questions on the <span className="font-medium text-slate-700 dark:text-slate-200">Questions</span> tab.
            </p>
          )}
            </div>
            <div className="flex shrink-0 flex-wrap items-center justify-start gap-2 sm:justify-end">
              <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize ${statusPillClass(status)}`}>
                {status || 'draft'}
              </span>
              {Number(timeLimit) > 0 ? (
                <span className="rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-800 ring-1 ring-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-200 dark:ring-indigo-900/40">
                  {Number(timeLimit)} min
                </span>
              ) : null}
            </div>
          </div>
        </div>
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-quiz-title">
                  Quiz name
                </label>
                <p className={HINT}>Shown in the course outline and at the top of the quiz for learners.</p>
                <input
                  id="sik-quiz-title"
                  className={FIELD}
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="e.g. Module 1 checkpoint"
                />
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-quiz-desc">
                  Instructions for students
                </label>
                <textarea
                  id="sik-quiz-desc"
                  rows={8}
                  className={`${FIELD} min-h-[160px] w-full`}
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                  placeholder="What students see before starting the quiz…"
                />
              </div>
              <EditorFeaturedImageField
                fieldId="sik-quiz-featured"
                attachmentId={featured}
                onAttachmentIdChange={setFeatured}
                description="Optional image for quiz cards or outlines when your theme supports featured images."
              />
            </div>
          ) : editorTab === 'settings' ? (
            <div className="space-y-6" role="tabpanel">
              <FormSection title="Grading & timing" description="How the quiz is scored and constrained for learners.">
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                  <div>
                    <label className={LABEL} htmlFor="sik-quiz-time">
                      Time limit (minutes)
                    </label>
                    <p className={HINT}>Use 0 for no time limit.</p>
                    <input
                      id="sik-quiz-time"
                      type="number"
                      min={0}
                      className={FIELD}
                      value={timeLimit}
                      onChange={(e) => setTimeLimit(Number(e.target.value))}
                    />
                  </div>
                  <div>
                    <label className={LABEL} htmlFor="sik-quiz-pass">
                      Passing score (%)
                    </label>
                    <p className={HINT}>Based on total points across questions.</p>
                    <input
                      id="sik-quiz-pass"
                      type="number"
                      min={0}
                      max={100}
                      className={FIELD}
                      value={passing}
                      onChange={(e) => setPassing(Number(e.target.value))}
                    />
                  </div>
                  <div>
                    <label className={LABEL} htmlFor="sik-quiz-attempts">
                      Attempts allowed
                    </label>
                    <p className={HINT}>Minimum 1.</p>
                    <input
                      id="sik-quiz-attempts"
                      type="number"
                      min={1}
                      className={FIELD}
                      value={attempts}
                      onChange={(e) => setAttempts(Number(e.target.value))}
                    />
                  </div>
                </div>
              </FormSection>
              <FormSection title="Publishing" description="Draft hides the quiz from learners until you publish.">
                <div>
                  <label className={LABEL} htmlFor="sik-quiz-status">
                    Status
                  </label>
                  <select id="sik-quiz-status" className={FIELD} value={status} onChange={(e) => setStatus(e.target.value)}>
                    <option value="draft">Draft</option>
                    <option value="publish">Published</option>
                    <option value="private">Private</option>
                  </select>
                </div>
              </FormSection>
              <ProQuizFields
                values={proQuizValues}
                onChange={setProQuizValues}
                maxRandomDrawPerQuiz={advQuiz.enabled && advQuiz.licenseOk ? quizAdvMaxDraw : QUIZ_ADVANCED_BANK_DRAW_HARD_MAX}
              />
              <ProGradebookQuizWeightFields
                gradeWeight={proQuizValues.gradeWeight}
                onGradeWeightChange={(w) => setProQuizValues((v) => ({ ...v, gradeWeight: w }))}
              />
            </div>
          ) : (
            <div className="space-y-6" role="tabpanel">
              <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/60">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                  <div className="min-w-0 flex-1">
                    <label className={LABEL} htmlFor="sik-quiz-q-search">
                      Add questions
                    </label>
                    <p className={HINT}>
                      Search your Question library, then add items to this quiz. Order matters (drag to reorder).
                    </p>
                    <input
                      id="sik-quiz-q-search"
                      className={FIELD}
                      value={questionSearch}
                      onChange={(e) => setQuestionSearch(e.target.value)}
                      placeholder="Search questions…"
                    />
                  </div>
                  <div className="flex shrink-0 flex-wrap items-center justify-start gap-2 sm:justify-end">
                    <button
                      type="button"
                      className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                      onClick={openAddQuestion}
                    >
                      + New question
                    </button>
                    <a
                      href={appViewHref(config, 'content-library', { tab: 'questions' })}
                      className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                      Manage library
                    </a>
                  </div>
                </div>
                {questionError ? (
                  <div className="mt-3">
                    <ApiErrorPanel error={questionError} title="Could not load questions" onRetry={() => setQuestionSearch((s) => s)} />
                  </div>
                ) : null}
                {questionLoading ? (
                  <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">Loading questions…</p>
                ) : (
                  <div className="mt-3 grid gap-3 lg:grid-cols-2">
                    <div className="min-w-0">
                      <div className="text-xs font-semibold text-slate-500 dark:text-slate-400">Library</div>
                      <div className="mt-2 max-h-64 overflow-auto rounded-lg border border-slate-100 dark:border-slate-800">
                        <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                          {questionRows.map((q) => {
                            if (!q.id) return null;
                            const added = quizQuestionIds.includes(q.id);
                            return (
                              <li key={q.id} className="flex items-center justify-between gap-3 px-3 py-2">
                                <span className="min-w-0 flex-1 truncate text-sm text-slate-700 dark:text-slate-200">
                                  {q.title || `Question #${q.id}`}
                                </span>
                                <button
                                  type="button"
                                  className={`shrink-0 rounded-md px-2.5 py-1.5 text-xs font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 ${
                                    added
                                      ? 'border border-slate-200 text-slate-400 dark:border-slate-700 dark:text-slate-500'
                                      : 'bg-brand-600 text-white hover:bg-brand-700'
                                  }`}
                                  disabled={added}
                                  onClick={() => setQuizQuestionIds((prev) => (prev.includes(q.id) ? prev : [...prev, q.id]))}
                                >
                                  {added ? 'Added' : 'Add'}
                                </button>
                              </li>
                            );
                          })}
                          {!questionRows.length ? (
                            <li className="px-3 py-6 text-center text-xs text-slate-500 dark:text-slate-400">
                              No questions found.
                            </li>
                          ) : null}
                        </ul>
                      </div>
                    </div>
                    <div className="min-w-0">
                      <div className="text-xs font-semibold text-slate-500 dark:text-slate-400">Selected in this quiz</div>
                      <div className="mt-2 rounded-lg border border-slate-100 dark:border-slate-800">
                        <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                          {quizQuestionIds.map((qid) => {
                            const row = questionRows.find((r) => r.id === qid);
                            return (
                              <li
                                key={qid}
                                className="flex items-center gap-3 px-3 py-2"
                                onDragOver={(e) => {
                                  if (![...e.dataTransfer.types].includes(QUIZ_Q_DND_MIME)) return;
                                  e.preventDefault();
                                  e.dataTransfer.dropEffect = 'move';
                                }}
                                onDrop={(e) => {
                                  e.preventDefault();
                                  const raw = e.dataTransfer.getData(QUIZ_Q_DND_MIME);
                                  const fromId = Number(raw);
                                  if (!fromId) return;
                                  setQuizQuestionIds((prev) => reorderIds(prev, fromId, qid));
                                }}
                              >
                                <span
                                  className="cursor-grab select-none text-slate-400 hover:text-slate-600 active:cursor-grabbing dark:hover:text-slate-300"
                                  draggable
                                  onDragStart={(e) => {
                                    e.dataTransfer.setData(QUIZ_Q_DND_MIME, String(qid));
                                    e.dataTransfer.effectAllowed = 'move';
                                  }}
                                  title="Drag to reorder"
                                  aria-label="Drag to reorder"
                                >
                                  <NavIcon name="dragHandle" className="h-4 w-4" />
                                </span>
                                <span className="min-w-0 flex-1 truncate text-sm text-slate-700 dark:text-slate-200">
                                  {row?.title || `Question #${qid}`}
                                </span>
                                <a
                                  href={appViewHref(config, 'edit-content', { post_type: 'sik_question', post_id: String(qid) })}
                                  target="_blank"
                                  rel="noreferrer noopener"
                                  className="shrink-0 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                  title="Edit this question in a new tab"
                                >
                                  Edit
                                </a>
                                <button
                                  type="button"
                                  className="shrink-0 rounded-md px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 dark:text-red-400 dark:hover:bg-red-950/30"
                                  onClick={() => setQuizQuestionIds((prev) => prev.filter((x) => x !== qid))}
                                >
                                  Remove
                                </button>
                              </li>
                            );
                          })}
                          {!quizQuestionIds.length ? (
                            <li className="px-3 py-6 text-center text-xs text-slate-500 dark:text-slate-400">
                              No questions added yet.
                            </li>
                          ) : null}
                        </ul>
                      </div>
                      <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Changes are saved when you click “Save”.
                      </p>
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
        </section>
      </div>
      <AddQuestionAuthoringModal
        config={config}
        open={addQuestionOpen}
        onClose={() => setAddQuestionOpen(false)}
        onCreated={(id) => {
          if (!id) return;
          setQuizQuestionIds((prev) => (prev.includes(id) ? prev : [...prev, id]));
          setQuestionRefresh((n) => n + 1);
        }}
        onPickExisting={(id) => {
          if (!id) return;
          setQuizQuestionIds((prev) => (prev.includes(id) ? prev : [...prev, id]));
          setAddQuestionOpen(false);
        }}
        pickExistingLabel="Add to quiz"
      />
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} canSave={Boolean(title.trim())} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
          canSave={Boolean(title.trim())}
          onSave={() => void onSave()}
          onTrash={moveToTrash}
        />
      )}
    </EditorFormShell>
  );
}

export function QuestionEditor(props: ContentEditorProps) {
  const { config, postId, backHref, entityLabel, onSavedNewId, embedded } = props;
  const editor = useWpContentPost('sik_question', postId);
  const moveToTrash = useMoveToTrash(editor, backHref, entityLabel);
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
  const [saveMsg, setSaveMsg] = useState<string | null>(null);
  const [editorTab, setEditorTab] = useState<'content' | 'settings'>('content');
  const [proQuestionValues, setProQuestionValues] = useState<ProQuestionValues>(PRO_QUESTION_DEFAULTS);
  const [typeMenuPos, setTypeMenuPos] = useState<{ top: number; left: number } | null>(null);

  const advQuiz = useAddonEnabled('quiz_advanced');
  const advFeatureOk = isFeatureEnabled(config, 'quiz_advanced');
  const canUseAdvancedTypes = Boolean(advFeatureOk && advQuiz.enabled && advQuiz.licenseOk);
  const isLockedType = useCallback(
    (t: string) => {
      const key = String(t || '').trim();
      const needs = QUESTION_PICKER_TYPES.find((x) => x.type === key)?.requiresAdvancedQuiz;
      return Boolean(needs && !canUseAdvancedTypes);
    },
    [canUseAdvancedTypes]
  );

  useEffect(() => {
    if (editor.isNew) {
      setTitle('');
      setContent('');
      setStatus('draft');
      setFeatured(0);
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
      setProQuestionValues(PRO_QUESTION_DEFAULTS);
      return;
    }
    if (!editor.post) {
      return;
    }
    const p = editor.post;
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
      const parsed = parseQuestionCorrectJson(rawCorrect) as { matching?: { left?: string[]; right?: string[]; map?: number[] } } | null;
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
  }, [editor.post, editor.isNew]);

  const onTypeChange = (v: string) => {
    if (isLockedType(v)) {
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

  const onSave = async () => {
    setSaveMsg(null);
    editor.setError(null);
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

    const body: Record<string, unknown> = {
      title,
      content,
      status,
      featured_media: featured > 0 ? featured : 0,
      meta: {
        _sikshya_question_type: qType,
        _sikshya_question_points: Math.max(0, points),
        _sikshya_question_options: optionsPayload,
        _sikshya_question_correct_answer: correctPayload,
        ...buildProQuestionMeta(proQuestionValues),
      },
    };
    try {
      const res = await editor.save(body);
      if (editor.isNew && res && typeof res === 'object' && 'id' in res) {
        const id = (res as { id: number }).id;
        if (typeof id === 'number' && id > 0) {
          onSavedNewId?.(id);
          return;
        }
      }
      setSaveMsg('Question saved.');
      await editor.load();
    } catch {
      /* hook */
    }
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

  return (
    <EditorFormShell
      loading={editor.loading}
      saving={editor.saving}
      error={editor.error}
      onRetry={() => void editor.load()}
      saveMsg={saveMsg}
    >
      <div className="space-y-3">
        <HorizontalEditorTabs
          ariaLabel="Question editor sections"
          tabs={[
            { id: 'content', label: 'Content', icon: 'plusDocument' },
            { id: 'settings', label: 'Settings', icon: 'cog' },
          ]}
          value={editorTab}
          onChange={(id) => setEditorTab(id as 'content' | 'settings')}
        />

        <section className={EDITOR_SURFACE} aria-label="Question editor">
        <div className="border-b border-slate-100 px-6 pb-4 pt-6 dark:border-slate-800">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-white">
                {title?.trim() ? title : 'Question'}
              </h2>
              <p className={HINT}>
                Content: type, question, answers, optional illustration. Settings: points and publish status. Reuse questions from the Questions library in any quiz.
              </p>
            </div>
            <div className="flex shrink-0 flex-wrap items-center justify-start gap-2 sm:justify-end">
              <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize ${statusPillClass(status)}`}>
                {status || 'draft'}
              </span>
              {Number(points) > 0 ? (
                <span className="rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-semibold text-violet-800 ring-1 ring-violet-200 dark:bg-violet-950/40 dark:text-violet-200 dark:ring-violet-900/40">
                  {Number(points)} pts
                </span>
              ) : null}
              {qType?.trim() ? (
                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700">
                  {qType.replace(/_/g, ' ')}
                </span>
              ) : null}
            </div>
          </div>
        </div>
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-q-type-content">
                  Question type
                </label>
                <p className={HINT}>Each type shows different fields — multiple choice, matching, essay, and so on.</p>
                <div className="mt-1.5">
                  <button
                    type="button"
                    id="sik-q-type-content"
                    className="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-left text-sm text-slate-900 shadow-sm transition hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/20 focus-visible:ring-brand-500/35 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                    aria-haspopup="listbox"
                    aria-expanded={Boolean(typeMenuPos)}
                    onClick={(e) => openTypeMenu(e.currentTarget)}
                  >
                    <span className="min-w-0">
                      <span className="block font-medium">
                        {qType
                          ? QUESTION_PICKER_TYPES.find((t) => t.type === (qType as QuestionType))?.label || qType.replace(/_/g, ' ')
                          : 'Select question type'}
                      </span>
                      <span className="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">
                        {qType
                          ? QUESTION_PICKER_TYPES.find((t) => t.type === (qType as QuestionType))?.hint || 'Answers depend on the chosen type.'
                          : 'Choose the format first — it will unlock the right answer fields.'}
                      </span>
                    </span>
                    <NavIcon name="chevronDown" className="h-4 w-4 shrink-0 text-slate-400" />
                  </button>
                </div>
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-q-title">
                  Question text
                </label>
                <p className={HINT}>What the learner sees (plain text or short HTML).</p>
                <textarea
                  id="sik-q-title"
                  rows={4}
                  className={`${FIELD} min-h-[88px] w-full`}
                  placeholder="Enter the question stem…"
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
                      <div className="font-semibold">Choose a question type to continue</div>
                      <p className="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        Once you pick a type, Sikshya will show the right answer fields (options, matching pairs, ordering, etc.).
                      </p>
                      <div className="mt-3 flex flex-wrap gap-2">
                        {QUESTION_PICKER_TYPES.slice(0, 6).map((t) => {
                          const locked = isLockedType(t.type);
                          return (
                            <button
                              key={t.type}
                              type="button"
                              disabled={locked}
                              aria-disabled={locked}
                              title={locked ? 'Enable Advanced Quiz add-on to use this type.' : undefined}
                              className={
                                locked
                                  ? 'cursor-not-allowed rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-400 opacity-70 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500'
                                  : 'rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'
                              }
                              onClick={() => onTypeChange(t.type)}
                            >
                              {t.label}
                              {locked ? (
                                <span className="ml-2 inline-flex items-center rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                  Pro
                                </span>
                              ) : null}
                            </button>
                          );
                        })}
                      </div>
                    </div>
                  </div>
                </div>
              ) : null}
              {qType === 'multiple_choice' ? (
                <div className="space-y-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                  <div className="text-sm font-semibold text-slate-800 dark:text-slate-200">Answer choices</div>
                  <p className={HINT}>At least two options; mark the correct one.</p>
                  <ul className="space-y-3">
                    {options.map((opt, idx) => (
                      <li key={idx} className="flex flex-wrap items-start gap-3">
                        <label className="mt-2.5 flex shrink-0 cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-400">
                          <input
                            type="radio"
                            name="sik-q-correct"
                            checked={correctAnswer === String(idx)}
                            onChange={() => setCorrectAnswer(String(idx))}
                            className="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500"
                          />
                          Correct
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
                          placeholder={`Option ${idx + 1}`}
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
                          Remove
                        </button>
                      </li>
                    ))}
                  </ul>
                  <button
                    type="button"
                    className="text-sm font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                    onClick={() => setOptions((prev) => [...prev, ''])}
                  >
                    + Add option
                  </button>
                </div>
              ) : null}
              {qType === 'multiple_response' ? (
                <div className="space-y-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                  <div className="text-sm font-semibold text-slate-800 dark:text-slate-200">Answer choices</div>
                  <p className={HINT}>Check every option that should be marked correct.</p>
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
                                  : [...prev, idx].sort((a, b) => a - b),
                              )
                            }
                            className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                          />
                          Correct
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
                          placeholder={`Option ${idx + 1}`}
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
                                .sort((a, b) => a - b),
                            );
                          }}
                          disabled={options.length <= 2}
                        >
                          Remove
                        </button>
                      </li>
                    ))}
                  </ul>
                  <button
                    type="button"
                    className="text-sm font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                    onClick={() => setOptions((prev) => [...prev, ''])}
                  >
                    + Add option
                  </button>
                </div>
              ) : null}
              {qType === 'true_false' ? (
                <div>
                  <label className={LABEL} htmlFor="sik-q-tf">
                    Correct answer
                  </label>
                  <p className={HINT}>Learners choose between true and false.</p>
                  <select
                    id="sik-q-tf"
                    className={FIELD}
                    value={correctAnswer === 'false' ? 'false' : 'true'}
                    onChange={(e) => setCorrectAnswer(e.target.value)}
                  >
                    <option value="true">True</option>
                    <option value="false">False</option>
                  </select>
                </div>
              ) : null}
              {qType === 'short_answer' || qType === 'fill_blank' ? (
                <div>
                  <label className={LABEL} htmlFor="sik-q-expected">
                    {qType === 'fill_blank' ? 'Accepted answer(s)' : 'Expected answer'}
                  </label>
                  <p className={HINT}>
                    {qType === 'fill_blank'
                      ? 'Separate multiple correct spellings with | (pipe). Comparison is case-insensitive.'
                      : 'Case-insensitive match. Use | between alternative correct answers.'}
                  </p>
                  <input
                    id="sik-q-expected"
                    className={FIELD}
                    value={correctAnswer}
                    onChange={(e) => setCorrectAnswer(e.target.value)}
                    placeholder={qType === 'fill_blank' ? 'e.g. Paris|paris' : 'e.g. photosynthesis'}
                  />
                </div>
              ) : null}
              {qType === 'ordering' ? (
                <div className="space-y-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                  <div className="text-sm font-semibold text-slate-800 dark:text-slate-200">Steps / items</div>
                  <p className={HINT}>Edit labels, then arrange the correct order (top = first).</p>
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
                          placeholder={`Item ${idx + 1}`}
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
                          Remove
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
                    + Add item
                  </button>
                  <div className="mt-4 text-sm font-semibold text-slate-800 dark:text-slate-200">Correct order</div>
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
                          Up
                        </button>
                        <button
                          type="button"
                          className="text-xs font-medium text-slate-600 dark:text-slate-400"
                          onClick={() => moveOrderSlot(pos, 1)}
                          disabled={pos >= orderPerm.length - 1}
                        >
                          Down
                        </button>
                      </li>
                    ))}
                  </ol>
                </div>
              ) : null}
              {qType === 'matching' ? (
                <div className="space-y-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                  <div className="text-sm font-semibold text-slate-800 dark:text-slate-200">Matching pairs</div>
                  <p className={HINT}>For each prompt on the left, choose the matching answer column index.</p>
                  <ul className="space-y-3">
                    {matchLeft.map((left, i) => (
                      <li key={i} className="grid gap-2 sm:grid-cols-2 sm:items-end">
                        <label className="block min-w-0">
                          <span className={HINT}>Prompt {i + 1}</span>
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
                            placeholder="Left column text"
                          />
                        </label>
                        <div className="flex flex-wrap gap-2">
                          <label className="block min-w-0 flex-1">
                            <span className={HINT}>Matches answer</span>
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
                  <div className="mt-4 text-sm font-semibold text-slate-800 dark:text-slate-200">Answer column</div>
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
                          placeholder={`Answer ${i + 1}`}
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
                      + Add prompt
                    </button>
                    <button
                      type="button"
                      className="text-sm font-semibold text-brand-600"
                      onClick={() => setMatchRight((prev) => [...prev, ''])}
                    >
                      + Add answer
                    </button>
                  </div>
                </div>
              ) : null}
              {qType === 'essay' ? (
                <p className="text-sm text-slate-600 dark:text-slate-400">
                  Essays are graded manually. Use the explanation field below for model answers or staff notes.
                </p>
              ) : null}
              <EditorFeaturedImageField
                fieldId="sik-q-featured"
                attachmentId={featured}
                onAttachmentIdChange={setFeatured}
                description="Optional image shown with the question in supported themes or future quiz layouts."
              />
              <div>
                <label className={LABEL} htmlFor="sik-q-body">
                  Explanation / feedback (optional)
                </label>
                <p className={HINT}>Shown after grading or kept for instructors. Stored as post content.</p>
                <textarea
                  id="sik-q-body"
                  rows={8}
                  className={`${FIELD} min-h-[160px] w-full`}
                  placeholder="Optional explanation for learners or grading notes…"
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                />
              </div>
            </div>
          ) : (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-q-points">
                  Points
                </label>
                <p className={HINT}>Awarded when the answer is correct (auto-graded types only).</p>
                <input
                  id="sik-q-points"
                  type="number"
                  min={0}
                  className={FIELD}
                  placeholder="1"
                  value={points}
                  onChange={(e) => setPoints(Number(e.target.value))}
                />
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-q-status">
                  Status
                </label>
                <p className={HINT}>Draft stays hidden from learners until published.</p>
                <select id="sik-q-status" className={FIELD} value={status} onChange={(e) => setStatus(e.target.value)}>
                  <option value="draft">Draft</option>
                  <option value="publish">Published</option>
                </select>
              </div>
              <ProQuestionFields values={proQuestionValues} onChange={setProQuestionValues} />
            </div>
          )}
        </div>
        </section>
      </div>
      {typeMenuPos ? (
        <div
          className="fixed inset-0 z-[90]"
          onMouseDown={() => setTypeMenuPos(null)}
          aria-hidden
        />
      ) : null}
      {typeMenuPos ? (
        <div
          className="fixed z-[100] w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
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
              const lockTitle = locked ? 'Enable Advanced Quiz add-on to use this type.' : undefined;
              return (
                <li key={t.type}>
                  <button
                    type="button"
                    role="option"
                    aria-selected={active}
                    aria-disabled={locked}
                    disabled={locked}
                    title={lockTitle}
                    className={`flex w-full items-start gap-3 rounded-xl px-3 py-2 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 ${
                      active
                        ? 'bg-brand-50 text-brand-900 dark:bg-brand-950/40 dark:text-brand-100'
                        : locked
                        ? 'cursor-not-allowed text-slate-400 opacity-70 dark:text-slate-500'
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
                      <span className="block text-sm font-semibold leading-snug">
                        {t.label}
                        {locked ? (
                          <span className="ml-2 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                            Pro
                          </span>
                        ) : null}
                      </span>
                      <span className="mt-0.5 block text-xs leading-snug text-slate-500 dark:text-slate-400">{t.hint}</span>
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        </div>
      ) : null}
      {embedded ? (
        <EmbeddedSaveBar
          saving={editor.saving}
          entityLabel={entityLabel}
          canSave={Boolean(title.trim() && qType.trim())}
          onSave={() => void onSave()}
        />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
          canSave={Boolean(title.trim() && qType.trim())}
          onSave={() => void onSave()}
          onTrash={moveToTrash}
        />
      )}
    </EditorFormShell>
  );
}

export function AssignmentEditor(props: ContentEditorProps) {
  const { postId, backHref, entityLabel, onSavedNewId, embedded } = props;
  const editor = useWpContentPost('sik_assignment', postId);
  const moveToTrash = useMoveToTrash(editor, backHref, entityLabel);
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [status, setStatus] = useState('draft');
  const [due, setDue] = useState('');
  const [apoints, setApoints] = useState(10);
  const [atype, setAtype] = useState('');
  const [featured, setFeatured] = useState(0);
  const [saveMsg, setSaveMsg] = useState<string | null>(null);
  const [editorTab, setEditorTab] = useState<'content' | 'settings'>('content');
  const [proAsgValues, setProAsgValues] = useState<ProAssignmentValues>(PRO_ASSIGNMENT_DEFAULTS);

  useEffect(() => {
    if (editor.isNew) {
      setTitle('');
      setContent('');
      setStatus('draft');
      setDue('');
      setApoints(10);
      setAtype('');
      setFeatured(0);
      setProAsgValues(PRO_ASSIGNMENT_DEFAULTS);
      return;
    }
    if (!editor.post) {
      return;
    }
    const p = editor.post;
    setTitle(titleFromPost(p));
    setContent(contentFromPost(p));
    setStatus(p.status || 'draft');
    setFeatured(typeof p.featured_media === 'number' ? p.featured_media : 0);
    const m = p.meta as Record<string, unknown> | undefined;
    setDue(String(readMeta(m, '_sikshya_assignment_due_date') ?? ''));
    setApoints(Number(readMeta(m, '_sikshya_assignment_points') ?? 10));
    setAtype(String(readMeta(m, '_sikshya_assignment_type') ?? ''));
    setProAsgValues(readProAssignmentFromMeta(m));
  }, [editor.post, editor.isNew]);

  const onSave = async () => {
    setSaveMsg(null);
    editor.setError(null);
    const body: Record<string, unknown> = {
      title,
      content,
      status,
      featured_media: featured > 0 ? featured : 0,
      meta: {
        _sikshya_assignment_due_date: due,
        _sikshya_assignment_points: Math.max(0, apoints),
        _sikshya_assignment_type: atype,
        ...buildProAssignmentMeta(proAsgValues),
      },
    };
    try {
      const res = await editor.save(body);
      if (editor.isNew && res && typeof res === 'object' && 'id' in res) {
        const id = (res as { id: number }).id;
        if (typeof id === 'number' && id > 0) {
          onSavedNewId?.(id);
          return;
        }
      }
      setSaveMsg('Assignment saved.');
      await editor.load();
    } catch {
      /* hook */
    }
  };

  return (
    <EditorFormShell
      loading={editor.loading}
      saving={editor.saving}
      error={editor.error}
      onRetry={() => void editor.load()}
      saveMsg={saveMsg}
    >
      <div className="space-y-3">
        <HorizontalEditorTabs
          ariaLabel="Assignment editor sections"
          tabs={[
            { id: 'content', label: 'Content', icon: 'plusDocument' },
            { id: 'settings', label: 'Settings', icon: 'cog' },
          ]}
          value={editorTab}
          onChange={(id) => setEditorTab(id as 'content' | 'settings')}
        />

        <section className={EDITOR_SURFACE} aria-label="Assignment editor">
        <div className="border-b border-slate-100 px-6 pb-4 pt-6 dark:border-slate-800">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-white">
                {title?.trim() ? title : 'Assignment'}
              </h2>
              <p className={HINT}>
                Content holds the title, instructions, and optional cover image; Settings holds due date, points, submission type, and status.
              </p>
            </div>
            <div className="flex shrink-0 flex-wrap items-center justify-start gap-2 sm:justify-end">
              <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize ${statusPillClass(status)}`}>
                {status || 'draft'}
              </span>
              <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                {Math.max(0, Number.isFinite(apoints) ? apoints : 0)} pts
              </span>
              {due?.trim() ? (
                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                  Due {due}
                </span>
              ) : null}
              {atype?.trim() ? (
                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                  {atype}
                </span>
              ) : null}
            </div>
          </div>
        </div>
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-as-title">
                  Title
                </label>
                <input
                  id="sik-as-title"
                  className={FIELD}
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="e.g. Week 3 project brief"
                />
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-as-body">
                  Instructions & rubric
                </label>
                <p className={HINT}>What to submit, file types, length, and how you will grade (optional but clearer for learners).</p>
                <textarea
                  id="sik-as-body"
                  rows={12}
                  className={`${FIELD} min-h-[240px] w-full`}
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                  placeholder="Task description, deliverables, and grading criteria…"
                />
              </div>
              <EditorFeaturedImageField
                fieldId="sik-as-featured"
                attachmentId={featured}
                onAttachmentIdChange={setFeatured}
                description="Optional image for assignment lists or course outlines when your theme supports featured images."
              />
            </div>
          ) : (
            <div className="space-y-6" role="tabpanel">
              <FormSection title="Submission" description="Scheduling and what learners submit.">
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                  <div>
                    <label className={LABEL} htmlFor="sik-as-due">
                      Due (datetime)
                    </label>
                    <p className={HINT}>Optional. Uses your site timezone.</p>
                    <DateTimePickerField kind="datetime" value={due} onChange={setDue} />
                  </div>
                  <div>
                    <label className={LABEL} htmlFor="sik-as-points">
                      Points
                    </label>
                    <p className={HINT}>Maximum score when graded.</p>
                    <input
                      id="sik-as-points"
                      type="number"
                      min={0}
                      className={FIELD}
                      value={apoints}
                      onChange={(e) => setApoints(Number(e.target.value))}
                    />
                  </div>
                  <div>
                    <label className={LABEL} htmlFor="sik-as-type">
                      Submission type
                    </label>
                    <select id="sik-as-type" className={FIELD} value={atype} onChange={(e) => setAtype(e.target.value)}>
                      <option value="">Choose how learners submit…</option>
                      <option value="essay">Essay</option>
                      <option value="file_upload">File upload</option>
                      <option value="url_submission">URL</option>
                    </select>
                  </div>
                </div>
              </FormSection>
              <FormSection title="Publishing">
                <div>
                  <label className={LABEL} htmlFor="sik-as-status">
                    Status
                  </label>
                  <select id="sik-as-status" className={FIELD} value={status} onChange={(e) => setStatus(e.target.value)}>
                    <option value="draft">Draft</option>
                    <option value="publish">Published</option>
                  </select>
                </div>
              </FormSection>
              <ProAssignmentFields values={proAsgValues} onChange={setProAsgValues} />
              <ProGradebookAssignmentWeightFields
                gradeWeight={proAsgValues.gradeWeight}
                onGradeWeightChange={(w) => setProAsgValues((v) => ({ ...v, gradeWeight: w }))}
              />
            </div>
          )}
        </div>
        </section>
      </div>
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} canSave={Boolean(title.trim())} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
          canSave={Boolean(title.trim())}
          onSave={() => void onSave()}
          onTrash={moveToTrash}
        />
      )}
    </EditorFormShell>
  );
}

type CourseOpt = { id: number; title: string };

export function ChapterEditor(props: ContentEditorProps) {
  const { postId, backHref, entityLabel, onSavedNewId, embedded, forcedCourseId } = props;
  const editor = useWpContentPost('sik_chapter', postId);
  const moveToTrash = useMoveToTrash(editor, backHref, entityLabel);
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [status, setStatus] = useState('draft');
  const [order, setOrder] = useState(0);
  const [courseId, setCourseId] = useState(0);
  const [courses, setCourses] = useState<CourseOpt[]>([]);
  const [featured, setFeatured] = useState(0);
  const [saveMsg, setSaveMsg] = useState<string | null>(null);
  const [editorTab, setEditorTab] = useState<'content' | 'settings'>('content');

  useEffect(() => {
    let cancelled = false;
    void getWpApi()
      .get<{ id: number; title: { rendered: string } }[]>('/sik_course?per_page=100&status=any&orderby=title&order=asc')
      .then((rows) => {
        if (cancelled || !Array.isArray(rows)) {
          return;
        }
        setCourses(
          rows.map((r) => ({
            id: r.id,
            title: r.title?.rendered ? r.title.rendered.replace(/<[^>]+>/g, '') : `Course #${r.id}`,
          }))
        );
      })
      .catch(() => {
        if (!cancelled) {
          setCourses([]);
        }
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (forcedCourseId && forcedCourseId > 0) {
      setCourseId(forcedCourseId);
    }
  }, [forcedCourseId]);

  useEffect(() => {
    if (editor.isNew) {
      setTitle('');
      setContent('');
      setStatus('draft');
      setOrder(0);
      setFeatured(0);
      setCourseId(forcedCourseId && forcedCourseId > 0 ? forcedCourseId : 0);
      return;
    }
    if (!editor.post) {
      return;
    }
    const p = editor.post;
    setTitle(titleFromPost(p));
    setContent(contentFromPost(p));
    setStatus(p.status || 'draft');
    setFeatured(typeof p.featured_media === 'number' ? p.featured_media : 0);
    const m = p.meta as Record<string, unknown> | undefined;
    setOrder(Number(readMeta(m, '_sikshya_chapter_order') ?? 0));
    const fromMeta = Number(readMeta(m, '_sikshya_chapter_course_id') ?? 0);
    setCourseId(fromMeta > 0 ? fromMeta : forcedCourseId && forcedCourseId > 0 ? forcedCourseId : 0);
  }, [editor.post, editor.isNew, forcedCourseId]);

  const onSave = async () => {
    setSaveMsg(null);
    editor.setError(null);
    const effectiveCourse = forcedCourseId && forcedCourseId > 0 ? forcedCourseId : courseId;
    const body: Record<string, unknown> = {
      title,
      content,
      status,
      featured_media: featured > 0 ? featured : 0,
      meta: {
        _sikshya_chapter_order: Math.max(0, order),
        _sikshya_chapter_course_id: effectiveCourse > 0 ? effectiveCourse : 0,
      },
    };
    try {
      const res = await editor.save(body);
      if (editor.isNew && res && typeof res === 'object' && 'id' in res) {
        const id = (res as { id: number }).id;
        if (typeof id === 'number' && id > 0) {
          onSavedNewId?.(id);
          return;
        }
      }
      setSaveMsg('Chapter saved.');
      await editor.load();
    } catch {
      /* hook */
    }
  };

  return (
    <EditorFormShell
      loading={editor.loading}
      saving={editor.saving}
      error={editor.error}
      onRetry={() => void editor.load()}
      saveMsg={saveMsg}
    >
      <div className="space-y-3">
        <HorizontalEditorTabs
          ariaLabel="Chapter editor sections"
          tabs={[
            { id: 'content', label: 'Content', icon: 'plusDocument' },
            { id: 'settings', label: 'Settings', icon: 'cog' },
          ]}
          value={editorTab}
          onChange={(id) => setEditorTab(id as 'content' | 'settings')}
        />

        <section className={EDITOR_SURFACE} aria-label="Chapter editor">
        <div className="border-b border-slate-100 px-6 pb-4 pt-6 dark:border-slate-800">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-white">
                {title?.trim() ? title : 'Chapter'}
              </h2>
              <p className={HINT}>
                Content: chapter title, intro text, and optional cover image. Settings: which course it belongs to, sort order, and publish status.
              </p>
            </div>
            <div className="flex shrink-0 flex-wrap items-center justify-start gap-2 sm:justify-end">
              <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize ${statusPillClass(status)}`}>
                {status || 'draft'}
              </span>
              {order > 0 ? (
                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700">
                  Order {order}
                </span>
              ) : null}
            </div>
          </div>
        </div>
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-ch-title">
                  Chapter title
                </label>
                <input
                  id="sik-ch-title"
                  className={FIELD}
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="e.g. Getting started"
                />
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-ch-body">
                  Description / intro
                </label>
                <textarea
                  id="sik-ch-body"
                  rows={8}
                  className={`${FIELD} min-h-[140px] w-full`}
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                  placeholder="Optional text shown above the lessons in this chapter…"
                />
              </div>
              <EditorFeaturedImageField
                fieldId="sik-ch-featured"
                attachmentId={featured}
                onAttachmentIdChange={setFeatured}
                description="Optional banner or icon for this chapter in the course outline when your theme supports featured images."
              />
            </div>
          ) : (
            <div className="space-y-6" role="tabpanel">
              <FormSection title="Placement" description="Which course outline this chapter belongs to.">
                <div>
                  <label className={LABEL} htmlFor="sik-ch-course">
                    Parent course
                  </label>
                  {forcedCourseId && forcedCourseId > 0 ? (
                    <div
                      id="sik-ch-course"
                      className={`${FIELD} border-dashed bg-slate-50 text-slate-700 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-200`}
                    >
                      {courses.find((c) => c.id === forcedCourseId)?.title || `Course #${forcedCourseId}`}
                    </div>
                  ) : (
                    <select
                      id="sik-ch-course"
                      className={FIELD}
                      value={courseId}
                      onChange={(e) => setCourseId(Number(e.target.value))}
                    >
                      <option value={0}>Choose a course…</option>
                      {courses.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.title}
                        </option>
                      ))}
                    </select>
                  )}
                </div>
              </FormSection>
              <FormSection title="Ordering" description="Lower numbers appear first in the course outline.">
                <div>
                  <label className={LABEL} htmlFor="sik-ch-order">
                    Sort order
                  </label>
                  <input
                    id="sik-ch-order"
                    type="number"
                    min={0}
                    className={FIELD}
                    value={order}
                    onChange={(e) => setOrder(Number(e.target.value))}
                  />
                </div>
              </FormSection>
              <FormSection title="Publishing">
                <div>
                  <label className={LABEL} htmlFor="sik-ch-status">
                    Status
                  </label>
                  <select id="sik-ch-status" className={FIELD} value={status} onChange={(e) => setStatus(e.target.value)}>
                    <option value="draft">Draft</option>
                    <option value="publish">Published</option>
                  </select>
                </div>
              </FormSection>
            </div>
          )}
        </div>
        </section>
      </div>
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} canSave={Boolean(title.trim())} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
          canSave={Boolean(title.trim())}
          onSave={() => void onSave()}
          onTrash={moveToTrash}
        />
      )}
    </EditorFormShell>
  );
}

/** Visual certificate builder (drag-and-drop canvas) + generated HTML in post content. */
export function CertificateEditor(props: ContentEditorProps) {
  const { postId, backHref, entityLabel, onSavedNewId, embedded, config } = props;
  const editor = useWpContentPost('sikshya_certificate', postId);
  const moveToTrash = useMoveToTrash(editor, backHref, entityLabel);
  const [title, setTitle] = useState('');
  const [status, setStatus] = useState('draft');
  const [featuredId, setFeaturedId] = useState(0);
  const [featuredPreview, setFeaturedPreview] = useState('');
  const [orientation, setOrientation] = useState<'landscape' | 'portrait'>('landscape');
  const [pageSize, setPageSize] = useState<'letter' | 'a4' | 'a5'>('a4');
  const [pageFinish, setPageFinish] = useState<CertificatePageFinish>(DEFAULT_CERTIFICATE_PAGE_FINISH);
  const [toast, setToast] = useState<{
    open: boolean;
    kind: 'success' | 'error';
    title: string;
    message?: string;
  } | null>(null);
  const [layout, setLayout] = useState<CertLayoutFile>(() => defaultCertificateLayout());
  const layoutRef = useRef<CertLayoutFile>(layout);

  // Certificate Builder is a Pro addon feature; gate the workspace when it's off.
  const featureOk = isFeatureEnabled(config, 'certificates_advanced');
  const addon = useAddonEnabled('certificates_advanced');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);

  // Saving can be triggered immediately after a drag/resize gesture; keep a ref so we always use the latest layout.
  useEffect(() => {
    layoutRef.current = layout;
  }, [layout]);

  useEffect(() => {
    if (editor.isNew) {
      setTitle('');
      setStatus('draft');
      setFeaturedId(0);
      setFeaturedPreview('');
      setOrientation('landscape');
      setPageSize('a4');
      setPageFinish(DEFAULT_CERTIFICATE_PAGE_FINISH);
      setLayout(defaultCertificateLayout());
      return;
    }
    if (!editor.post) {
      return;
    }
    const p = editor.post;
    setTitle(titleFromPost(p));
    setStatus(p.status || 'draft');
    const fm = typeof p.featured_media === 'number' ? p.featured_media : 0;
    setFeaturedId(fm);
    const m = p.meta as Record<string, unknown> | undefined;
    const o = String(readMeta(m, '_sikshya_certificate_orientation') || 'landscape');
    setOrientation(o === 'portrait' ? 'portrait' : 'landscape');
    const s = String(readMeta(m, '_sikshya_certificate_page_size') || 'a4');
    setPageSize(s === 'a4' ? 'a4' : s === 'a5' ? 'a5' : 'letter');
    setPageFinish(
      parseCertificatePageFinish(
        readMeta(m, '_sikshya_certificate_page_color'),
        readMeta(m, '_sikshya_certificate_page_pattern'),
        readMeta(m, '_sikshya_certificate_page_deco')
      )
    );
    const rawLayout = readMeta(m, '_sikshya_certificate_layout');
    setLayout(parseLayoutFromMeta(rawLayout));
  }, [editor.post, editor.isNew]);

  const publicPreviewHref = useMemo(() => {
    return String((editor.post as any)?.sikshya_certificate_preview_url || '').trim();
  }, [editor.post]);

  // The Free build seeds two ready-to-use templates and locks them against
  // deletion via TemplateGuard. Mirror that lock in the UI so the affordance
  // is hidden — the Pro filter is the single source of truth at the boundary.
  const isProtectedTemplate = useMemo(() => {
    const m = editor.post?.meta as Record<string, unknown> | undefined;
    if (!m) {
      return false;
    }
    const locked = readMeta(m, '_sikshya_certificate_default_locked');
    if (locked === true || locked === '1' || locked === 1) {
      return true;
    }
    return readMeta(m, '_sikshya_certificate_default') === '1';
  }, [editor.post]);

  useEffect(() => {
    if (editor.isNew || featuredId <= 0) {
      if (!editor.isNew && featuredId <= 0) {
        setFeaturedPreview('');
      }
      return;
    }
    let cancelled = false;
    void getWpApi()
      .get<{ source_url?: string }>(`/media/${featuredId}`)
      .then((media) => {
        if (!cancelled && media?.source_url) {
          setFeaturedPreview(media.source_url);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setFeaturedPreview('');
        }
      });
    return () => {
      cancelled = true;
    };
  }, [featuredId, editor.isNew]);

  // Auto-dismiss success toasts so they don't pile up.
  useEffect(() => {
    if (!toast?.open || toast.kind !== 'success') {
      return;
    }
    const timer = window.setTimeout(() => setToast(null), 2600);
    return () => window.clearTimeout(timer);
  }, [toast]);

  const onSave = async (nextStatus?: string) => {
    setToast(null);
    editor.setError(null);
    const targetStatus = nextStatus || status;
    const latestLayout = layoutRef.current;
    const generated = layoutToHtml(latestLayout, {
      pageAspect: getPageAspectCss(orientation, pageSize),
      pageColor: pageFinish.pageColor,
      pagePattern: pageFinish.pagePattern,
      pageDeco: pageFinish.pageDeco,
      pageFeaturedImageUrl: featuredPreview,
    });
    const body: Record<string, unknown> = {
      title,
      content: generated,
      status: targetStatus,
      featured_media: featuredId > 0 ? featuredId : 0,
      meta: {
        _sikshya_certificate_orientation: orientation,
        _sikshya_certificate_page_size: pageSize,
        _sikshya_certificate_page_color: pageFinish.pageColor,
        _sikshya_certificate_page_pattern: pageFinish.pagePattern,
        _sikshya_certificate_page_deco: pageFinish.pageDeco,
        _sikshya_certificate_layout: layoutToStorage(latestLayout),
      },
    };
    try {
      const res = await editor.save(body);
      if (editor.isNew && res && typeof res === 'object' && 'id' in res) {
        const id = (res as { id: number }).id;
        if (typeof id === 'number' && id > 0) {
          onSavedNewId?.(id);
          return;
        }
      }

      // Reload the post in edit context so verification runs against the authoritative
      // persisted state (not the save response), and the UI reflects exactly what's on disk.
      let persistedMeta: Record<string, unknown> | undefined;
      try {
        await editor.load();
        // We can't synchronously read the just-updated editor.post here (React state).
        // Fallback to the save response meta (save now uses ?context=edit, so it's populated).
        persistedMeta =
          (res && typeof res === 'object' && 'meta' in res
            ? (res as { meta?: Record<string, unknown> }).meta
            : undefined) ?? undefined;
      } catch {
        persistedMeta =
          (res && typeof res === 'object' && 'meta' in res
            ? (res as { meta?: Record<string, unknown> }).meta
            : undefined) ?? undefined;
        setToast({
          open: true,
          kind: 'error',
          title: 'Saved, but could not reload',
          message: 'Refresh this page to see the latest version.',
        });
        return;
      }

      // Verify persisted meta. WP sanitizers may coerce/strip values; when that happens, surface it.
      // If persistedMeta is entirely missing from the response (unexpected now that we request
      // context=edit), skip verification rather than raise a false alarm — the reload above
      // already re-synchronised the UI with the server.
      const expectedMeta = (body.meta || {}) as Record<string, unknown>;
      const expectedLayout = layoutRef.current;
      const expectedBlockCount = Array.isArray(expectedLayout.blocks) ? expectedLayout.blocks.length : 0;
      const mismatchedKeys: string[] = [];

      if (persistedMeta && typeof persistedMeta === 'object') {
        for (const k of Object.keys(expectedMeta)) {
          const exp = expectedMeta[k];
          const got = readMeta(persistedMeta, k);

          if (k === '_sikshya_certificate_layout') {
            // Layout is stored as a JSON string and may be re-encoded server-side.
            // Validate that block count matches what we sent (sanitizer may drop unknown blocks).
            const parsed = parseLayoutFromMeta(got);
            const gotBlocks = Array.isArray(parsed?.blocks) ? parsed.blocks.length : 0;
            if (expectedBlockCount > 0 && gotBlocks !== expectedBlockCount) {
              mismatchedKeys.push(k);
            }
            continue;
          }

          if (String(got ?? '') !== String(exp ?? '')) {
            mismatchedKeys.push(k);
          }
        }
      }

      if (mismatchedKeys.length) {
        setToast({
          open: true,
          kind: 'error',
          title: 'Some settings could not be saved',
          message: mismatchedKeys.map((k) => k.replace(/^_sikshya_certificate_/, '')).join(', '),
        });
      } else {
        setToast({
          open: true,
          kind: 'success',
          title: targetStatus === 'publish' ? 'Certificate published' : 'Saved',
        });
      }
    } catch {
      return;
    }
  };

  const openPreview = async () => {
    const win = window.open('', '_blank', 'noopener,noreferrer');
    if (!win) {
      return;
    }

    if (!postId || postId <= 0) {
      const errHtml = `<!doctype html><html lang="en"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width,initial-scale=1" />
      <title>Preview unavailable</title>
      <style>html,body{height:100%;margin:0}body{font-family:system-ui;background:#0b1220;color:#fff;display:flex;align-items:center;justify-content:center;padding:24px}
      .box{max-width:720px;width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);padding:18px 20px}</style></head>
      <body><div class="box"><strong>Save the certificate first.</strong><div style="margin-top:10px;opacity:.85;font-size:13px">Preview needs a saved certificate so the server can authorize the request.</div></div></body></html>`;
      win.document.open();
      win.document.write(errHtml);
      win.document.close();
      return;
    }

    const loadingHtml = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Certificate preview</title>
  <style>
    html,body{height:100%;margin:0}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,Arial,sans-serif;background:#f3f4f6;color:#0f172a}
    .wrap{min-height:100%;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{background:#fff;border:1px solid #e5e7eb;padding:18px 20px;max-width:520px;width:100%;box-shadow:0 18px 50px rgba(15,23,42,.10)}
    .title{font-weight:700;font-size:14px;letter-spacing:.02em}
    .sub{margin-top:6px;font-size:12px;color:#64748b;line-height:1.45}
    .bar{margin-top:14px;height:8px;background:#eef2ff;position:relative;overflow:hidden}
    .bar:before{content:"";position:absolute;left:-40%;top:0;bottom:0;width:40%;background:#6366f1;animation:move 1.1s ease-in-out infinite}
    @keyframes move{0%{left:-40%}100%{left:100%}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="title">Loading certificate preview…</div>
      <div class="sub">Generating a clean full-page preview (no theme styling).</div>
      <div class="bar"></div>
    </div>
  </div>
</body>
</html>`;

    win.document.open();
    win.document.write(loadingHtml);
    win.document.close();

    try {
      const resp = await getSikshyaApi().post<{ ok?: boolean; html?: string; title?: string }>(
        `/admin/certificates/${encodeURIComponent(String(postId))}/preview`,
        {
          html: layoutToHtml(layoutRef.current, {
            pageAspect: getPageAspectCss(orientation, pageSize),
            pageColor: pageFinish.pageColor,
            pagePattern: pageFinish.pagePattern,
            pageDeco: pageFinish.pageDeco,
            pageFeaturedImageUrl: featuredPreview,
          }),
          title: title.trim() || 'Certificate preview',
        }
      );
      const raw = resp?.html ? String(resp.html) : '';
      const t = resp?.title ? String(resp.title) : 'Certificate preview';

      if (!raw.trim()) {
        const emptyHtml = `<!doctype html><html lang="en"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>Empty preview</title>
        <style>html,body{height:100%;margin:0}body{font-family:system-ui;background:#f8fafc;color:#0f172a;display:flex;align-items:center;justify-content:center;padding:24px}
        .box{max-width:520px;text-align:center}</style></head>
        <body><div class="box"><p style="font-weight:600">No layout HTML was returned.</p><p style="margin-top:8px;font-size:13px;color:#64748b">Add blocks on the canvas, save, and try preview again.</p></div></body></html>`;
        win.document.open();
        win.document.write(emptyHtml);
        win.document.close();
        return;
      }

      const ar = getPageAspectCss(orientation, pageSize);
      const previewHtml = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>${t.replace(/</g, '&lt;')}</title>
  <style>
    html,body{height:100%;margin:0}
    body{background:#f3f4f6;color:#0f172a}
    /* prevent any inherited WP/admin styles in the new window */
    *,*::before,*::after{box-sizing:border-box}
    img{max-width:100%;height:auto}
    .stage{min-height:100%;display:flex;align-items:center;justify-content:center;padding:28px}
    .sheet{
      width:min(1200px,calc(100vw - 56px));
      max-height:calc(100vh - 56px);
      aspect-ratio:${String(ar).replace(/[^0-9./\\s]/g, '')};
      background:#fff;
      box-shadow:0 30px 80px rgba(15,23,42,.18);
      overflow:hidden;
    }
    .sheet > .sikshya-certificate-layout{width:100%;height:100%}
  </style>
</head>
<body>
  <div class="stage">
    <div class="sheet">${raw}</div>
  </div>
</body>
</html>`;

      win.document.open();
      win.document.write(previewHtml);
      win.document.close();
    } catch (e) {
      const errHtml = `<!doctype html><html><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width,initial-scale=1" />
      <title>Preview failed</title>
      <style>html,body{height:100%;margin:0}body{font-family:system-ui;background:#0b1220;color:#fff;display:flex;align-items:center;justify-content:center;padding:24px}
      .box{max-width:720px;width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);padding:18px 20px}</style></head>
      <body><div class="box"><strong>Could not load preview.</strong><div style="margin-top:10px;opacity:.85;font-size:13px">Please try saving the certificate and opening preview again.</div></div></body></html>`;
      win.document.open();
      win.document.write(errHtml);
      win.document.close();
    }
  };

  // When navigated from list row “Preview”, auto-open preview once after load.
  const didAutoPreviewRef = useRef(false);
  useEffect(() => {
    if (didAutoPreviewRef.current) {
      return;
    }
    if (String(config?.query?.open_preview || '') !== '1') {
      return;
    }
    if (editor.isNew || !editor.post) {
      return;
    }
    didAutoPreviewRef.current = true;
    // Prefer public preview link (not blocked by popup rules).
    if (publicPreviewHref) {
      window.open(publicPreviewHref, '_blank', 'noopener,noreferrer');
      return;
    }
    // Fallback: internal preview window (HTML generated from current layout).
    window.setTimeout(() => void openPreview(), 50);
  }, [config?.query?.open_preview, editor.isNew, editor.post]);

  return (
    <EditorFormShell
      loading={editor.loading}
      saving={editor.saving}
      error={editor.error}
      onRetry={() => void editor.load()}
      saveMsg={null}
    >
      <section
        className="flex h-[100dvh] w-full flex-col overflow-hidden bg-slate-100 dark:bg-slate-950"
        aria-label="Certificate editor"
      >
        {toast?.open ? (
          <div className="fixed right-6 top-6 z-[9999] w-[420px] max-w-[calc(100vw-48px)]">
            <div
              className={`rounded-2xl border px-4 py-3 shadow-lg backdrop-blur dark:backdrop-blur ${
                toast.kind === 'success'
                  ? 'border-emerald-200 bg-emerald-50/95 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/60 dark:text-emerald-100'
                  : 'border-rose-200 bg-rose-50/95 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/60 dark:text-rose-100'
              }`}
              role="status"
              aria-live="polite"
            >
              <div className="flex items-start gap-3">
                <span
                  className={`mt-0.5 flex h-9 w-9 items-center justify-center rounded-xl ${
                    toast.kind === 'success'
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200'
                      : 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200'
                  }`}
                >
                  <NavIcon name={toast.kind === 'success' ? 'badge' : 'helpCircle'} className="h-5 w-5" />
                </span>
                <div className="min-w-0 flex-1">
                  <div className="text-sm font-semibold">{toast.title}</div>
                  {toast.message ? <div className="mt-0.5 text-xs leading-snug opacity-90">{toast.message}</div> : null}
                </div>
                <button
                  type="button"
                  onClick={() => setToast(null)}
                  className="rounded-lg px-2 py-1 text-xs font-semibold opacity-70 hover:opacity-100"
                  aria-label="Dismiss"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>
        ) : null}
        <div className="shrink-0 border-b border-slate-200/80 bg-white/90 px-4 py-2.5 backdrop-blur dark:border-slate-800 dark:bg-slate-950/88 sm:px-6 xl:px-8">
          <div className="grid grid-cols-1 gap-3 xl:grid-cols-[auto_minmax(0,1fr)_auto] xl:items-center">
            <div className="flex flex-wrap items-center gap-2 xl:justify-start">
              <div className="flex flex-wrap items-center gap-2">
                <a
                  href={backHref}
                  className="rounded-md bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/50 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus-visible:ring-slate-500/45"
                >
                  ← Back
                </a>
                <span className="bg-slate-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                  {status === 'publish' ? 'Published' : 'Draft'}
                </span>
                {isProtectedTemplate ? (
                  <span
                    className="inline-flex items-center gap-1 bg-amber-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                    title="This is a default template included with Sikshya. It cannot be deleted, but you can duplicate or edit it."
                  >
                    <svg viewBox="0 0 16 16" width="10" height="10" aria-hidden="true">
                      <path
                        d="M5 7V5a3 3 0 0 1 6 0v2m-7 0h8v6H4V7Z"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.6"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      />
                    </svg>
                    Default template
                  </span>
                ) : null}
              </div>
            </div>
            <div className="min-w-0 text-left xl:text-center">
              <h1 className="truncate text-base font-semibold text-slate-900 dark:text-white">
                {title.trim() || 'Untitled certificate'}
              </h1>
              <p className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                {isProtectedTemplate
                  ? 'Built-in template — edit freely, deletion is protected.'
                  : 'Full-page certificate builder workspace.'}
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-2 xl:justify-end">
              {!editor.isNew ? (
                publicPreviewHref ? (
                  <a
                    href={publicPreviewHref}
                    target="_blank"
                    rel="noreferrer noopener"
                    className="px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
                    title="Open the public verification/preview link (uses this certificate’s preview hash)."
                  >
                    Preview
                  </a>
                ) : (
                  <button
                    type="button"
                    disabled={editor.saving}
                    onClick={() => void openPreview()}
                    title="Opens an internal layout preview window. Save once to enable the public preview link."
                    className="px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50 dark:text-slate-200 dark:hover:bg-slate-800"
                  >
                    Preview
                  </button>
                )
              ) : null}
              {!editor.isNew && status !== 'publish' ? (
                <button
                  type="button"
                  disabled={editor.saving}
                  onClick={() => void onSave('publish')}
                  className="bg-emerald-50 px-3.5 py-1.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 disabled:opacity-50 dark:bg-emerald-950/30 dark:text-emerald-300"
                >
                  {editor.saving ? 'Publishing…' : 'Publish'}
                </button>
              ) : null}
              <ButtonPrimary type="button" disabled={editor.saving} onClick={() => void onSave()} className="px-4 py-1.5">
                {editor.saving ? 'Saving…' : 'Save'}
              </ButtonPrimary>
              {!editor.isNew && !isProtectedTemplate ? (
                <button
                  type="button"
                  disabled={editor.saving}
                  onClick={moveToTrash}
                  className="px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-950/30"
                >
                  Move to trash
                </button>
              ) : null}
            </div>
          </div>
        </div>

        <GatedFeatureWorkspace
          mode={mode}
          featureId="certificates_advanced"
          config={config}
          featureTitle="Certificate Builder"
          featureDescription="Design printable, verifiable certificates with templates, QR codes, and verification links."
          previewVariant="generic"
          addonEnableTitle="Certificate Builder is not enabled"
          addonEnableDescription="Turn on the Advanced certificates add-on to use the certificate builder."
          canEnable={Boolean(addon.licenseOk)}
          enableBusy={addon.loading}
          onEnable={() => void addon.enable()}
          addonError={addon.error}
        >
          <div className="flex min-h-0 flex-1 overflow-hidden px-0 py-0">
            <CertificateVisualBuilder
              layout={layout}
              onLayoutChange={setLayout}
              orientation={orientation}
              onOrientationChange={setOrientation}
              pageSize={pageSize}
              onPageSizeChange={setPageSize}
              pageFinish={pageFinish}
              onPageFinishChange={setPageFinish}
              featuredPreview={featuredPreview}
              onFeaturedPreviewChange={setFeaturedPreview}
              onFeaturedIdChange={(id) => setFeaturedId(id)}
              templatePreviewUrl={publicPreviewHref}
            />
          </div>
        </GatedFeatureWorkspace>
      </section>
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} canSave={Boolean(title.trim())} onSave={() => void onSave()} />
      ) : null}
    </EditorFormShell>
  );
}

export function DefaultContentEditor(props: ContentEditorProps) {
  const { postId, backHref, entityLabel, onSavedNewId, embedded } = props;
  const rest = props.postType.replace(/^\//, '');
  const editor = useWpContentPost(rest, postId);
  const moveToTrash = useMoveToTrash(editor, backHref, entityLabel);
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [status, setStatus] = useState('draft');
  const [featured, setFeatured] = useState(0);
  const [saveMsg, setSaveMsg] = useState<string | null>(null);
  const [editorTab, setEditorTab] = useState<'content' | 'settings'>('content');

  useEffect(() => {
    if (editor.isNew) {
      setTitle('');
      setContent('');
      setStatus('draft');
      setFeatured(0);
      return;
    }
    if (!editor.post) {
      return;
    }
    const p = editor.post;
    setTitle(titleFromPost(p));
    setContent(contentFromPost(p));
    setStatus(p.status || 'draft');
    setFeatured(typeof p.featured_media === 'number' ? p.featured_media : 0);
  }, [editor.post, editor.isNew]);

  const onSave = async () => {
    setSaveMsg(null);
    editor.setError(null);
    try {
      const res = await editor.save({
        title,
        content,
        status,
        featured_media: featured > 0 ? featured : 0,
      });
      if (editor.isNew && res && typeof res === 'object' && 'id' in res) {
        const id = (res as { id: number }).id;
        if (typeof id === 'number' && id > 0) {
          onSavedNewId?.(id);
          return;
        }
      }
      setSaveMsg('Saved.');
      await editor.load();
    } catch {
      /* hook */
    }
  };

  return (
    <EditorFormShell
      loading={editor.loading}
      saving={editor.saving}
      error={editor.error}
      onRetry={() => void editor.load()}
      saveMsg={saveMsg}
    >
      <div className="space-y-3">
        <HorizontalEditorTabs
          ariaLabel={`${entityLabel} editor sections`}
          tabs={[
            { id: 'content', label: 'Content' },
            { id: 'settings', label: 'Settings' },
          ]}
          value={editorTab}
          onChange={(id) => setEditorTab(id as 'content' | 'settings')}
        />

        <section className={EDITOR_SURFACE} aria-label={`${entityLabel} editor`}>
        <div className="border-b border-slate-100 px-6 pb-0 pt-6 dark:border-slate-800">
          <h2 className="text-lg font-semibold text-slate-900 dark:text-white">{entityLabel}</h2>
          <p className={HINT}>Content: title, body, and optional featured image. Settings: publish status.</p>
        </div>
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-def-title">
                  Title
                </label>
                <input
                  id="sik-def-title"
                  className={FIELD}
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="Entry title"
                />
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-def-body">
                  Content
                </label>
                <textarea
                  id="sik-def-body"
                  rows={16}
                  className={`${FIELD} min-h-[300px] w-full font-mono text-[13px]`}
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                  placeholder="<p>HTML or plain text…</p>"
                />
              </div>
              <EditorFeaturedImageField
                fieldId="sik-def-featured"
                attachmentId={featured}
                onAttachmentIdChange={setFeatured}
              />
            </div>
          ) : (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-def-status">
                  Status
                </label>
                <p className={HINT}>Draft hides this item from public views until you publish.</p>
                <select id="sik-def-status" className={FIELD} value={status} onChange={(e) => setStatus(e.target.value)}>
                  <option value="draft">Draft</option>
                  <option value="publish">Published</option>
                  <option value="private">Private</option>
                </select>
              </div>
            </div>
          )}
        </div>
        </section>
      </div>
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} canSave={Boolean(title.trim())} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
          canSave={Boolean(title.trim())}
          onSave={() => void onSave()}
          onTrash={moveToTrash}
        />
      )}
    </EditorFormShell>
  );
}

export function renderContentEditor(postType: string, props: ContentEditorProps): React.ReactNode {
  switch (postType) {
    case 'sik_lesson':
      return <LessonEditor {...props} />;
    case 'sik_quiz':
      return <QuizEditor {...props} />;
    case 'sik_question':
      return <QuestionEditor {...props} />;
    case 'sik_assignment':
      return <AssignmentEditor {...props} />;
    case 'sik_chapter':
      return <ChapterEditor {...props} />;
    case 'sikshya_certificate':
      return <CertificateEditor {...props} />;
    default:
      return <DefaultContentEditor {...props} />;
  }
}
