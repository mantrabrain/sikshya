import { useCallback, useEffect, useState } from 'react';
import { getWpApi } from '../../api';
import { appViewHref } from '../../lib/appUrl';
import { ApiErrorPanel } from '../../components/shared/ApiErrorPanel';
import { useSikshyaDialog } from '../../components/shared/SikshyaDialogContext';
import { ButtonPrimary } from '../../components/shared/buttons';
import { HorizontalEditorTabs } from '../../components/shared/HorizontalEditorTabs';
import type { SikshyaReactConfig } from '../../types';
import { CertificateVisualBuilder } from './CertificateVisualBuilder';
import {
  defaultCertificateLayout,
  layoutToHtml,
  layoutToStorage,
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

const FIELD =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const LABEL = 'block text-sm font-medium text-slate-800 dark:text-slate-200';
const HINT = 'mt-1 text-xs text-slate-500 dark:text-slate-400';

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
  return (
    <>
      {saveMsg ? (
        <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">
          {saveMsg}
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
        window.location.href = backHref;
      } catch {
        /* handled by editor error state */
      }
    })();
  }, [editor, backHref, entityLabel, confirm]);
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
  }, [editor.post, editor.isNew]);

  const onSave = async () => {
    setSaveMsg(null);
    editor.setError(null);
    const body: Record<string, unknown> = {
      title,
      content,
      status,
      excerpt,
      featured_media: featured > 0 ? featured : 0,
      meta: {
        _sikshya_lesson_duration: duration.trim(),
        _sikshya_lesson_type: lessonType.trim() || 'text',
        _sikshya_lesson_video_url: lessonType === 'video' ? videoUrl.trim() : '',
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
      <section className={EDITOR_SURFACE} aria-label="Lesson editor">
        <div className="border-b border-slate-100 px-6 pb-4 pt-6 dark:border-slate-800">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-white">{title?.trim() ? title : 'Lesson'}</h2>
          <p className={HINT}>Lessons are the main teaching units students complete in order.</p>
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
                {lessonType === 'video' ? 'Video' : 'Text'}
              </span>
            </div>
          </div>
        </div>
        <HorizontalEditorTabs
          ariaLabel="Lesson editor sections"
          tabs={[
            { id: 'content', label: 'Content', icon: 'plusDocument' },
            { id: 'settings', label: 'Settings', icon: 'cog' },
          ]}
          value={editorTab}
          onChange={(id) => setEditorTab(id as 'content' | 'settings')}
        />
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-lesson-title">
                  Lesson title
                </label>
                <input id="sik-lesson-title" className={FIELD} value={title} onChange={(e) => setTitle(e.target.value)} />
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-lesson-excerpt">
                  Short summary
                </label>
                <p className={HINT}>Shown in lesson lists and previews. Optional but recommended.</p>
                <textarea
                  id="sik-lesson-excerpt"
                  rows={3}
                  className={`${FIELD} min-h-[72px] resize-y`}
                  value={excerpt}
                  onChange={(e) => setExcerpt(e.target.value)}
                />
              </div>
              {lessonType === 'video' ? (
                <div className="rounded-xl border border-slate-200 bg-slate-50/40 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                  <label className={LABEL} htmlFor="sik-lesson-video-url-inline">
                    Video URL
                  </label>
                  <p className={HINT}>Paste a YouTube/Vimeo/MP4 URL. This is the primary content of a video lesson.</p>
                  <input
                    id="sik-lesson-video-url-inline"
                    className={FIELD}
                    value={videoUrl}
                    onChange={(e) => setVideoUrl(e.target.value)}
                    placeholder="https://…"
                  />
                </div>
              ) : null}
              <div>
                <label className={LABEL} htmlFor="sik-lesson-body">
                  {lessonType === 'video' ? 'Transcript / notes' : 'Lesson content'}
                </label>
                <p className={HINT}>
                  {lessonType === 'video'
                    ? 'Optional but recommended for accessibility and SEO.'
                    : 'Main instructional content (HTML supported).'}
                </p>
                <textarea
                  id="sik-lesson-body"
                  rows={16}
                  className={`${FIELD} min-h-[320px] w-full font-mono text-[13px] leading-relaxed`}
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                />
              </div>
            </div>
          ) : (
            <div className="space-y-6" role="tabpanel">
              <div className="grid gap-6 lg:grid-cols-2">
                <div>
                  <label className={LABEL} htmlFor="sik-lesson-duration">
                    Duration
                  </label>
                  <p className={HINT}>Shown to learners (examples: “12 min”, “1h 20m”).</p>
                  <input
                    id="sik-lesson-duration"
                    className={FIELD}
                    value={duration}
                    onChange={(e) => setDuration(e.target.value)}
                    placeholder="e.g. 12 min"
                  />
                </div>
                <div>
                  <label className={LABEL} htmlFor="sik-lesson-type">
                    Lesson type
                  </label>
                  <p className={HINT}>Controls optional settings like video URL.</p>
                  <select
                    id="sik-lesson-type"
                    className={FIELD}
                    value={lessonType}
                    onChange={(e) => setLessonType(e.target.value)}
                  >
                    <option value="text">Text</option>
                    <option value="video">Video</option>
                  </select>
                </div>
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
                  <label className={LABEL} htmlFor="sik-lesson-thumb">
                    Featured image (attachment ID)
                  </label>
                  <p className={HINT}>Use a media library attachment ID for the lesson thumbnail.</p>
                  <input
                    id="sik-lesson-thumb"
                    type="number"
                    min={0}
                    className={FIELD}
                    value={featured || ''}
                    onChange={(e) => setFeatured(e.target.value === '' ? 0 : Number(e.target.value))}
                  />
                </div>
              </div>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                Tip: switch the lesson type to change the editing experience.
              </p>
            </div>
          )}
        </div>
      </section>
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
          onSave={() => void onSave()}
          onTrash={moveToTrash}
        />
      )}
    </EditorFormShell>
  );
}

/** Course builder side panel: save CPT only; no trash / no full-width action bar. */
function EmbeddedSaveBar(props: { saving: boolean; entityLabel: string; onSave: () => void }) {
  const { saving, entityLabel, onSave } = props;
  return (
    <div id="sikshya-embedded-save" className="mt-6 border-t border-slate-100/90 pt-4 dark:border-slate-800/90">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
          Saves this {entityLabel.toLowerCase()} only. Use the course toolbar for draft / publish.
        </p>
        <button
          type="button"
          disabled={saving}
          onClick={onSave}
          className="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 transition hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
        >
          {saving ? 'Saving…' : `Save ${entityLabel.toLowerCase()}`}
        </button>
      </div>
    </div>
  );
}

function EditorActions(props: {
  backHref: string;
  entityLabel: string;
  saving: boolean;
  isNew: boolean;
  onSave: () => void;
  onTrash: () => void;
}) {
  const { backHref, saving, isNew, onSave, onTrash } = props;
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-6 dark:border-slate-800">
      <a
        href={backHref}
        className="rounded-lg border border-slate-200/90 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
      >
        ← Back to list
      </a>
      <div className="flex flex-wrap gap-2">
        <ButtonPrimary type="button" disabled={saving} onClick={onSave} className="rounded-xl px-5 py-2.5">
          {saving ? 'Saving…' : 'Save'}
        </ButtonPrimary>
        {!isNew ? (
          <button
            type="button"
            disabled={saving}
            onClick={onTrash}
            className="rounded-xl px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30"
          >
            Move to trash
          </button>
        ) : null}
      </div>
    </div>
  );
}

export function QuizEditor(props: ContentEditorProps) {
  const { postId, backHref, entityLabel, onSavedNewId, embedded, config } = props;
  const editor = useWpContentPost('sik_quiz', postId);
  const moveToTrash = useMoveToTrash(editor, backHref, entityLabel);
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
  const [saveMsg, setSaveMsg] = useState<string | null>(null);
  const [editorTab, setEditorTab] = useState<'content' | 'settings' | 'questions'>('content');

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
      return;
    }
    if (!editor.post) {
      return;
    }
    const p = editor.post;
    setTitle(titleFromPost(p));
    setContent(contentFromPost(p));
    setStatus(p.status || 'draft');
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
  }, [editorTab, questionSearch]);

  const onSave = async () => {
    setSaveMsg(null);
    editor.setError(null);
    const body: Record<string, unknown> = {
      title,
      content,
      status,
      meta: {
        _sikshya_quiz_time_limit: Math.max(0, timeLimit),
        _sikshya_quiz_passing_score: Math.min(100, Math.max(0, passing)),
        _sikshya_quiz_attempts_allowed: Math.max(1, attempts),
        _sikshya_quiz_questions: quizQuestionIds,
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
      <section className={EDITOR_SURFACE} aria-label="Quiz editor">
        <div className="border-b border-slate-100 px-6 pb-4 pt-6 dark:border-slate-800">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-white">{title?.trim() ? title : 'Quiz'}</h2>
          {embedded ? (
            <p className={`${HINT} mt-1`}>
              Use <span className="font-medium text-slate-700 dark:text-slate-200">Content</span> for the name and
              student-facing text; use <span className="font-medium text-slate-700 dark:text-slate-200">Settings</span> for
              timing and scoring. Questions live in the{' '}
              <a
                href={appViewHref(config, 'questions')}
                className="font-medium text-brand-600 underline decoration-brand-600/30 underline-offset-2 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
              >
                Questions
              </a>{' '}
              library.
            </p>
          ) : (
            <p className={HINT}>
              Content tab: title and instructions. Settings: time limit, passing score, attempts, and visibility.
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
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-quiz-title">
                  Quiz name
                </label>
                <input id="sik-quiz-title" className={FIELD} value={title} onChange={(e) => setTitle(e.target.value)} />
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
            </div>
          ) : editorTab === 'settings' ? (
            <div className="space-y-6" role="tabpanel">
              <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                  <label className={LABEL} htmlFor="sik-quiz-time">
                    Time limit (minutes)
                  </label>
                  <p className={HINT}>0 = no limit</p>
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
                  <a
                    href={appViewHref(config, 'questions')}
                    className="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                  >
                    Manage Questions
                  </a>
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
                                  className={`shrink-0 rounded-md px-2.5 py-1.5 text-xs font-semibold ${
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
                                <button
                                  type="button"
                                  className="shrink-0 rounded-md px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30"
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
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
          onSave={() => void onSave()}
          onTrash={moveToTrash}
        />
      )}
    </EditorFormShell>
  );
}

export function QuestionEditor(props: ContentEditorProps) {
  const { postId, backHref, entityLabel, onSavedNewId, embedded } = props;
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
  const [saveMsg, setSaveMsg] = useState<string | null>(null);
  const [editorTab, setEditorTab] = useState<'content' | 'settings'>('content');

  useEffect(() => {
    if (editor.isNew) {
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
      return;
    }
    if (!editor.post) {
      return;
    }
    const p = editor.post;
    setTitle(titleFromPost(p));
    setContent(contentFromPost(p));
    setStatus(p.status || 'draft');
    const m = p.meta as Record<string, unknown> | undefined;
    const t = String(readMeta(m, '_sikshya_question_type') ?? '');
    setQType(t);
    setPoints(Number(readMeta(m, '_sikshya_question_points') ?? 1));
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
      meta: {
        _sikshya_question_type: qType,
        _sikshya_question_points: Math.max(0, points),
        _sikshya_question_options: optionsPayload,
        _sikshya_question_correct_answer: correctPayload,
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

  return (
    <EditorFormShell
      loading={editor.loading}
      saving={editor.saving}
      error={editor.error}
      onRetry={() => void editor.load()}
      saveMsg={saveMsg}
    >
      <section className={EDITOR_SURFACE} aria-label="Question editor">
        <div className="border-b border-slate-100 px-6 pb-4 pt-6 dark:border-slate-800">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-white">
                {title?.trim() ? title : 'Question'}
              </h2>
              <p className={HINT}>
                Content: type, stem, and answers. Settings: points and publish status.
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
        <HorizontalEditorTabs
          ariaLabel="Question editor sections"
          tabs={[
            { id: 'content', label: 'Content', icon: 'plusDocument' },
            { id: 'settings', label: 'Settings', icon: 'cog' },
          ]}
          value={editorTab}
          onChange={(id) => setEditorTab(id as 'content' | 'settings')}
        />
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-q-type-content">
                  Question type
                </label>
                <p className={HINT}>Controls which answer fields appear below.</p>
                <select
                  id="sik-q-type-content"
                  className={FIELD}
                  value={qType}
                  onChange={(e) => onTypeChange(e.target.value)}
                >
                  <option value="">Select type…</option>
                  <option value="multiple_choice">Multiple choice (one correct)</option>
                  <option value="multiple_response">Multiple response (several correct)</option>
                  <option value="true_false">True / false</option>
                  <option value="short_answer">Short answer</option>
                  <option value="fill_blank">Fill in the blank</option>
                  <option value="ordering">Ordering / sequencing</option>
                  <option value="matching">Matching</option>
                  <option value="essay">Essay (manual grading)</option>
                </select>
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
                <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                  Pick a <span className="font-semibold">question type</span> above to configure answers.
                </p>
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
            </div>
          )}
        </div>
      </section>
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
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
  const [saveMsg, setSaveMsg] = useState<string | null>(null);
  const [editorTab, setEditorTab] = useState<'content' | 'settings'>('content');

  useEffect(() => {
    if (editor.isNew) {
      setTitle('');
      setContent('');
      setStatus('draft');
      setDue('');
      setApoints(10);
      setAtype('');
      return;
    }
    if (!editor.post) {
      return;
    }
    const p = editor.post;
    setTitle(titleFromPost(p));
    setContent(contentFromPost(p));
    setStatus(p.status || 'draft');
    const m = p.meta as Record<string, unknown> | undefined;
    setDue(String(readMeta(m, '_sikshya_assignment_due_date') ?? ''));
    setApoints(Number(readMeta(m, '_sikshya_assignment_points') ?? 10));
    setAtype(String(readMeta(m, '_sikshya_assignment_type') ?? ''));
  }, [editor.post, editor.isNew]);

  const onSave = async () => {
    setSaveMsg(null);
    editor.setError(null);
    const body: Record<string, unknown> = {
      title,
      content,
      status,
      meta: {
        _sikshya_assignment_due_date: due,
        _sikshya_assignment_points: Math.max(0, apoints),
        _sikshya_assignment_type: atype,
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
      <section className={EDITOR_SURFACE} aria-label="Assignment editor">
        <div className="border-b border-slate-100 px-6 pb-4 pt-6 dark:border-slate-800">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-white">
                {title?.trim() ? title : 'Assignment'}
              </h2>
              <p className={HINT}>Content: title and learner instructions. Settings: due date, points, submission type, status.</p>
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
        <HorizontalEditorTabs
          ariaLabel="Assignment editor sections"
          tabs={[
            { id: 'content', label: 'Content', icon: 'plusDocument' },
            { id: 'settings', label: 'Settings', icon: 'cog' },
          ]}
          value={editorTab}
          onChange={(id) => setEditorTab(id as 'content' | 'settings')}
        />
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-as-title">
                  Title
                </label>
                <input id="sik-as-title" className={FIELD} value={title} onChange={(e) => setTitle(e.target.value)} />
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-as-body">
                  Instructions & rubric
                </label>
                <textarea
                  id="sik-as-body"
                  rows={12}
                  className={`${FIELD} min-h-[240px] w-full`}
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                />
              </div>
            </div>
          ) : (
            <div className="space-y-6" role="tabpanel">
              <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                  <label className={LABEL} htmlFor="sik-as-due">
                    Due (datetime)
                  </label>
                  <input
                    id="sik-as-due"
                    type="datetime-local"
                    className={FIELD}
                    value={due}
                    onChange={(e) => setDue(e.target.value)}
                  />
                </div>
                <div>
                  <label className={LABEL} htmlFor="sik-as-points">
                    Points
                  </label>
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
                    <option value="">Select…</option>
                    <option value="essay">Essay</option>
                    <option value="file_upload">File upload</option>
                    <option value="url_submission">URL</option>
                  </select>
                </div>
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-as-status">
                  Status
                </label>
                <select id="sik-as-status" className={FIELD} value={status} onChange={(e) => setStatus(e.target.value)}>
                  <option value="draft">Draft</option>
                  <option value="publish">Published</option>
                </select>
              </div>
            </div>
          )}
        </div>
      </section>
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
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
      <section className={EDITOR_SURFACE} aria-label="Chapter editor">
        <div className="border-b border-slate-100 px-6 pb-4 pt-6 dark:border-slate-800">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h2 className="truncate text-lg font-semibold text-slate-900 dark:text-white">
                {title?.trim() ? title : 'Chapter'}
              </h2>
              <p className={HINT}>
                Content: title and intro copy. Settings: parent course, display order, and status.
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
        <HorizontalEditorTabs
          ariaLabel="Chapter editor sections"
          tabs={[
            { id: 'content', label: 'Content', icon: 'plusDocument' },
            { id: 'settings', label: 'Settings', icon: 'cog' },
          ]}
          value={editorTab}
          onChange={(id) => setEditorTab(id as 'content' | 'settings')}
        />
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-ch-title">
                  Chapter title
                </label>
                <input id="sik-ch-title" className={FIELD} value={title} onChange={(e) => setTitle(e.target.value)} />
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
                />
              </div>
            </div>
          ) : (
            <div className="space-y-6" role="tabpanel">
              <div className="grid gap-6 sm:grid-cols-2">
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
                      <option value={0}>Select course…</option>
                      {courses.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.title}
                        </option>
                      ))}
                    </select>
                  )}
                </div>
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
              </div>
              <div>
                <label className={LABEL} htmlFor="sik-ch-status">
                  Status
                </label>
                <select id="sik-ch-status" className={FIELD} value={status} onChange={(e) => setStatus(e.target.value)}>
                  <option value="draft">Draft</option>
                  <option value="publish">Published</option>
                </select>
              </div>
            </div>
          )}
        </div>
      </section>
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
          onSave={() => void onSave()}
          onTrash={moveToTrash}
        />
      )}
    </EditorFormShell>
  );
}

/** Visual certificate builder (drag-and-drop canvas) + generated HTML in post content. */
export function CertificateEditor(props: ContentEditorProps) {
  const { postId, backHref, entityLabel, onSavedNewId, embedded } = props;
  const editor = useWpContentPost('sikshya_certificate', postId);
  const moveToTrash = useMoveToTrash(editor, backHref, entityLabel);
  const [title, setTitle] = useState('');
  const [status, setStatus] = useState('draft');
  const [featuredId, setFeaturedId] = useState(0);
  const [featuredPreview, setFeaturedPreview] = useState('');
  const [orientation, setOrientation] = useState<'landscape' | 'portrait'>('landscape');
  const [accentColor, setAccentColor] = useState('');
  const [saveMsg, setSaveMsg] = useState<string | null>(null);
  const [layout, setLayout] = useState<CertLayoutFile>(() => defaultCertificateLayout());

  useEffect(() => {
    if (editor.isNew) {
      setTitle('');
      setStatus('draft');
      setFeaturedId(0);
      setFeaturedPreview('');
      setOrientation('landscape');
      setAccentColor('');
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
    setAccentColor(String(readMeta(m, '_sikshya_certificate_accent_color') || ''));
    const rawLayout = readMeta(m, '_sikshya_certificate_layout');
    setLayout(parseLayoutFromMeta(rawLayout));
  }, [editor.post, editor.isNew]);

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

  const onSave = async () => {
    setSaveMsg(null);
    editor.setError(null);
    const generated = layoutToHtml(layout);
    const body: Record<string, unknown> = {
      title,
      content: generated,
      status,
      featured_media: featuredId > 0 ? featuredId : 0,
      meta: {
        _sikshya_certificate_orientation: orientation,
        _sikshya_certificate_accent_color: accentColor.trim(),
        _sikshya_certificate_layout: layoutToStorage(layout),
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
      <section className={EDITOR_SURFACE} aria-label="Certificate editor">
        <div className="border-b border-slate-100 px-6 pb-4 pt-6 dark:border-slate-800">
          <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Certificate builder</h2>
          <p className={HINT}>
            Use the left panel: Templates, Elements, Media library, and Backgrounds. Drag blocks onto the canvas; click a
            block for its settings. Saving stores layout JSON in meta and exports HTML to the post body for issuance.
          </p>
          <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <label className={LABEL} htmlFor="sik-cert-doc-title">
                Template title
              </label>
              <input
                id="sik-cert-doc-title"
                className={FIELD}
                value={title}
                onChange={(e) => setTitle(e.target.value)}
              />
            </div>
            <div>
              <label className={LABEL} htmlFor="sik-cert-doc-status">
                Status
              </label>
              <select
                id="sik-cert-doc-status"
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
          </div>
        </div>
        <div className="p-4 sm:p-6">
          <CertificateVisualBuilder
            layout={layout}
            onLayoutChange={setLayout}
            orientation={orientation}
            onOrientationChange={setOrientation}
            accentColor={accentColor}
            onAccentColorChange={setAccentColor}
            featuredPreview={featuredPreview}
            onFeaturedPreviewChange={setFeaturedPreview}
            onFeaturedIdChange={(id) => setFeaturedId(id)}
          />
        </div>
      </section>
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
          onSave={() => void onSave()}
          onTrash={moveToTrash}
        />
      )}
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
      <section className={EDITOR_SURFACE} aria-label={`${entityLabel} editor`}>
        <div className="border-b border-slate-100 px-6 pb-0 pt-6 dark:border-slate-800">
          <h2 className="text-lg font-semibold text-slate-900 dark:text-white">{entityLabel}</h2>
          <p className={HINT}>Content: title and body. Settings: status and featured image.</p>
        </div>
        <HorizontalEditorTabs
          ariaLabel={`${entityLabel} editor sections`}
          tabs={[
            { id: 'content', label: 'Content' },
            { id: 'settings', label: 'Settings' },
          ]}
          value={editorTab}
          onChange={(id) => setEditorTab(id as 'content' | 'settings')}
        />
        <div className="p-6">
          {editorTab === 'content' ? (
            <div className="space-y-6" role="tabpanel">
              <div>
                <label className={LABEL} htmlFor="sik-def-title">
                  Title
                </label>
                <input id="sik-def-title" className={FIELD} value={title} onChange={(e) => setTitle(e.target.value)} />
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
                />
              </div>
            </div>
          ) : (
            <div className="space-y-6" role="tabpanel">
              <div className="grid gap-6 sm:grid-cols-2">
                <div>
                  <label className={LABEL} htmlFor="sik-def-status">
                    Status
                  </label>
                  <select id="sik-def-status" className={FIELD} value={status} onChange={(e) => setStatus(e.target.value)}>
                    <option value="draft">Draft</option>
                    <option value="publish">Published</option>
                    <option value="private">Private</option>
                  </select>
                </div>
                <div>
                  <label className={LABEL} htmlFor="sik-def-thumb">
                    Featured image (attachment ID)
                  </label>
                  <input
                    id="sik-def-thumb"
                    type="number"
                    min={0}
                    className={FIELD}
                    value={featured || ''}
                    onChange={(e) => setFeatured(e.target.value === '' ? 0 : Number(e.target.value))}
                  />
                </div>
              </div>
            </div>
          )}
        </div>
      </section>
      {embedded ? (
        <EmbeddedSaveBar saving={editor.saving} entityLabel={entityLabel} onSave={() => void onSave()} />
      ) : (
        <EditorActions
          backHref={backHref}
          entityLabel={entityLabel}
          saving={editor.saving}
          isNew={editor.isNew}
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
