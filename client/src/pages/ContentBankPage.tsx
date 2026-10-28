import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { useAddonEnabled } from '../hooks/useAddons';
import { useAsyncData } from '../hooks/useAsyncData';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { SkeletonCard } from '../components/shared/Skeleton';
import { ConfirmDialog } from '../components/shared/ConfirmDialog';
import { HorizontalEditorTabs } from '../components/shared/HorizontalEditorTabs';
import { Modal } from '../components/shared/Modal';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';
import type { SikshyaReactConfig } from '../types';
import { __ } from '../lib/i18n';

type BankKind = 'lesson' | 'quiz' | 'assignment';

type BankItem = {
  id: number;
  title: string;
  kind: BankKind | string;
  visibility: 'private' | 'shared' | string;
  author_id: number;
  author_name: string;
  question_count: number;
  source_id: number;
  created: string;
  excerpt: string;
};

type ItemsResp = {
  ok?: boolean;
  items?: BankItem[];
  total?: number;
  pages?: number;
  counts?: Partial<Record<BankKind, number>>;
  sees_every_item?: boolean;
};

type TargetChapter = { id: number; title: string };
type TargetCourse = { id: number; title: string; status: string; chapters: TargetChapter[] };
type TargetsResp = { ok?: boolean; courses?: TargetCourse[] };

type CourseSection = {
  id: number;
  title: string;
  items: Array<{ id: number; title: string; kind: BankKind | string }>;
};
type CourseContentResp = { ok?: boolean; sections?: CourseSection[] };

type CaptureResp = {
  ok?: boolean;
  saved?: BankItem[];
  skipped?: Array<{ id: number; reason: string }>;
};

type PanelTab = 'library' | 'capture';

const TAB_LIST: { id: PanelTab; label: string; icon?: string }[] = [
  { id: 'library', label: 'Library', icon: 'layers' },
  { id: 'capture', label: 'Save from a course', icon: 'bookOpen' },
];

const KIND_LABEL: Record<string, string> = {
  lesson: 'Lesson',
  quiz: 'Quiz',
  assignment: 'Assignment',
};

const INPUT_CLASS =
  'rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500';

const LABEL_CLASS = 'block text-xs font-medium text-slate-600 dark:text-slate-300';

function kindLabel(kind: string): string {
  return KIND_LABEL[kind] ?? kind;
}

function errorMessage(err: unknown, fallback: string): string {
  return err instanceof Error && err.message ? err.message : fallback;
}

export function ContentBankPage(props: {
  config: SikshyaReactConfig;
  title: string;
  embedded?: boolean;
}) {
  const { config, title, embedded } = props;
  const featureOk = isFeatureEnabled(config, 'content_bank');
  const addon = useAddonEnabled('content_bank');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [tab, setTab] = useState<PanelTab>('library');
  const [reloadTick, setReloadTick] = useState(0);

  const inner = (
    <GatedFeatureWorkspace
      mode={mode}
      featureId="content_bank"
      config={config}
      featureTitle={__('Content bank', 'sikshya')}
      featureDescription={__(
        'Keep a library of lessons, quizzes and assignments, then drop a copy into any course you can edit.',
        'sikshya',
      )}
      previewVariant="cards"
      addonEnableTitle={__('The content bank is not enabled', 'sikshya')}
      addonEnableDescription={__(
        'Enable the add-on to save content to a reusable library and insert it into other courses.',
        'sikshya',
      )}
      canEnable={Boolean(addon.licenseOk)}
      enableBusy={addon.loading}
      onEnable={() => addon.enable()}
      addonError={addon.error}
    >
      {enabled ? (
        <div className="space-y-6">
          <HorizontalEditorTabs
            ariaLabel={__('Content bank sections', 'sikshya')}
            tabs={TAB_LIST}
            value={tab}
            onChange={(id) => setTab(id as PanelTab)}
          />
          {tab === 'library' ? <LibraryPanel reloadTick={reloadTick} /> : null}
          {tab === 'capture' ? (
            <CapturePanel
              onSaved={() => {
                setReloadTick((n) => n + 1);
                setTab('library');
              }}
            />
          ) : null}
        </div>
      ) : null}
    </GatedFeatureWorkspace>
  );

  return (
    <EmbeddableShell embedded={embedded} config={config} title={title}>
      {inner}
    </EmbeddableShell>
  );
}

// ============================================================================
//  LIBRARY (browse, rename, share, insert, delete)
// ============================================================================

function LibraryPanel({ reloadTick }: { reloadTick: number }) {
  const [kind, setKind] = useState<'' | BankKind>('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [localTick, setLocalTick] = useState(0);
  const [insertFor, setInsertFor] = useState<BankItem | null>(null);
  const [renameFor, setRenameFor] = useState<BankItem | null>(null);
  const [confirmDelete, setConfirmDelete] = useState<BankItem | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);
  const toast = useTopRightToast(2600);

  const loader = useCallback(
    async () =>
      getSikshyaApi().get<ItemsResp>(
        SIKSHYA_ENDPOINTS.pro.contentBankItems({
          kind: kind || undefined,
          search: search.trim() || undefined,
          page,
          per_page: 20,
        }),
      ),
    [kind, search, page, localTick, reloadTick],
  );
  const { loading, data, error, refetch } = useAsyncData(loader, [kind, search, page, localTick, reloadTick]);

  const items = data?.items ?? [];
  const total = data?.total ?? 0;
  const pages = data?.pages ?? 1;
  const counts = data?.counts ?? {};

  const reload = () => setLocalTick((n) => n + 1);

  const onToggleShare = async (item: BankItem) => {
    setBusyId(item.id);
    try {
      await getSikshyaApi().patch(SIKSHYA_ENDPOINTS.pro.contentBankItem(item.id), {
        visibility: item.visibility === 'shared' ? 'private' : 'shared',
      });
      toast.success(
        item.visibility === 'shared' ? __('Made private', 'sikshya') : __('Shared', 'sikshya'),
        item.visibility === 'shared'
          ? 'Only you can see this item now.'
          : 'Every author on this site can now insert this item.',
      );
      reload();
    } catch (err) {
      toast.error(__('Could not change sharing', 'sikshya'), errorMessage(err, 'Request failed'));
    } finally {
      setBusyId(null);
    }
  };

  const onDelete = async (item: BankItem) => {
    setBusyId(item.id);
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.pro.contentBankItem(item.id));
      toast.success(__('Removed', 'sikshya'), 'The item is gone from the bank. Courses built from it are untouched.');
      setConfirmDelete(null);
      reload();
    } catch (err) {
      toast.error(__('Could not remove', 'sikshya'), errorMessage(err, 'Request failed'));
    } finally {
      setBusyId(null);
    }
  };

  const kindTabs = useMemo(
    () =>
      [
        { value: '' as const, label: __('All', 'sikshya') },
        { value: 'lesson' as const, label: `${__('Lessons', 'sikshya')} (${counts.lesson ?? 0})` },
        { value: 'quiz' as const, label: `${__('Quizzes', 'sikshya')} (${counts.quiz ?? 0})` },
        { value: 'assignment' as const, label: `${__('Assignments', 'sikshya')} (${counts.assignment ?? 0})` },
      ] as const,
    [counts.lesson, counts.quiz, counts.assignment],
  );

  return (
    <div className="space-y-4">
      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
        <h2 className="text-base font-semibold text-slate-900 dark:text-white">{__('Reusable content', 'sikshya')}</h2>
        <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
          Inserting an item creates a copy in the section you choose. Progress and quiz attempts are recorded against a
          specific lesson, so copies keep each course&rsquo;s records separate — and editing a banked item does not change
          courses already built from it.
        </p>
      </section>

      <section className="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4 dark:border-slate-800">
          <div>
            <label className={LABEL_CLASS} htmlFor="sikshya-bank-kind">
              {__('Type', 'sikshya')}
            </label>
            <select
              id="sikshya-bank-kind"
              className={`mt-1 w-44 ${INPUT_CLASS}`}
              value={kind}
              onChange={(e) => {
                setKind(e.target.value as '' | BankKind);
                setPage(1);
              }}
            >
              {kindTabs.map((t) => (
                <option key={t.value || 'all'} value={t.value}>
                  {t.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className={LABEL_CLASS} htmlFor="sikshya-bank-search">
              {__('Search', 'sikshya')}
            </label>
            <input
              id="sikshya-bank-search"
              type="search"
              className={`mt-1 w-64 ${INPUT_CLASS}`}
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              placeholder={__('Search the bank…', 'sikshya')}
            />
          </div>
          <span className="ml-auto text-xs text-slate-500">
            {total} item{total === 1 ? '' : 's'}
          </span>
        </div>

        <div className="p-3">
          {loading ? (
            <SkeletonCard rows={4} />
          ) : error ? (
            <ApiErrorPanel error={error} title={__('Could not load the bank', 'sikshya')} onRetry={() => refetch()} />
          ) : items.length === 0 ? (
            <div className="rounded-lg border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-slate-700">
              {__('Nothing saved yet. Use “Save from a course” to put something in the bank.', 'sikshya')}
            </div>
          ) : (
            <ul className="space-y-2">
              {items.map((item) => (
                <li
                  key={item.id}
                  className="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                >
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium text-slate-900 dark:text-white">{item.title}</span>
                      <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {kindLabel(String(item.kind))}
                      </span>
                      {item.kind === 'quiz' ? (
                        <span className="text-xs text-slate-500">
                          {item.question_count} question{item.question_count === 1 ? '' : 's'}
                        </span>
                      ) : null}
                      {item.visibility === 'shared' ? (
                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                          {__('Shared', 'sikshya')}
                        </span>
                      ) : null}
                    </div>
                    {item.excerpt ? (
                      <p className="mt-1 max-w-2xl text-xs text-slate-500 dark:text-slate-400">{item.excerpt}</p>
                    ) : null}
                    {item.author_name ? (
                      <p className="mt-1 text-xs text-slate-400">{`Saved by ${item.author_name}`}</p>
                    ) : null}
                  </div>
                  <div className="flex flex-wrap justify-end gap-2">
                    <ButtonPrimary type="button" onClick={() => setInsertFor(item)}>
                      {__('Insert into course', 'sikshya')}
                    </ButtonPrimary>
                    <ButtonSecondary type="button" onClick={() => setRenameFor(item)} disabled={busyId === item.id}>
                      {__('Rename', 'sikshya')}
                    </ButtonSecondary>
                    <ButtonSecondary
                      type="button"
                      onClick={() => void onToggleShare(item)}
                      disabled={busyId === item.id}
                    >
                      {item.visibility === 'shared' ? __('Make private', 'sikshya') : __('Share', 'sikshya')}
                    </ButtonSecondary>
                    <ButtonSecondary type="button" onClick={() => setConfirmDelete(item)} disabled={busyId === item.id}>
                      {__('Remove', 'sikshya')}
                    </ButtonSecondary>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>

        {pages > 1 ? (
          <div className="flex items-center justify-end gap-2 border-t border-slate-100 p-3 dark:border-slate-800">
            <ButtonSecondary type="button" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
              {__('Previous', 'sikshya')}
            </ButtonSecondary>
            <span className="text-xs text-slate-500">{`Page ${page} of ${pages}`}</span>
            <ButtonSecondary type="button" disabled={page >= pages} onClick={() => setPage((p) => p + 1)}>
              {__('Next', 'sikshya')}
            </ButtonSecondary>
          </div>
        ) : null}
      </section>

      {insertFor ? (
        <InsertDialog
          item={insertFor}
          onClose={() => setInsertFor(null)}
          onInserted={(courseTitle) => {
            setInsertFor(null);
            toast.success(__('Inserted', 'sikshya'), `A copy was added to ${courseTitle}.`);
          }}
        />
      ) : null}

      {renameFor ? (
        <RenameDialog
          item={renameFor}
          onClose={() => setRenameFor(null)}
          onRenamed={() => {
            setRenameFor(null);
            toast.success(__('Renamed', 'sikshya'), 'The bank item was renamed.');
            reload();
          }}
        />
      ) : null}

      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />

      <ConfirmDialog
        open={Boolean(confirmDelete)}
        title={__('Remove from the bank?', 'sikshya')}
        confirmLabel={__('Remove', 'sikshya')}
        variant="danger"
        busy={busyId !== null}
        onClose={() => setConfirmDelete(null)}
        onConfirm={async () => {
          if (confirmDelete) await onDelete(confirmDelete);
        }}
      >
        {__(
          'This removes the saved copy from your library. Lessons, quizzes and assignments already inserted into courses are ordinary course content and are not touched.',
          'sikshya',
        )}
      </ConfirmDialog>
    </div>
  );
}

// ============================================================================
//  INSERT (pick a course, pick a section)
// ============================================================================

function InsertDialog(props: {
  item: BankItem;
  onClose: () => void;
  onInserted: (courseTitle: string) => void;
}) {
  const { item, onClose, onInserted } = props;
  const [courseId, setCourseId] = useState(0);
  const [chapterId, setChapterId] = useState(0);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);

  const loader = useCallback(
    async () => getSikshyaApi().get<TargetsResp>(SIKSHYA_ENDPOINTS.pro.contentBankTargets),
    [],
  );
  const { loading, data, error, refetch } = useAsyncData(loader, []);

  const courses = data?.courses ?? [];
  const course = courses.find((c) => c.id === courseId) ?? null;
  const chapters = course?.chapters ?? [];

  const submit = async () => {
    if (chapterId <= 0) return;
    setBusy(true);
    setErr(null);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.contentBankInsert(item.id), { chapter_id: chapterId });
      onInserted(course ? course.title : __('the course', 'sikshya'));
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal open title={__('Insert into a course', 'sikshya')} onClose={onClose}>
      <div className="space-y-4">
        <p className="text-sm text-slate-600 dark:text-slate-400">
          {`“${item.title}” will be copied into the section you pick. The copy is independent of this bank item.`}
        </p>

        {loading ? (
          <SkeletonCard rows={3} />
        ) : error ? (
          <ApiErrorPanel error={error} title={__('Could not load courses', 'sikshya')} onRetry={() => refetch()} />
        ) : courses.length === 0 ? (
          <p className="text-sm text-slate-500">{__('There are no courses you can add content to.', 'sikshya')}</p>
        ) : (
          <>
            <div>
              <label className={LABEL_CLASS} htmlFor="sikshya-bank-insert-course">
                {__('Course', 'sikshya')}
              </label>
              <select
                id="sikshya-bank-insert-course"
                className={`mt-1 w-full ${INPUT_CLASS}`}
                value={courseId}
                onChange={(e) => {
                  setCourseId(Number(e.target.value) || 0);
                  setChapterId(0);
                }}
              >
                <option value={0}>{__('Choose a course…', 'sikshya')}</option>
                {courses.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.status === 'publish' ? c.title : `${c.title} (${c.status})`}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className={LABEL_CLASS} htmlFor="sikshya-bank-insert-section">
                {__('Section', 'sikshya')}
              </label>
              <select
                id="sikshya-bank-insert-section"
                className={`mt-1 w-full ${INPUT_CLASS}`}
                value={chapterId}
                onChange={(e) => setChapterId(Number(e.target.value) || 0)}
                disabled={courseId <= 0}
              >
                <option value={0}>{__('Choose a section…', 'sikshya')}</option>
                {chapters.map((ch) => (
                  <option key={ch.id} value={ch.id}>
                    {ch.title}
                  </option>
                ))}
              </select>
              {courseId > 0 && chapters.length === 0 ? (
                <p className="mt-2 text-xs text-amber-700 dark:text-amber-300">
                  {__('That course has no sections yet. Add one in the course builder first.', 'sikshya')}
                </p>
              ) : null}
            </div>
          </>
        )}

        {err ? <ApiErrorPanel error={err} title={__('Insert failed', 'sikshya')} /> : null}

        <div className="flex justify-end gap-2">
          <ButtonSecondary type="button" onClick={onClose} disabled={busy}>
            {__('Cancel', 'sikshya')}
          </ButtonSecondary>
          <ButtonPrimary type="button" onClick={() => void submit()} disabled={busy || chapterId <= 0}>
            {busy ? __('Inserting…', 'sikshya') : __('Insert a copy', 'sikshya')}
          </ButtonPrimary>
        </div>
      </div>
    </Modal>
  );
}

function RenameDialog(props: { item: BankItem; onClose: () => void; onRenamed: () => void }) {
  const { item, onClose, onRenamed } = props;
  const [title, setTitle] = useState(item.title);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);

  const submit = async () => {
    if (title.trim() === '') return;
    setBusy(true);
    setErr(null);
    try {
      await getSikshyaApi().patch(SIKSHYA_ENDPOINTS.pro.contentBankItem(item.id), { title: title.trim() });
      onRenamed();
    } catch (e) {
      setErr(e);
      setBusy(false);
    }
  };

  return (
    <Modal open title={__('Rename bank item', 'sikshya')} onClose={onClose}>
      <div className="space-y-4">
        <div>
          <label className={LABEL_CLASS} htmlFor="sikshya-bank-rename">
            {__('Name in the bank', 'sikshya')}
          </label>
          <input
            id="sikshya-bank-rename"
            type="text"
            className={`mt-1 w-full ${INPUT_CLASS}`}
            value={title}
            onChange={(e) => setTitle(e.target.value)}
          />
          <p className="mt-2 text-xs text-slate-500">
            {__('This is the name in your library. Content already inserted into a course keeps its own title.', 'sikshya')}
          </p>
        </div>
        {err ? <ApiErrorPanel error={err} title={__('Rename failed', 'sikshya')} /> : null}
        <div className="flex justify-end gap-2">
          <ButtonSecondary type="button" onClick={onClose} disabled={busy}>
            {__('Cancel', 'sikshya')}
          </ButtonSecondary>
          <ButtonPrimary type="button" onClick={() => void submit()} disabled={busy || title.trim() === ''}>
            {busy ? __('Saving…', 'sikshya') : __('Save', 'sikshya')}
          </ButtonPrimary>
        </div>
      </div>
    </Modal>
  );
}

// ============================================================================
//  CAPTURE (pick a course, tick what is worth keeping)
// ============================================================================

function CapturePanel({ onSaved }: { onSaved: () => void }) {
  const [courseId, setCourseId] = useState(0);
  const [picked, setPicked] = useState<number[]>([]);
  const [shared, setShared] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<unknown>(null);
  const [skipped, setSkipped] = useState<Array<{ id: number; reason: string }>>([]);
  const toast = useTopRightToast(3200);

  const coursesLoader = useCallback(
    async () => getSikshyaApi().get<TargetsResp>(SIKSHYA_ENDPOINTS.pro.contentBankTargets),
    [],
  );
  const courses = useAsyncData(coursesLoader, []);

  const contentLoader = useCallback(async () => {
    if (courseId <= 0) return null;
    return getSikshyaApi().get<CourseContentResp>(SIKSHYA_ENDPOINTS.pro.contentBankCourseContent(courseId));
  }, [courseId]);
  const content = useAsyncData(contentLoader, [courseId]);

  const sections = content.data?.sections ?? [];
  const toggle = (id: number) =>
    setPicked((prev) => (prev.includes(id) ? prev.filter((n) => n !== id) : [...prev, id]));

  const submit = async () => {
    if (picked.length === 0) return;
    setBusy(true);
    setErr(null);
    setSkipped([]);
    try {
      const resp = await getSikshyaApi().post<CaptureResp>(SIKSHYA_ENDPOINTS.pro.contentBankItems(), {
        post_ids: picked,
        visibility: shared ? 'shared' : 'private',
      });
      const savedCount = resp.saved?.length ?? 0;
      setSkipped(resp.skipped ?? []);
      setPicked([]);
      if (savedCount > 0) {
        toast.success(__('Saved to the bank', 'sikshya'), `${savedCount} item${savedCount === 1 ? '' : 's'} saved.`);
        if ((resp.skipped ?? []).length === 0) onSaved();
      } else {
        toast.error(__('Nothing was saved', 'sikshya'), 'See the notes below.');
      }
    } catch (e) {
      setErr(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-4">
      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
        <h2 className="text-base font-semibold text-slate-900 dark:text-white">{__('Save from a course', 'sikshya')}</h2>
        <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
          {__(
            'Pick a course, then tick the lessons, quizzes and assignments worth keeping. A quiz is saved with its questions, answer keys and settings.',
            'sikshya',
          )}
        </p>

        <div className="mt-4 flex flex-wrap items-end gap-3">
          <div>
            <label className={LABEL_CLASS} htmlFor="sikshya-bank-capture-course">
              {__('Course', 'sikshya')}
            </label>
            <select
              id="sikshya-bank-capture-course"
              className={`mt-1 w-72 ${INPUT_CLASS}`}
              value={courseId}
              onChange={(e) => {
                setCourseId(Number(e.target.value) || 0);
                setPicked([]);
                setSkipped([]);
              }}
            >
              <option value={0}>{__('Choose a course…', 'sikshya')}</option>
              {(courses.data?.courses ?? []).map((c) => (
                <option key={c.id} value={c.id}>
                  {c.status === 'publish' ? c.title : `${c.title} (${c.status})`}
                </option>
              ))}
            </select>
          </div>
          <div className="flex items-center gap-2 pb-2">
            <input
              id="sikshya-bank-capture-shared"
              type="checkbox"
              className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600"
              checked={shared}
              onChange={(e) => setShared(e.target.checked)}
            />
            <label className="text-xs text-slate-600 dark:text-slate-300" htmlFor="sikshya-bank-capture-shared">
              {__('Share with the other authors on this site', 'sikshya')}
            </label>
          </div>
          <ButtonPrimary
            type="button"
            className="mb-1"
            onClick={() => void submit()}
            disabled={busy || picked.length === 0}
          >
            {busy
              ? __('Saving…', 'sikshya')
              : `${__('Save', 'sikshya')} ${picked.length > 0 ? `(${picked.length})` : ''}`.trim()}
          </ButtonPrimary>
        </div>

        {courses.error ? (
          <div className="mt-3">
            <ApiErrorPanel error={courses.error} title={__('Could not load courses', 'sikshya')} />
          </div>
        ) : null}
        {err ? (
          <div className="mt-3">
            <ApiErrorPanel error={err} title={__('Save failed', 'sikshya')} />
          </div>
        ) : null}
        {skipped.length > 0 ? (
          <ul className="mt-3 list-disc space-y-1 pl-5 text-xs text-amber-700 dark:text-amber-300">
            {skipped.map((s) => (
              <li key={s.id}>{`#${s.id}: ${s.reason}`}</li>
            ))}
          </ul>
        ) : null}
      </section>

      {courseId > 0 ? (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
          {content.loading ? (
            <SkeletonCard rows={4} />
          ) : content.error ? (
            <ApiErrorPanel
              error={content.error}
              title={__('Could not read that course', 'sikshya')}
              onRetry={() => content.refetch()}
            />
          ) : sections.length === 0 ? (
            <p className="text-sm text-slate-500">{__('That course has no sections yet.', 'sikshya')}</p>
          ) : (
            <div className="space-y-4">
              {sections.map((section) => (
                <div key={section.id}>
                  <h3 className="text-sm font-semibold text-slate-900 dark:text-white">{section.title}</h3>
                  {section.items.length === 0 ? (
                    <p className="mt-1 text-xs text-slate-500">{__('Nothing in this section.', 'sikshya')}</p>
                  ) : (
                    <ul className="mt-2 space-y-1">
                      {section.items.map((it) => (
                        <li key={it.id} className="flex items-center gap-2">
                          <input
                            id={`sikshya-bank-pick-${it.id}`}
                            type="checkbox"
                            className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600"
                            checked={picked.includes(it.id)}
                            onChange={() => toggle(it.id)}
                          />
                          <label
                            className="text-sm text-slate-700 dark:text-slate-200"
                            htmlFor={`sikshya-bank-pick-${it.id}`}
                          >
                            {it.title}
                            <span className="ml-2 text-xs text-slate-500">{kindLabel(String(it.kind))}</span>
                          </label>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              ))}
            </div>
          )}
        </section>
      ) : null}

      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />
    </div>
  );
}
