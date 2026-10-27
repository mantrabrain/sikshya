import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { getErrorSummary } from '../api/errors';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { HorizontalEditorTabs } from '../components/shared/HorizontalEditorTabs';
import { SingleCoursePicker } from '../components/shared/SingleCoursePicker';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { useAddonEnabled } from '../hooks/useAddons';
import { useAsyncData } from '../hooks/useAsyncData';
import { appViewHref } from '../lib/appUrl';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { AddonSettingsPage } from './AddonSettingsPage';
import type { SikshyaReactConfig } from '../types';
import { __ } from '../lib/i18n';

type AiUsage = { period: string; used: number; cap: number; remaining: number | null };

type AiStatus = {
  ok?: boolean;
  configured: boolean;
  provider: string;
  model: string;
  language: string;
  question_types: string[];
  usage: AiUsage;
};

type DraftLesson = { title: string; summary: string; duration_minutes: number };
type DraftChapter = { title: string; summary: string; lessons: DraftLesson[] };
type OutlineDraft = { title: string; summary: string; chapters: DraftChapter[] };

type DraftQuestion = {
  type: string;
  text: string;
  options: string[];
  correct: string;
  explanation: string;
  points: number;
};

type TreeContent = { id: number; title: string; type: string };
type TreeChapter = { id: number; title: string; contents: TreeContent[] };

const FIELD =
  'mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const LABEL = 'block text-sm font-medium text-slate-700 dark:text-slate-300';
const CARD =
  'rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/40';

const QUESTION_TYPE_LABELS: Record<string, string> = {
  multiple_choice: __('Multiple choice', 'sikshya'),
  true_false: __('True / false', 'sikshya'),
  short_answer: __('Short answer', 'sikshya'),
};

function usageLine(usage: AiUsage | undefined): string {
  if (!usage) {
    return '';
  }
  if (!usage.cap) {
    return `${usage.used} ${__('requests this month (no limit set)', 'sikshya')}`;
  }
  return `${usage.used} / ${usage.cap} ${__('requests used this month', 'sikshya')}`;
}

/**
 * AI studio — draft an outline, a lesson body, or quiz questions.
 *
 * The screen is built around one rule: **the model never writes to a course.**
 * Generating returns a draft into this page, where it can be edited or thrown
 * away; a separate button applies it. That is why every tab has two distinct
 * steps rather than one "generate into my course" action, and why applying an
 * outline walks chapter by chapter with visible progress instead of posting
 * the whole thing and hoping.
 */
export function AiStudioPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const featureOk = isFeatureEnabled(config, 'ai_authoring');
  const addon = useAddonEnabled('ai_authoring');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [tab, setTab] = useState<'outline' | 'lesson' | 'quiz' | 'settings'>('outline');

  const statusLoader = useCallback(async () => {
    if (!enabled) {
      return null;
    }
    return getSikshyaApi().get<AiStatus>(SIKSHYA_ENDPOINTS.ai.status);
  }, [enabled]);
  const statusState = useAsyncData(statusLoader, [enabled]);
  const status = statusState.data ?? null;

  const inner = (
    <GatedFeatureWorkspace
      mode={mode}
      featureId="ai_authoring"
      config={config}
      featureTitle={__('AI course authoring', 'sikshya')}
      featureDescription={__('Draft an outline, write a lesson, or write quiz questions with your own AI provider key — and read the draft before anything is created.', 'sikshya')}
      previewVariant="form"
      addonEnableTitle={__('AI authoring is not enabled', 'sikshya')}
      addonEnableDescription={__('Enable the add-on, then add your own provider API key. Nothing is sent anywhere until you do.', 'sikshya')}
      canEnable={Boolean(addon.licenseOk)}
      enableBusy={addon.loading}
      onEnable={() => addon.enable()}
    >
      {enabled ? (
        <div className="space-y-6" data-sikshya-ai-studio>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <HorizontalEditorTabs
              ariaLabel={__('AI studio sections', 'sikshya')}
              tabs={[
                { id: 'outline', label: __('Course outline', 'sikshya'), icon: 'curriculumOutline' },
                { id: 'lesson', label: __('Lesson body', 'sikshya'), icon: 'bookOpen' },
                { id: 'quiz', label: __('Quiz questions', 'sikshya'), icon: 'clipboardList' },
                { id: 'settings', label: __('Settings', 'sikshya'), icon: 'cog' },
              ]}
              value={tab}
              onChange={(id) => setTab(id as 'outline' | 'lesson' | 'quiz' | 'settings')}
            />
            {status ? (
              <p
                className="text-xs text-slate-500 dark:text-slate-400"
                data-sikshya-ai-usage
              >
                {status.provider} · {status.model} · {usageLine(status.usage)}
              </p>
            ) : null}
          </div>

          {status && !status.configured && tab !== 'settings' ? (
            <div
              className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
              data-sikshya-ai-unconfigured
            >
              <p className="font-medium">{__('No API key yet', 'sikshya')}</p>
              <p className="mt-1">
                {__('Add a key from Anthropic or OpenAI in Settings. Requests go from this server straight to the provider.', 'sikshya')}
              </p>
              <ButtonSecondary type="button" className="mt-3" onClick={() => setTab('settings')}>
                {__('Open settings', 'sikshya')}
              </ButtonSecondary>
            </div>
          ) : null}

          {tab === 'outline' ? (
            <OutlineTab config={config} onUsed={() => statusState.refetch()} />
          ) : null}
          {tab === 'lesson' ? <LessonTab onUsed={() => statusState.refetch()} /> : null}
          {tab === 'quiz' ? (
            <QuizTab types={status?.question_types ?? []} onUsed={() => statusState.refetch()} />
          ) : null}
          {tab === 'settings' ? (
            <AddonSettingsPage
              embedded
              config={config}
              title={__('AI authoring settings', 'sikshya')}
              addonId="ai_authoring"
              subtitle={__('Your provider, your key, your spending limit.', 'sikshya')}
              featureTitle={__('AI authoring settings', 'sikshya')}
              featureDescription={__('The key is stored on this site and used only for the requests you start from this screen. The monthly limit is a spend guard — it is checked before each request, so a refused request costs nothing.', 'sikshya')}
              nextSteps={[
                {
                  label: __('Open a course', 'sikshya'),
                  href: appViewHref(config, 'courses'),
                  description: __('Apply a generated outline into any course from the Course outline tab.', 'sikshya'),
                },
              ]}
            />
          ) : null}
        </div>
      ) : null}
    </GatedFeatureWorkspace>
  );

  if (embedded) {
    return (
      <EmbeddableShell embedded config={config} title={title}>
        {inner}
      </EmbeddableShell>
    );
  }

  return inner;
}

/* ------------------------------------------------------------------ outline */

function OutlineTab(props: { config: SikshyaReactConfig; onUsed: () => void }) {
  const { config, onUsed } = props;

  const [topic, setTopic] = useState('');
  const [audience, setAudience] = useState('');
  const [chapters, setChapters] = useState(6);
  const [lessonsPer, setLessonsPer] = useState(4);
  const [notes, setNotes] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [draft, setDraft] = useState<OutlineDraft | null>(null);

  const [courseId, setCourseId] = useState(0);
  const [applying, setApplying] = useState<{ done: number; total: number } | null>(null);
  const [applied, setApplied] = useState('');

  const generate = async () => {
    setBusy(true);
    setError('');
    setApplied('');
    try {
      const res = await getSikshyaApi().post<OutlineDraft>(SIKSHYA_ENDPOINTS.ai.outline, {
        topic,
        audience,
        chapters,
        lessons_per_chapter: lessonsPer,
        notes,
      });
      setDraft({
        title: res.title || topic,
        summary: res.summary || '',
        chapters: Array.isArray(res.chapters) ? res.chapters : [],
      });
    } catch (e) {
      setError(getErrorSummary(e));
      setDraft(null);
    } finally {
      setBusy(false);
      onUsed();
    }
  };

  const updateChapter = (index: number, patch: Partial<DraftChapter>) => {
    setDraft((prev) => {
      if (!prev) return prev;
      const next = prev.chapters.slice();
      next[index] = { ...next[index], ...patch };
      return { ...prev, chapters: next };
    });
  };

  const updateLesson = (chapterIndex: number, lessonIndex: number, patch: Partial<DraftLesson>) => {
    setDraft((prev) => {
      if (!prev) return prev;
      const nextChapters = prev.chapters.slice();
      const lessons = nextChapters[chapterIndex].lessons.slice();
      lessons[lessonIndex] = { ...lessons[lessonIndex], ...patch };
      nextChapters[chapterIndex] = { ...nextChapters[chapterIndex], lessons };
      return { ...prev, chapters: nextChapters };
    });
  };

  const removeLesson = (chapterIndex: number, lessonIndex: number) => {
    setDraft((prev) => {
      if (!prev) return prev;
      const nextChapters = prev.chapters.slice();
      nextChapters[chapterIndex] = {
        ...nextChapters[chapterIndex],
        lessons: nextChapters[chapterIndex].lessons.filter((_, i) => i !== lessonIndex),
      };
      return { ...prev, chapters: nextChapters };
    });
  };

  const removeChapter = (index: number) => {
    setDraft((prev) =>
      prev ? { ...prev, chapters: prev.chapters.filter((_, i) => i !== index) } : prev
    );
  };

  /**
   * One request per chapter, and each applied chapter leaves the draft as it
   * lands. If the fourth of ten fails, three are really in the course and the
   * remaining seven are still on screen — pressing the button again continues
   * rather than duplicating what already worked.
   */
  const apply = async () => {
    if (!draft || courseId <= 0) {
      return;
    }
    const total = draft.chapters.length;
    setApplying({ done: 0, total });
    setError('');
    setApplied('');

    let done = 0;
    try {
      while (true) {
        const remaining = draft.chapters.slice(done);
        const chapter = remaining[0];
        if (!chapter) break;

        await getSikshyaApi().post(SIKSHYA_ENDPOINTS.ai.applyChapter, {
          course_id: courseId,
          chapter,
          order: done + 1,
        });
        done += 1;
        setApplying({ done, total });
      }
      setDraft(null);
      setApplied(
        `${done} ${__('chapters created. Open the course builder to arrange and write them.', 'sikshya')}`
      );
    } catch (e) {
      setError(getErrorSummary(e));
      setDraft((prev) => (prev ? { ...prev, chapters: prev.chapters.slice(done) } : prev));
      if (done > 0) {
        setApplied(
          `${done} ${__('of', 'sikshya')} ${total} ${__('chapters were created before this failed. The rest are still listed below.', 'sikshya')}`
        );
      }
    } finally {
      setApplying(null);
    }
  };

  const lessonCount = useMemo(
    () => (draft ? draft.chapters.reduce((n, c) => n + c.lessons.length, 0) : 0),
    [draft]
  );

  return (
    <div className="space-y-6">
      <section className={CARD}>
        <h2 className="text-base font-semibold text-slate-900 dark:text-white">
          {__('Describe the course', 'sikshya')}
        </h2>
        <div className="mt-4 grid gap-4 md:grid-cols-2">
          <div className="md:col-span-2">
            <label className={LABEL} htmlFor="sikshya-ai-topic">
              {__('What should it teach?', 'sikshya')}
            </label>
            <input
              id="sikshya-ai-topic"
              className={FIELD}
              value={topic}
              onChange={(e) => setTopic(e.target.value)}
              placeholder={__('e.g. Bookkeeping for small retail shops', 'sikshya')}
              data-sikshya-ai-topic
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="sikshya-ai-audience">
              {__('Who is it for?', 'sikshya')}
            </label>
            <input
              id="sikshya-ai-audience"
              className={FIELD}
              value={audience}
              onChange={(e) => setAudience(e.target.value)}
              placeholder={__('e.g. shop owners with no accounting background', 'sikshya')}
            />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className={LABEL} htmlFor="sikshya-ai-chapters">
                {__('Chapters', 'sikshya')}
              </label>
              <input
                id="sikshya-ai-chapters"
                type="number"
                min={1}
                max={20}
                className={FIELD}
                value={chapters}
                onChange={(e) => setChapters(Number(e.target.value) || 1)}
              />
            </div>
            <div>
              <label className={LABEL} htmlFor="sikshya-ai-lessons">
                {__('Lessons each', 'sikshya')}
              </label>
              <input
                id="sikshya-ai-lessons"
                type="number"
                min={1}
                max={10}
                className={FIELD}
                value={lessonsPer}
                onChange={(e) => setLessonsPer(Number(e.target.value) || 1)}
              />
            </div>
          </div>
          <div className="md:col-span-2">
            <label className={LABEL} htmlFor="sikshya-ai-notes">
              {__('Anything it must cover or avoid (optional)', 'sikshya')}
            </label>
            <textarea
              id="sikshya-ai-notes"
              className={FIELD}
              rows={3}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder={__('e.g. must follow Nepali VAT rules; no software recommendations', 'sikshya')}
            />
          </div>
        </div>
        <div className="mt-4 flex items-center gap-3">
          <span data-sikshya-ai-generate-outline>
            <ButtonPrimary
              type="button"
              disabled={busy || topic.trim() === ''}
              onClick={() => void generate()}
            >
              {busy ? __('Drafting…', 'sikshya') : __('Draft the outline', 'sikshya')}
            </ButtonPrimary>
          </span>
          <span className="text-xs text-slate-500 dark:text-slate-400">
            {__('Nothing is created yet — you will see the draft first.', 'sikshya')}
          </span>
        </div>
        {error ? (
          <p className="mt-3 text-sm text-rose-700 dark:text-rose-300" data-sikshya-ai-error>
            {error}
          </p>
        ) : null}
        {applied ? (
          <p className="mt-3 text-sm text-emerald-800 dark:text-emerald-200" data-sikshya-ai-applied>
            {applied}
          </p>
        ) : null}
      </section>

      {draft ? (
        <section className={CARD} data-sikshya-ai-outline-draft>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-slate-900 dark:text-white">{draft.title}</h2>
              {draft.summary ? (
                <p className="mt-1 max-w-3xl text-sm text-slate-600 dark:text-slate-300">{draft.summary}</p>
              ) : null}
              <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {draft.chapters.length} {__('chapters', 'sikshya')} · {lessonCount} {__('lessons', 'sikshya')}
              </p>
            </div>
            <ButtonSecondary type="button" onClick={() => setDraft(null)}>
              {__('Discard draft', 'sikshya')}
            </ButtonSecondary>
          </div>

          <div className="mt-5 space-y-5">
            {draft.chapters.map((chapter, ci) => (
              <div
                key={`chapter-${ci}`}
                className="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                data-sikshya-ai-draft-chapter
              >
                <div className="flex items-center gap-2">
                  <input
                    className={`${FIELD} mt-0 font-medium`}
                    value={chapter.title}
                    onChange={(e) => updateChapter(ci, { title: e.target.value })}
                    aria-label={__('Chapter title', 'sikshya')}
                  />
                  <ButtonSecondary type="button" onClick={() => removeChapter(ci)}>
                    {__('Remove', 'sikshya')}
                  </ButtonSecondary>
                </div>
                <ul className="mt-3 space-y-2">
                  {chapter.lessons.map((lesson, li) => (
                    <li key={`lesson-${ci}-${li}`} className="flex items-center gap-2">
                      <input
                        className={`${FIELD} mt-0`}
                        value={lesson.title}
                        onChange={(e) => updateLesson(ci, li, { title: e.target.value })}
                        aria-label={__('Lesson title', 'sikshya')}
                        data-sikshya-ai-draft-lesson
                      />
                      <span className="w-20 shrink-0 text-right text-xs text-slate-500 dark:text-slate-400">
                        {lesson.duration_minutes > 0
                          ? `${lesson.duration_minutes} ${__('min', 'sikshya')}`
                          : ''}
                      </span>
                      <button
                        type="button"
                        className="shrink-0 text-xs text-slate-500 underline hover:text-rose-700 dark:text-slate-400"
                        onClick={() => removeLesson(ci, li)}
                      >
                        {__('Remove', 'sikshya')}
                      </button>
                    </li>
                  ))}
                  {chapter.lessons.length === 0 ? (
                    <li className="text-xs text-slate-500 dark:text-slate-400">
                      {__('No lessons — the chapter will be created empty.', 'sikshya')}
                    </li>
                  ) : null}
                </ul>
              </div>
            ))}
          </div>

          <div className="mt-6 border-t border-slate-200 pt-5 dark:border-slate-700">
            <div data-sikshya-ai-course-picker>
              <SingleCoursePicker
                label={__('Create this in', 'sikshya')}
                value={courseId}
                onChange={setCourseId}
                hint={__('Chapters are appended to whatever the course already has; nothing existing is touched.', 'sikshya')}
              />
            </div>
            <div className="mt-4 flex flex-wrap items-center gap-3">
              <span data-sikshya-ai-apply-outline>
                <ButtonPrimary
                  type="button"
                  disabled={courseId <= 0 || applying !== null || draft.chapters.length === 0}
                  onClick={() => void apply()}
                    >
                  {applying
                    ? `${__('Creating', 'sikshya')} ${applying.done}/${applying.total}…`
                    : __('Create in course', 'sikshya')}
                </ButtonPrimary>
              </span>
              {courseId > 0 ? (
                <a
                  className="text-sm text-slate-600 underline dark:text-slate-300"
                  href={appViewHref(config, 'courses', { course_id: String(courseId) })}
                >
                  {__('Open the course builder', 'sikshya')}
                </a>
              ) : null}
            </div>
          </div>
        </section>
      ) : null}
    </div>
  );
}

/* ------------------------------------------------------------------- lesson */

function useCurriculumTree(courseId: number) {
  const loader = useCallback(async () => {
    if (courseId <= 0) {
      return [] as TreeChapter[];
    }
    const res = await getSikshyaApi().get<{ success?: boolean; data?: { chapters?: TreeChapter[] } }>(
      SIKSHYA_ENDPOINTS.admin.courseCurriculumTree(courseId)
    );
    return res.data?.chapters ?? [];
  }, [courseId]);

  return useAsyncData(loader, [courseId]);
}

function LessonTab(props: { onUsed: () => void }) {
  const { onUsed } = props;

  const [courseId, setCourseId] = useState(0);
  const [lessonId, setLessonId] = useState(0);
  const [context, setContext] = useState('');
  const [words, setWords] = useState(700);
  const [html, setHtml] = useState('');
  const [busy, setBusy] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState('');

  const tree = useCurriculumTree(courseId);
  const lessons = useMemo(() => {
    const rows: { id: number; title: string; chapter: string }[] = [];
    for (const chapter of tree.data ?? []) {
      for (const item of chapter.contents || []) {
        if (item.type === 'lesson') {
          rows.push({ id: item.id, title: item.title, chapter: chapter.title });
        }
      }
    }
    return rows;
  }, [tree.data]);

  const selected = lessons.find((l) => l.id === lessonId);

  const generate = async () => {
    setBusy(true);
    setError('');
    setSaved('');
    try {
      const res = await getSikshyaApi().post<{ content_html?: string }>(SIKSHYA_ENDPOINTS.ai.lesson, {
        lesson_id: lessonId,
        course_title: '',
        context,
        words,
      });
      setHtml(res.content_html || '');
    } catch (e) {
      setError(getErrorSummary(e));
    } finally {
      setBusy(false);
      onUsed();
    }
  };

  const save = async () => {
    setSaving(true);
    setError('');
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.ai.applyLesson, {
        lesson_id: lessonId,
        content_html: html,
      });
      setSaved(__('Saved onto the lesson.', 'sikshya'));
    } catch (e) {
      setError(getErrorSummary(e));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6">
      <section className={CARD}>
        <h2 className="text-base font-semibold text-slate-900 dark:text-white">
          {__('Write one lesson', 'sikshya')}
        </h2>
        <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
          {__('Pick an existing lesson. The draft replaces its body only when you press save.', 'sikshya')}
        </p>

        <div className="mt-4 grid gap-4 md:grid-cols-2">
          <div data-sikshya-ai-course-picker>
            <SingleCoursePicker label={__('Course', 'sikshya')} value={courseId} onChange={(id) => { setCourseId(id); setLessonId(0); }} />
          </div>
          <div>
            <label className={LABEL} htmlFor="sikshya-ai-lesson-select">
              {__('Lesson', 'sikshya')}
            </label>
            <select
              id="sikshya-ai-lesson-select"
              className={FIELD}
              value={lessonId}
              disabled={courseId <= 0 || tree.loading}
              onChange={(e) => setLessonId(Number(e.target.value) || 0)}
              data-sikshya-ai-lesson-select
            >
              <option value={0}>
                {courseId <= 0
                  ? __('Choose a course first', 'sikshya')
                  : lessons.length === 0
                    ? __('This course has no lessons yet', 'sikshya')
                    : __('Choose a lesson…', 'sikshya')}
              </option>
              {lessons.map((lesson) => (
                <option key={lesson.id} value={lesson.id}>
                  {lesson.chapter} → {lesson.title}
                </option>
              ))}
            </select>
          </div>
          <div className="md:col-span-2">
            <label className={LABEL} htmlFor="sikshya-ai-context">
              {__('What should it say? (optional)', 'sikshya')}
            </label>
            <textarea
              id="sikshya-ai-context"
              className={FIELD}
              rows={4}
              value={context}
              onChange={(e) => setContext(e.target.value)}
              placeholder={__('Paste notes, a transcript, or the points this lesson has to make.', 'sikshya')}
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="sikshya-ai-words">
              {__('Roughly how long (words)', 'sikshya')}
            </label>
            <input
              id="sikshya-ai-words"
              type="number"
              min={200}
              max={2500}
              step={100}
              className={FIELD}
              value={words}
              onChange={(e) => setWords(Number(e.target.value) || 700)}
            />
          </div>
        </div>

        <div className="mt-4">
          <span data-sikshya-ai-generate-lesson>
            <ButtonPrimary
              type="button"
              disabled={busy || lessonId <= 0}
              onClick={() => void generate()}
            >
              {busy ? __('Writing…', 'sikshya') : __('Write the lesson', 'sikshya')}
            </ButtonPrimary>
          </span>
        </div>
        {error ? (
          <p className="mt-3 text-sm text-rose-700 dark:text-rose-300" data-sikshya-ai-error>
            {error}
          </p>
        ) : null}
        {saved ? (
          <p className="mt-3 text-sm text-emerald-800 dark:text-emerald-200" data-sikshya-ai-applied>
            {saved}
          </p>
        ) : null}
      </section>

      {html ? (
        <section className={CARD} data-sikshya-ai-lesson-draft>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-base font-semibold text-slate-900 dark:text-white">
              {selected ? selected.title : __('Draft', 'sikshya')}
            </h2>
            <ButtonSecondary type="button" onClick={() => setHtml('')}>
              {__('Discard draft', 'sikshya')}
            </ButtonSecondary>
          </div>

          <label className={`${LABEL} mt-4`} htmlFor="sikshya-ai-lesson-html">
            {__('Edit before saving', 'sikshya')}
          </label>
          <textarea
            id="sikshya-ai-lesson-html"
            className={`${FIELD} font-mono text-xs`}
            rows={16}
            value={html}
            onChange={(e) => setHtml(e.target.value)}
          />

          <div className="mt-4 flex items-center gap-3">
            <span data-sikshya-ai-apply-lesson>
              <ButtonPrimary
                type="button"
                disabled={saving || lessonId <= 0 || html.trim() === ''}
                onClick={() => void save()}
              >
                {saving ? __('Saving…', 'sikshya') : __('Save onto the lesson', 'sikshya')}
              </ButtonPrimary>
            </span>
            <span className="text-xs text-slate-500 dark:text-slate-400">
              {__('This replaces the lesson body.', 'sikshya')}
            </span>
          </div>
        </section>
      ) : null}
    </div>
  );
}

/* --------------------------------------------------------------------- quiz */

function QuizTab(props: { types: string[]; onUsed: () => void }) {
  const { types, onUsed } = props;
  const allTypes = types.length ? types : ['multiple_choice', 'true_false', 'short_answer'];

  const [courseId, setCourseId] = useState(0);
  const [chapterId, setChapterId] = useState(0);
  const [quizTitle, setQuizTitle] = useState('');
  const [sourceText, setSourceText] = useState('');
  const [count, setCount] = useState(5);
  const [chosenTypes, setChosenTypes] = useState<string[]>(['multiple_choice', 'true_false']);
  const [questions, setQuestions] = useState<DraftQuestion[]>([]);
  const [busy, setBusy] = useState(false);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState('');
  const [created, setCreated] = useState('');

  const tree = useCurriculumTree(courseId);
  const chapters = tree.data ?? [];
  const chapter = chapters.find((c) => c.id === chapterId);

  const generate = async () => {
    setBusy(true);
    setError('');
    setCreated('');
    try {
      const res = await getSikshyaApi().post<{ questions?: DraftQuestion[] }>(SIKSHYA_ENDPOINTS.ai.quiz, {
        chapter_id: chapterId,
        source_title: chapter ? chapter.title : quizTitle,
        source_text: sourceText,
        question_count: count,
        types: chosenTypes,
      });
      setQuestions(Array.isArray(res.questions) ? res.questions : []);
    } catch (e) {
      setError(getErrorSummary(e));
      setQuestions([]);
    } finally {
      setBusy(false);
      onUsed();
    }
  };

  const create = async () => {
    setCreating(true);
    setError('');
    try {
      const res = await getSikshyaApi().post<{ question_ids?: number[]; skipped?: number }>(
        SIKSHYA_ENDPOINTS.ai.applyQuiz,
        {
          chapter_id: chapterId,
          title: quizTitle.trim() || `${chapter ? chapter.title : __('Quiz', 'sikshya')}`,
          questions,
        }
      );
      const made = res.question_ids?.length ?? 0;
      setQuestions([]);
      setCreated(
        `${__('Quiz created with', 'sikshya')} ${made} ${__('questions.', 'sikshya')}${
          res.skipped ? ` ${res.skipped} ${__('were skipped.', 'sikshya')}` : ''
        }`
      );
      tree.refetch();
    } catch (e) {
      setError(getErrorSummary(e));
    } finally {
      setCreating(false);
    }
  };

  const toggleType = (type: string) => {
    setChosenTypes((prev) =>
      prev.includes(type) ? prev.filter((t) => t !== type) : prev.concat(type)
    );
  };

  return (
    <div className="space-y-6">
      <section className={CARD}>
        <h2 className="text-base font-semibold text-slate-900 dark:text-white">
          {__('Write quiz questions', 'sikshya')}
        </h2>
        <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
          {__('Questions are created as a real quiz inside the chapter you choose, with explanations learners see after grading.', 'sikshya')}
        </p>

        <div className="mt-4 grid gap-4 md:grid-cols-2">
          <div data-sikshya-ai-course-picker>
            <SingleCoursePicker label={__('Course', 'sikshya')} value={courseId} onChange={(id) => { setCourseId(id); setChapterId(0); }} />
          </div>
          <div>
            <label className={LABEL} htmlFor="sikshya-ai-chapter-select">
              {__('Chapter', 'sikshya')}
            </label>
            <select
              id="sikshya-ai-chapter-select"
              className={FIELD}
              value={chapterId}
              disabled={courseId <= 0 || tree.loading}
              onChange={(e) => setChapterId(Number(e.target.value) || 0)}
              data-sikshya-ai-chapter-select
            >
              <option value={0}>
                {courseId <= 0
                  ? __('Choose a course first', 'sikshya')
                  : chapters.length === 0
                    ? __('This course has no chapters yet', 'sikshya')
                    : __('Choose a chapter…', 'sikshya')}
              </option>
              {chapters.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.title}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className={LABEL} htmlFor="sikshya-ai-quiz-title">
              {__('Quiz title', 'sikshya')}
            </label>
            <input
              id="sikshya-ai-quiz-title"
              className={FIELD}
              value={quizTitle}
              onChange={(e) => setQuizTitle(e.target.value)}
              placeholder={chapter ? `${chapter.title} — ${__('knowledge check', 'sikshya')}` : __('Knowledge check', 'sikshya')}
              data-sikshya-ai-quiz-title
            />
          </div>
          <div>
            <label className={LABEL} htmlFor="sikshya-ai-question-count">
              {__('How many questions', 'sikshya')}
            </label>
            <input
              id="sikshya-ai-question-count"
              type="number"
              min={1}
              max={30}
              className={FIELD}
              value={count}
              onChange={(e) => setCount(Number(e.target.value) || 1)}
            />
          </div>
          <div className="md:col-span-2">
            <span className={LABEL}>{__('Question types', 'sikshya')}</span>
            <div className="mt-2 flex flex-wrap gap-4">
              {allTypes.map((type) => (
                <label key={type} className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                  <input
                    type="checkbox"
                    checked={chosenTypes.includes(type)}
                    onChange={() => toggleType(type)}
                  />
                  {QUESTION_TYPE_LABELS[type] ?? type}
                </label>
              ))}
            </div>
          </div>
          <div className="md:col-span-2">
            <label className={LABEL} htmlFor="sikshya-ai-source-text">
              {__('Material to draw on (optional)', 'sikshya')}
            </label>
            <textarea
              id="sikshya-ai-source-text"
              className={FIELD}
              rows={5}
              value={sourceText}
              onChange={(e) => setSourceText(e.target.value)}
              placeholder={__('Paste the lesson text so the questions test what you actually taught.', 'sikshya')}
            />
          </div>
        </div>

        <div className="mt-4">
          <span data-sikshya-ai-generate-quiz>
            <ButtonPrimary
              type="button"
              disabled={busy || chapterId <= 0 || chosenTypes.length === 0}
              onClick={() => void generate()}
            >
              {busy ? __('Writing…', 'sikshya') : __('Write the questions', 'sikshya')}
            </ButtonPrimary>
          </span>
        </div>
        {error ? (
          <p className="mt-3 text-sm text-rose-700 dark:text-rose-300" data-sikshya-ai-error>
            {error}
          </p>
        ) : null}
        {created ? (
          <p className="mt-3 text-sm text-emerald-800 dark:text-emerald-200" data-sikshya-ai-applied>
            {created}
          </p>
        ) : null}
      </section>

      {questions.length ? (
        <section className={CARD} data-sikshya-ai-quiz-draft>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-base font-semibold text-slate-900 dark:text-white">
              {questions.length} {__('questions drafted', 'sikshya')}
            </h2>
            <ButtonSecondary type="button" onClick={() => setQuestions([])}>
              {__('Discard draft', 'sikshya')}
            </ButtonSecondary>
          </div>

          <ol className="mt-4 space-y-4">
            {questions.map((question, qi) => (
              <li
                key={`question-${qi}`}
                className="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
                data-sikshya-ai-draft-question
              >
                <div className="flex items-start justify-between gap-3">
                  <p className="text-sm font-medium text-slate-900 dark:text-white">
                    {qi + 1}. {question.text}
                  </p>
                  <button
                    type="button"
                    className="shrink-0 text-xs text-slate-500 underline hover:text-rose-700 dark:text-slate-400"
                    onClick={() => setQuestions((prev) => prev.filter((_, i) => i !== qi))}
                  >
                    {__('Remove', 'sikshya')}
                  </button>
                </div>
                <p className="mt-1 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  {QUESTION_TYPE_LABELS[question.type] ?? question.type}
                </p>
                {question.options.length ? (
                  <ul className="mt-2 space-y-1 text-sm">
                    {question.options.map((option, oi) => {
                      const isCorrect = String(oi) === String(question.correct);
                      return (
                        <li
                          key={`option-${qi}-${oi}`}
                          className={
                            isCorrect
                              ? 'font-medium text-emerald-800 dark:text-emerald-200'
                              : 'text-slate-600 dark:text-slate-300'
                          }
                        >
                          {isCorrect ? '✓ ' : '• '}
                          {option}
                        </li>
                      );
                    })}
                  </ul>
                ) : (
                  <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    {__('Accepted answers:', 'sikshya')}{' '}
                    <span className="font-medium">{question.correct.split('|').join(', ')}</span>
                  </p>
                )}
                {question.explanation ? (
                  <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">{question.explanation}</p>
                ) : null}
              </li>
            ))}
          </ol>

          <div className="mt-5 flex items-center gap-3">
            <span data-sikshya-ai-apply-quiz>
              <ButtonPrimary
                type="button"
                disabled={creating || chapterId <= 0}
                onClick={() => void create()}
              >
                {creating ? __('Creating…', 'sikshya') : __('Create the quiz', 'sikshya')}
              </ButtonPrimary>
            </span>
            <span className="text-xs text-slate-500 dark:text-slate-400">
              {__('Creates one quiz in the chosen chapter, with these questions.', 'sikshya')}
            </span>
          </div>
        </section>
      ) : null}
    </div>
  );
}
