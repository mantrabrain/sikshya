import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPaginationBar, DEFAULT_LIST_PER_PAGE } from '../components/shared/list/ListPaginationBar';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { Modal } from '../components/shared/Modal';
import { SingleCoursePicker } from '../components/shared/SingleCoursePicker';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { formatPostDate } from '../lib/formatPostDate';
import type { SikshyaReactConfig } from '../types';

type ThreadStatus = 'pending' | 'approved' | 'spam';
type ThreadType = 'discussion' | 'qa';

type AuthorRef = {
  id: number;
  name: string;
  email?: string;
};

type CourseRef = {
  id: number;
  title: string;
  permalink?: string;
};

type ContentRef = {
  id: number;
  type: string;
  title: string;
  permalink?: string;
};

type ThreadRow = {
  id: number;
  content: string;
  excerpt: string;
  thread_type: ThreadType;
  status: ThreadStatus;
  is_pending: boolean;
  created_at: string;
  reply_count: number;
  author: AuthorRef;
  course: CourseRef;
  content_ref: ContentRef;
  can_moderate: boolean;
  can_edit: boolean;
  can_delete: boolean;
};

type ReplyRow = {
  id: number;
  parent_id: number;
  content: string;
  created_at: string;
  status: ThreadStatus;
  is_pending: boolean;
  author: AuthorRef;
  can_edit: boolean;
  can_delete: boolean;
};

type ThreadDetail = ThreadRow & { replies: ReplyRow[] };

type ListResponse = {
  success: boolean;
  data: { items: ThreadRow[]; total: number; page: number; per_page: number };
};

type DetailResponse = {
  success: boolean;
  data: ThreadDetail;
};

type SummaryResponse = {
  success: boolean;
  data: {
    total: number;
    pending: number;
    discussions: number;
    qa: number;
    replies: number;
  };
};

type StatusFilter = 'pending' | 'approved' | 'spam' | 'all';
type ContentTypeFilter = '' | 'lesson' | 'quiz';
type ThreadTypeFilter = '' | ThreadType;

const STATUS_OPTIONS: Array<{ value: StatusFilter; label: string }> = [
  { value: 'all', label: 'All statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'spam', label: 'Rejected (spam)' },
];

const THREAD_TYPE_OPTIONS: Array<{ value: ThreadTypeFilter; label: string }> = [
  { value: '', label: 'Discussions and Q&A' },
  { value: 'discussion', label: 'Discussions only' },
  { value: 'qa', label: 'Q&A only' },
];

const CONTENT_TYPE_OPTIONS: Array<{ value: ContentTypeFilter; label: string }> = [
  { value: '', label: 'Lessons and quizzes' },
  { value: 'lesson', label: 'Lessons only' },
  { value: 'quiz', label: 'Quizzes only' },
];

function StatusPill({ status }: { status: ThreadStatus }) {
  if (status === 'approved') {
    return (
      <span className="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
        Approved
      </span>
    );
  }
  if (status === 'pending') {
    return (
      <span className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
        Pending
      </span>
    );
  }
  return (
    <span className="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-900/40 dark:text-rose-200">
      Rejected
    </span>
  );
}

function TypeTag({ type }: { type: ThreadType }) {
  const isQa = type === 'qa';
  return (
    <span
      className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ${
        isQa
          ? 'bg-violet-100 text-violet-800 dark:bg-violet-900/50 dark:text-violet-200'
          : 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-200'
      }`}
    >
      {isQa ? 'Q&A' : 'Discussion'}
    </span>
  );
}

function PostTypeLabel({ type }: { type: string }) {
  const t = type === 'sik_lesson' ? 'Lesson' : type === 'sik_quiz' ? 'Quiz' : type || 'Content';
  return (
    <span className="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700 dark:bg-slate-800 dark:text-slate-300">
      {t}
    </span>
  );
}

export function DiscussionsPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const { confirm } = useSikshyaDialog();

  const featureOk = isFeatureEnabled(config, 'community_discussions');
  const addon = useAddonEnabled('community_discussions');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const gateOpen = mode === 'full';

  const [filterCourseId, setFilterCourseId] = useState(0);
  const [filterContentType, setFilterContentType] = useState<ContentTypeFilter>('');
  const [filterThreadType, setFilterThreadType] = useState<ThreadTypeFilter>('');
  const [status, setStatus] = useState<StatusFilter>('all');
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [page, setPage] = useState(1);
  const perPage = DEFAULT_LIST_PER_PAGE;
  const [rowBusyId, setRowBusyId] = useState<number | null>(null);

  const [drawerThreadId, setDrawerThreadId] = useState<number | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editingDraft, setEditingDraft] = useState<string>('');
  const [editingBusy, setEditingBusy] = useState(false);
  const [replyDraft, setReplyDraft] = useState<string>('');
  const [replyBusy, setReplyBusy] = useState(false);

  const listLoader = useCallback(async () => {
    if (!gateOpen) {
      return {
        success: true,
        data: { items: [], total: 0, page: 1, per_page: perPage },
      } as ListResponse;
    }
    return getSikshyaApi().get<ListResponse>(
      SIKSHYA_ENDPOINTS.admin.discussions({
        course_id: filterCourseId > 0 ? filterCourseId : undefined,
        content_type: filterContentType || undefined,
        thread_type: filterThreadType || undefined,
        status,
        search: search || undefined,
        page,
        per_page: perPage,
      })
    );
  }, [gateOpen, filterCourseId, filterContentType, filterThreadType, status, search, page, perPage]);

  const summaryLoader = useCallback(async () => {
    if (!gateOpen) {
      return { success: true, data: { total: 0, pending: 0, discussions: 0, qa: 0, replies: 0 } } as SummaryResponse;
    }
    return getSikshyaApi().get<SummaryResponse>(SIKSHYA_ENDPOINTS.admin.discussionsSummary);
  }, [gateOpen]);

  const list = useAsyncData(listLoader, [
    gateOpen,
    filterCourseId,
    filterContentType,
    filterThreadType,
    status,
    search,
    page,
    perPage,
  ]);
  const summary = useAsyncData(summaryLoader, [gateOpen]);

  const rows = list.data?.data.items ?? [];
  const total = list.data?.data.total ?? 0;
  const totalPages = useMemo(() => {
    if (!total) return 1;
    return Math.max(1, Math.ceil(total / perPage));
  }, [total, perPage]);

  const detailLoader = useCallback(async () => {
    if (drawerThreadId == null) {
      return null;
    }
    return getSikshyaApi().get<DetailResponse>(SIKSHYA_ENDPOINTS.admin.discussion(drawerThreadId));
  }, [drawerThreadId]);
  const detail = useAsyncData(detailLoader, [drawerThreadId]);
  const detailRow = detail.data?.data ?? null;

  const refreshAll = () => {
    list.refetch();
    summary.refetch();
    detail.refetch();
  };

  const onSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    setSearch(searchInput.trim());
  };

  const resetFilters = () => {
    setFilterCourseId(0);
    setFilterContentType('');
    setFilterThreadType('');
    setStatus('all');
    setSearch('');
    setSearchInput('');
    setPage(1);
  };

  const approve = async (id: number) => {
    setRowBusyId(id);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.discussionApprove(id), {});
      refreshAll();
    } finally {
      setRowBusyId(null);
    }
  };

  const reject = async (id: number) => {
    setRowBusyId(id);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.discussionReject(id), {});
      refreshAll();
    } finally {
      setRowBusyId(null);
    }
  };

  const remove = async (id: number, kind: 'thread' | 'reply') => {
    const ok = await confirm({
      title: kind === 'thread' ? 'Delete thread?' : 'Delete reply?',
      message:
        kind === 'thread'
          ? 'This permanently removes the thread and all of its replies.'
          : 'This permanently removes the reply.',
      variant: 'danger',
      confirmLabel: 'Delete',
    });
    if (!ok) return;
    setRowBusyId(id);
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.admin.discussion(id));
      if (kind === 'thread' && drawerThreadId === id) {
        setDrawerThreadId(null);
      }
      refreshAll();
    } finally {
      setRowBusyId(null);
    }
  };

  const startEditing = (id: number, current: string) => {
    setEditingId(id);
    setEditingDraft(current);
  };

  const cancelEditing = () => {
    setEditingId(null);
    setEditingDraft('');
    setEditingBusy(false);
  };

  const saveEditing = async () => {
    if (editingId == null) return;
    const text = editingDraft.trim();
    if (!text) return;
    setEditingBusy(true);
    try {
      await getSikshyaApi().put(SIKSHYA_ENDPOINTS.admin.discussion(editingId), { content: text });
      cancelEditing();
      refreshAll();
    } finally {
      setEditingBusy(false);
    }
  };

  const submitReply = async () => {
    if (drawerThreadId == null) return;
    const text = replyDraft.trim();
    if (!text) return;
    setReplyBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.discussionReply(drawerThreadId), { content: text });
      setReplyDraft('');
      refreshAll();
    } finally {
      setReplyBusy(false);
    }
  };

  const summaryData = summary.data?.data ?? { total: 0, pending: 0, discussions: 0, qa: 0, replies: 0 };

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle="Browse, search, moderate, and reply to learner discussion threads and Q&A across every course."
      pageActions={
        gateOpen ? (
          <ButtonPrimary type="button" disabled={list.loading} onClick={() => refreshAll()}>
            {list.loading ? 'Refreshing…' : 'Refresh'}
          </ButtonPrimary>
        ) : null
      }
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="community_discussions"
        config={config}
        featureTitle="Community discussions and Q&A"
        featureDescription="Let learners ask questions and discuss lessons inside the Learn page, with optional instructor moderation. This screen lets you triage every thread across your catalog."
        previewVariant="table"
        addonEnableTitle="Discussions and Q&A is not enabled"
        addonEnableDescription="Enable the Community discussions add-on to register the Learn-page widgets, REST endpoints, and this moderation screen."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => addon.enable()}
        addonError={addon.error}
      >
        {gateOpen ? (
          <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <SummaryTile label="Threads" value={summaryData.total} />
            <SummaryTile label="Pending" value={summaryData.pending} accent="amber" />
            <SummaryTile label="Discussions" value={summaryData.discussions} />
            <SummaryTile label="Q&A" value={summaryData.qa} />
            <SummaryTile label="Replies" value={summaryData.replies} />
          </div>
        ) : null}

        {list.error ? (
          <div className="mb-4">
            <ApiErrorPanel error={list.error} title="Could not load discussions" onRetry={() => list.refetch()} />
          </div>
        ) : null}

        <ListPanel className="mb-4">
          <div className="space-y-4 border-b border-slate-100 p-4 dark:border-slate-800">
            <div className="grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
              <SingleCoursePicker
                value={filterCourseId}
                onChange={(id) => {
                  setFilterCourseId(id);
                  setPage(1);
                }}
                placeholder="All courses"
                hint="Optional — limit threads to a single course."
                className="w-full max-w-full"
              />
              <label className="block text-sm text-slate-600 dark:text-slate-400">
                Content type
                <select
                  value={filterContentType}
                  onChange={(e) => {
                    setFilterContentType(e.target.value as ContentTypeFilter);
                    setPage(1);
                  }}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                >
                  {CONTENT_TYPE_OPTIONS.map((o) => (
                    <option key={o.value || 'all'} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm text-slate-600 dark:text-slate-400">
                Thread type
                <select
                  value={filterThreadType}
                  onChange={(e) => {
                    setFilterThreadType(e.target.value as ThreadTypeFilter);
                    setPage(1);
                  }}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                >
                  {THREAD_TYPE_OPTIONS.map((o) => (
                    <option key={o.value || 'all'} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block text-sm text-slate-600 dark:text-slate-400">
                Status
                <select
                  value={status}
                  onChange={(e) => {
                    setStatus(e.target.value as StatusFilter);
                    setPage(1);
                  }}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                >
                  {STATUS_OPTIONS.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>
              </label>
              <form onSubmit={onSearchSubmit} className="flex items-end gap-2 lg:col-span-2">
                <label className="block flex-1 text-sm text-slate-600 dark:text-slate-400">
                  Search
                  <input
                    type="search"
                    value={searchInput}
                    onChange={(e) => setSearchInput(e.target.value)}
                    placeholder="Author, email, or content text…"
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                  />
                </label>
                <ButtonPrimary type="submit">Apply</ButtonPrimary>
                <ButtonSecondary type="button" onClick={() => resetFilters()}>
                  Reset
                </ButtonSecondary>
              </form>
            </div>
          </div>

          {list.loading ? (
            <div className="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>
          ) : rows.length === 0 ? (
            <ListEmptyState
              title="No threads match"
              description="Try widening the filters or clearing the search. Threads appear here when learners post in the Learn page Discussions or Q&A widgets."
            />
          ) : (
            <>
              <ListPaginationBar
                placement="top"
                page={page}
                total={total}
                totalPages={totalPages}
                perPage={perPage}
                onPageChange={(p) => setPage(p)}
                disabled={list.loading}
              />
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                  <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                    <tr>
                      <th className="px-5 py-3.5">Author</th>
                      <th className="px-5 py-3.5">Course</th>
                      <th className="px-5 py-3.5">Content</th>
                      <th className="px-5 py-3.5">Thread</th>
                      <th className="px-5 py-3.5">Replies</th>
                      <th className="px-5 py-3.5">Posted</th>
                      <th className="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {rows.map((r) => (
                      <tr key={r.id} className="bg-white dark:bg-slate-900">
                        <td className="px-5 py-3.5 align-top">
                          <div className="font-semibold text-slate-900 dark:text-white">
                            {r.author.name || `User #${r.author.id}`}
                          </div>
                          {r.author.email ? (
                            <div className="text-xs text-slate-500 dark:text-slate-400">{r.author.email}</div>
                          ) : null}
                        </td>
                        <td className="px-5 py-3.5 align-top">
                          {r.course.id > 0 ? (
                            r.course.permalink ? (
                              <a
                                href={r.course.permalink}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400"
                              >
                                {r.course.title || `#${r.course.id}`}
                              </a>
                            ) : (
                              <span>{r.course.title || `#${r.course.id}`}</span>
                            )
                          ) : (
                            <span className="text-slate-400">—</span>
                          )}
                        </td>
                        <td className="px-5 py-3.5 align-top">
                          <div className="flex items-center gap-2">
                            <PostTypeLabel type={r.content_ref.type} />
                            {r.content_ref.permalink ? (
                              <a
                                href={r.content_ref.permalink}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400"
                              >
                                {r.content_ref.title || `#${r.content_ref.id}`}
                              </a>
                            ) : (
                              <span>{r.content_ref.title || `#${r.content_ref.id}`}</span>
                            )}
                          </div>
                        </td>
                        <td className="max-w-[28rem] px-5 py-3.5 align-top">
                          <div className="mb-1 flex flex-wrap items-center gap-2">
                            <TypeTag type={r.thread_type} />
                            <StatusPill status={r.status} />
                          </div>
                          <p className="whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                            {r.excerpt}
                          </p>
                        </td>
                        <td className="px-5 py-3.5 align-top text-slate-700 dark:text-slate-300">
                          {r.reply_count}
                        </td>
                        <td className="whitespace-nowrap px-5 py-3.5 align-top text-slate-600 dark:text-slate-400">
                          {formatPostDate(r.created_at)}
                        </td>
                        <td className="whitespace-nowrap px-5 py-3.5 text-right align-top">
                          <div className="inline-flex flex-wrap justify-end gap-3 text-sm font-medium">
                            <button
                              type="button"
                              className="text-brand-600 hover:text-brand-800 dark:text-brand-400"
                              onClick={() => setDrawerThreadId(r.id)}
                            >
                              View
                            </button>
                            {r.is_pending && r.can_moderate ? (
                              <button
                                type="button"
                                disabled={rowBusyId === r.id}
                                onClick={() => approve(r.id)}
                                className="text-emerald-700 hover:text-emerald-900 disabled:opacity-50 dark:text-emerald-300"
                              >
                                Approve
                              </button>
                            ) : null}
                            {r.is_pending && r.can_moderate ? (
                              <button
                                type="button"
                                disabled={rowBusyId === r.id}
                                onClick={() => reject(r.id)}
                                className="text-amber-700 hover:text-amber-900 disabled:opacity-50 dark:text-amber-300"
                              >
                                Reject
                              </button>
                            ) : null}
                            {r.can_edit ? (
                              <button
                                type="button"
                                disabled={rowBusyId === r.id}
                                onClick={() => startEditing(r.id, r.content)}
                                className="text-slate-600 hover:text-slate-900 disabled:opacity-50 dark:text-slate-300 dark:hover:text-white"
                              >
                                Edit
                              </button>
                            ) : null}
                            {r.can_delete ? (
                              <button
                                type="button"
                                disabled={rowBusyId === r.id}
                                onClick={() => remove(r.id, 'thread')}
                                className="text-red-600 hover:text-red-800 disabled:opacity-50 dark:text-red-400"
                              >
                                Delete
                              </button>
                            ) : null}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <ListPaginationBar
                placement="bottom"
                page={page}
                total={total}
                totalPages={totalPages}
                perPage={perPage}
                onPageChange={(p) => setPage(p)}
                disabled={list.loading}
              />
            </>
          )}
        </ListPanel>

        <Modal
          open={drawerThreadId !== null}
          title={detailRow ? `${detailRow.thread_type === 'qa' ? 'Q&A' : 'Discussion'} thread` : 'Thread'}
          description={
            detailRow
              ? `${detailRow.author.name || `User #${detailRow.author.id}`} · ${formatPostDate(detailRow.created_at)}`
              : undefined
          }
          size="lg"
          onClose={() => setDrawerThreadId(null)}
          footer={
            detailRow ? (
              <div className="flex flex-wrap items-center justify-end gap-2">
                <ButtonSecondary type="button" onClick={() => setDrawerThreadId(null)}>
                  Close
                </ButtonSecondary>
              </div>
            ) : null
          }
        >
          {detail.loading && !detailRow ? (
            <div className="p-6 text-center text-sm text-slate-500 dark:text-slate-400">Loading thread…</div>
          ) : detail.error ? (
            <ApiErrorPanel
              error={detail.error}
              title="Could not load this thread"
              onRetry={() => detail.refetch()}
            />
          ) : detailRow ? (
            <div className="space-y-6">
              <section className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                <header className="mb-3 flex flex-wrap items-center gap-2 text-xs">
                  <TypeTag type={detailRow.thread_type} />
                  <StatusPill status={detailRow.status} />
                  <span className="text-slate-500 dark:text-slate-400">
                    {detailRow.course.title || `Course #${detailRow.course.id}`}
                    {' · '}
                    {detailRow.content_ref.title || `#${detailRow.content_ref.id}`}
                  </span>
                </header>
                <div className="mb-2 flex items-center justify-between gap-2 text-sm">
                  <div>
                    <div className="font-semibold text-slate-900 dark:text-white">
                      {detailRow.author.name || `User #${detailRow.author.id}`}
                    </div>
                    {detailRow.author.email ? (
                      <div className="text-xs text-slate-500 dark:text-slate-400">{detailRow.author.email}</div>
                    ) : null}
                  </div>
                  <div className="text-xs text-slate-500 dark:text-slate-400">
                    {formatPostDate(detailRow.created_at)}
                  </div>
                </div>
                {editingId === detailRow.id ? (
                  <EditingArea
                    value={editingDraft}
                    onChange={setEditingDraft}
                    busy={editingBusy}
                    onCancel={cancelEditing}
                    onSave={saveEditing}
                  />
                ) : (
                  <p className="whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                    {detailRow.content}
                  </p>
                )}
                <div className="mt-3 flex flex-wrap gap-3 text-xs font-medium">
                  {detailRow.is_pending && detailRow.can_moderate ? (
                    <>
                      <button
                        type="button"
                        disabled={rowBusyId === detailRow.id}
                        onClick={() => approve(detailRow.id)}
                        className="text-emerald-700 hover:text-emerald-900 disabled:opacity-50 dark:text-emerald-300"
                      >
                        Approve
                      </button>
                      <button
                        type="button"
                        disabled={rowBusyId === detailRow.id}
                        onClick={() => reject(detailRow.id)}
                        className="text-amber-700 hover:text-amber-900 disabled:opacity-50 dark:text-amber-300"
                      >
                        Reject
                      </button>
                    </>
                  ) : null}
                  {detailRow.can_edit && editingId !== detailRow.id ? (
                    <button
                      type="button"
                      onClick={() => startEditing(detailRow.id, detailRow.content)}
                      className="text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                    >
                      Edit
                    </button>
                  ) : null}
                  {detailRow.can_delete ? (
                    <button
                      type="button"
                      disabled={rowBusyId === detailRow.id}
                      onClick={() => remove(detailRow.id, 'thread')}
                      className="text-red-600 hover:text-red-800 disabled:opacity-50 dark:text-red-400"
                    >
                      Delete thread
                    </button>
                  ) : null}
                </div>
              </section>

              <section className="space-y-3">
                <h4 className="text-sm font-semibold text-slate-900 dark:text-white">
                  Replies ({detailRow.replies.length})
                </h4>
                {detailRow.replies.length === 0 ? (
                  <p className="text-sm text-slate-500 dark:text-slate-400">No replies yet.</p>
                ) : (
                  detailRow.replies.map((reply) => (
                    <div
                      key={reply.id}
                      className="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/40"
                    >
                      <div className="mb-2 flex items-center justify-between gap-2 text-xs">
                        <div>
                          <span className="font-semibold text-slate-900 dark:text-white">
                            {reply.author.name || `User #${reply.author.id}`}
                          </span>
                          {reply.author.email ? (
                            <span className="ml-2 text-slate-500 dark:text-slate-400">{reply.author.email}</span>
                          ) : null}
                        </div>
                        <div className="flex items-center gap-2">
                          <StatusPill status={reply.status} />
                          <span className="text-slate-500 dark:text-slate-400">
                            {formatPostDate(reply.created_at)}
                          </span>
                        </div>
                      </div>
                      {editingId === reply.id ? (
                        <EditingArea
                          value={editingDraft}
                          onChange={setEditingDraft}
                          busy={editingBusy}
                          onCancel={cancelEditing}
                          onSave={saveEditing}
                        />
                      ) : (
                        <p className="whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                          {reply.content}
                        </p>
                      )}
                      {(reply.can_edit || reply.can_delete) && editingId !== reply.id ? (
                        <div className="mt-2 flex flex-wrap gap-3 text-xs font-medium">
                          {reply.can_edit ? (
                            <button
                              type="button"
                              onClick={() => startEditing(reply.id, reply.content)}
                              className="text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                            >
                              Edit
                            </button>
                          ) : null}
                          {reply.can_delete ? (
                            <button
                              type="button"
                              disabled={rowBusyId === reply.id}
                              onClick={() => remove(reply.id, 'reply')}
                              className="text-red-600 hover:text-red-800 disabled:opacity-50 dark:text-red-400"
                            >
                              Delete
                            </button>
                          ) : null}
                        </div>
                      ) : null}
                    </div>
                  ))
                )}
              </section>

              {detailRow.can_moderate ? (
                <section className="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                  <h4 className="mb-2 text-sm font-semibold text-slate-900 dark:text-white">Reply as instructor</h4>
                  <textarea
                    rows={3}
                    value={replyDraft}
                    onChange={(e) => setReplyDraft(e.target.value)}
                    placeholder="Type your reply…"
                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                  />
                  <div className="mt-2 flex justify-end">
                    <ButtonPrimary
                      type="button"
                      disabled={replyBusy || replyDraft.trim() === ''}
                      onClick={() => void submitReply()}
                    >
                      {replyBusy ? 'Posting…' : 'Post reply'}
                    </ButtonPrimary>
                  </div>
                </section>
              ) : null}
            </div>
          ) : null}
        </Modal>

        {editingId !== null && drawerThreadId === null ? (
          <Modal
            open
            title="Edit content"
            size="md"
            onClose={cancelEditing}
            footer={
              <div className="flex justify-end gap-2">
                <ButtonSecondary type="button" onClick={cancelEditing} disabled={editingBusy}>
                  Cancel
                </ButtonSecondary>
                <ButtonPrimary type="button" onClick={() => void saveEditing()} disabled={editingBusy || editingDraft.trim() === ''}>
                  {editingBusy ? 'Saving…' : 'Save'}
                </ButtonPrimary>
              </div>
            }
          >
            <textarea
              rows={6}
              value={editingDraft}
              onChange={(e) => setEditingDraft(e.target.value)}
              className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
            />
          </Modal>
        ) : null}
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}

function SummaryTile({ label, value, accent }: { label: string; value: number; accent?: 'amber' }) {
  const accentCls =
    accent === 'amber'
      ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100'
      : 'border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-white';
  return (
    <div className={`rounded-xl border px-4 py-3 shadow-sm ${accentCls}`}>
      <div className="text-xs font-semibold uppercase tracking-wide opacity-70">{label}</div>
      <div className="mt-1 text-2xl font-semibold tabular-nums">{value.toLocaleString()}</div>
    </div>
  );
}

function EditingArea({
  value,
  onChange,
  busy,
  onCancel,
  onSave,
}: {
  value: string;
  onChange: (v: string) => void;
  busy: boolean;
  onCancel: () => void;
  onSave: () => Promise<void> | void;
}) {
  return (
    <div className="space-y-2">
      <textarea
        rows={4}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
      />
      <div className="flex justify-end gap-2">
        <ButtonSecondary type="button" onClick={onCancel} disabled={busy}>
          Cancel
        </ButtonSecondary>
        <ButtonPrimary
          type="button"
          onClick={() => void onSave()}
          disabled={busy || value.trim() === ''}
        >
          {busy ? 'Saving…' : 'Save'}
        </ButtonPrimary>
      </div>
    </div>
  );
}
