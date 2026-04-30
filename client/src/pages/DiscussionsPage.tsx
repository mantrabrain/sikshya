import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPaginationBar, DEFAULT_LIST_PER_PAGE } from '../components/shared/list/ListPaginationBar';
import { BulkActionsBar } from '../components/shared/list/BulkActionsBar';
import { RowActionsMenu, type RowActionItem } from '../components/shared/list/RowActionsMenu';
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

type ThreadStatus = 'pending' | 'approved' | 'spam' | 'trash';
type ThreadType = 'discussion' | 'qa';
/** Learner thread triage hint for admins (moderation vs staff reply vs done). */
type ThreadAttention = 'moderate' | 'reply' | 'answered' | 'spam';

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
  attention?: ThreadAttention;
  needs_staff_reply?: boolean;
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

type DiscussionsBulkResponse = {
  success: boolean;
  message?: string;
  data?: {
    processed: number;
    skipped: Array<{ id: number; code?: string; message: string }>;
    action: string;
  };
};

type StatusFilter = 'pending' | 'approved' | 'spam' | 'trash' | 'all';
type ContentTypeFilter = '' | 'lesson' | 'quiz';
type ThreadTypeFilter = '' | ThreadType;
type AttentionFilter = 'all' | ThreadAttention;

const STATUS_OPTIONS: Array<{ value: StatusFilter; label: string }> = [
  { value: 'all', label: 'All statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'spam', label: 'Spam' },
  { value: 'trash', label: 'Rejected (trash)' },
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

/** When the REST payload omits `attention` (older Pro builds), derive a safe fallback. */
function resolveAttention(row: Pick<ThreadRow, 'attention' | 'status' | 'is_pending'>): ThreadAttention {
  if (row.attention) return row.attention;
  if (row.status === 'spam' || row.status === 'trash') return 'spam';
  if (row.is_pending) return 'moderate';
  return 'answered';
}

function buildThreadModerationMenuItems(
  row: ThreadRow,
  ctx: {
    rowBusyId: number | null;
    onApprove: () => void | Promise<void>;
    onMarkSpam: () => void | Promise<void>;
    onTrash: () => void | Promise<void>;
    onEdit: () => void;
    onDelete: () => void | Promise<void>;
  }
): RowActionItem[] {
  const busy = ctx.rowBusyId === row.id;
  const items: RowActionItem[] = [];

  if (row.can_moderate && row.is_pending) {
    items.push({ key: 'approve', label: 'Approve', disabled: busy, onClick: ctx.onApprove });
  }
  if (row.can_moderate && row.status !== 'spam' && row.status !== 'trash') {
    items.push({
      key: 'spam',
      label: 'Mark as spam',
      danger: true,
      disabled: busy,
      onClick: ctx.onMarkSpam,
    });
    items.push({
      key: 'trash',
      label: 'Reject (move to trash)',
      danger: true,
      disabled: busy,
      onClick: ctx.onTrash,
    });
  }
  if (row.can_edit) {
    items.push({ key: 'edit', label: 'Edit', disabled: busy, onClick: ctx.onEdit });
  }
  if (row.can_delete) {
    items.push({
      key: 'delete',
      label: 'Delete permanently',
      danger: true,
      disabled: busy,
      onClick: ctx.onDelete,
    });
  }

  return items;
}

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
  if (status === 'spam') {
    return (
      <span className="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-900/40 dark:text-rose-200">
        Spam
      </span>
    );
  }
  return (
    <span
      className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-800 dark:bg-slate-700 dark:text-slate-200"
      title="Moved to trash (moderator rejection, not spam)."
    >
      Rejected
    </span>
  );
}

function AttentionPill({ attention }: { attention: ThreadAttention }) {
  if (attention === 'moderate') {
    return (
      <span
        className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900 dark:bg-amber-900/35 dark:text-amber-100"
        title="Approve or reject the thread before it is fully visible to learners."
      >
        Moderation first
      </span>
    );
  }
  if (attention === 'reply') {
    return (
      <span
        className="inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-xs font-semibold text-violet-900 dark:bg-violet-900/45 dark:text-violet-100"
        title="The latest approved reply is not from course staff—or there are no replies yet. Open and post an instructor/admin answer."
      >
        Reply needed
      </span>
    );
  }
  if (attention === 'spam') {
    return (
      <span
        className="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200"
        title="This thread is spam or was moved to trash and is hidden from learners."
      >
        Rejected thread
      </span>
    );
  }
  return (
    <span
      className="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-900 dark:bg-emerald-900/35 dark:text-emerald-100"
      title="The most recent approved reply is from someone who can moderate this course (or there is nothing else to answer right now)."
    >
      Staff up to date
    </span>
  );
}

const ATTENTION_OPTIONS: Array<{ value: AttentionFilter; label: string }> = [
  { value: 'all', label: 'Any attention state' },
  { value: 'moderate', label: 'Moderation first' },
  { value: 'reply', label: 'Needs instructor reply' },
  { value: 'answered', label: 'Staff up to date' },
  { value: 'spam', label: 'Rejected only' },
];

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

const DISCUSSIONS_BULK_OPTIONS = [
  { value: 'bulk_approve', label: 'Approve' },
  { value: 'bulk_spam', label: 'Mark as spam' },
  { value: 'bulk_trash', label: 'Reject (move to trash)' },
  { value: 'bulk_delete', label: 'Delete permanently' },
] as const;

export function DiscussionsPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const { confirm, alert } = useSikshyaDialog();

  const featureOk = isFeatureEnabled(config, 'community_discussions');
  const addon = useAddonEnabled('community_discussions');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const gateOpen = mode === 'full';

  const [filterCourseId, setFilterCourseId] = useState(0);
  const [filterContentType, setFilterContentType] = useState<ContentTypeFilter>('');
  const [filterThreadType, setFilterThreadType] = useState<ThreadTypeFilter>('');
  const [filterAttention, setFilterAttention] = useState<AttentionFilter>('all');
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
  /** Narrow drawer strip (thread stays selected; expands again with ◀). */
  const [detailPanelCollapsed, setDetailPanelCollapsed] = useState(false);
  /** Right drawer slide-in (enter animation). */
  const [discussionDrawerEntered, setDiscussionDrawerEntered] = useState(false);

  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [bulkActionValue, setBulkActionValue] = useState('');
  const [bulkBusy, setBulkBusy] = useState(false);
  const bulkSelectAllRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (drawerThreadId === null) return;
    setDetailPanelCollapsed(false);
  }, [drawerThreadId]);

  useEffect(() => {
    if (drawerThreadId === null || !gateOpen) {
      setDiscussionDrawerEntered(false);
      return undefined;
    }
    setDiscussionDrawerEntered(false);
    const tid = window.setTimeout(() => setDiscussionDrawerEntered(true), 15);
    return () => window.clearTimeout(tid);
  }, [drawerThreadId, gateOpen]);

  useEffect(() => {
    if (drawerThreadId === null || !gateOpen || typeof document === 'undefined') {
      return undefined;
    }
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = prev;
    };
  }, [drawerThreadId, gateOpen]);

  useEffect(() => {
    if (drawerThreadId === null) return undefined;
    const onKey = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return;
      e.preventDefault();
      setDrawerThreadId(null);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [drawerThreadId]);

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
        attention: filterAttention,
        search: search || undefined,
        page,
        per_page: perPage,
      })
    );
  }, [gateOpen, filterCourseId, filterContentType, filterThreadType, filterAttention, status, search, page, perPage]);

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
    filterAttention,
    status,
    search,
    page,
    perPage,
  ]);
  const summary = useAsyncData(summaryLoader, [gateOpen]);

  const rows = list.data?.data.items ?? [];
  const selectableOnPage = useMemo(() => rows.filter((r) => r.can_moderate).map((r) => r.id), [rows]);

  useEffect(() => {
    setSelectedIds(new Set());
    setBulkActionValue('');
  }, [page, filterCourseId, filterContentType, filterThreadType, filterAttention, status, search]);

  useEffect(() => {
    const el = bulkSelectAllRef.current;
    if (!el) return;
    const n = selectableOnPage.filter((id) => selectedIds.has(id)).length;
    el.indeterminate = n > 0 && n < selectableOnPage.length;
  }, [selectableOnPage, selectedIds]);

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
  const detailRowRaw = detail.data?.data ?? null;
  const detailRow =
    drawerThreadId !== null && detailRowRaw && detailRowRaw.id === drawerThreadId ? detailRowRaw : null;
  const detailPanelLoading =
    drawerThreadId !== null &&
    Boolean(detail.loading) &&
    detailRow === null &&
    detail.error == null;

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
    setFilterAttention('all');
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

  const markSpam = async (id: number) => {
    const ok = await confirm({
      title: 'Mark as spam?',
      message:
        'Hides this thread from learners and sends it to the WordPress spam queue. Use this for abusive or junk posts.',
      variant: 'danger',
      confirmLabel: 'Mark as spam',
    });
    if (!ok) return;
    setRowBusyId(id);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.discussionMarkSpam(id), {});
      refreshAll();
    } finally {
      setRowBusyId(null);
    }
  };

  const toggleSelectThread = useCallback((id: number, on: boolean) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (on) next.add(id);
      else next.delete(id);
      return next;
    });
  }, []);

  const toggleSelectAllOnPage = useCallback(() => {
    const allOn =
      selectableOnPage.length > 0 && selectableOnPage.every((id) => selectedIds.has(id));
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (allOn) {
        selectableOnPage.forEach((id) => next.delete(id));
      } else {
        selectableOnPage.forEach((id) => next.add(id));
      }
      return next;
    });
  }, [selectableOnPage, selectedIds]);

  const applyBulkThreads = async () => {
    if (!gateOpen || selectedIds.size === 0 || bulkActionValue === '') return;

    const actionMap: Record<string, 'approve' | 'spam' | 'trash' | 'delete'> = {
      bulk_approve: 'approve',
      bulk_spam: 'spam',
      bulk_trash: 'trash',
      bulk_delete: 'delete',
    };
    const action = actionMap[bulkActionValue];
    if (!action) return;

    const ids = [...selectedIds];
    const countLabel = ids.length === 1 ? '1 thread' : `${ids.length} threads`;

    if (action === 'delete') {
      const ok = await confirm({
        title: 'Delete selected threads?',
        message: `This permanently deletes ${countLabel} and their replies.`,
        variant: 'danger',
        confirmLabel: 'Delete',
      });
      if (!ok) return;
    } else if (action === 'spam') {
      const ok = await confirm({
        title: 'Mark selected as spam?',
        message: `${countLabel} will be hidden from learners and sent to the spam queue.`,
        variant: 'danger',
        confirmLabel: 'Mark as spam',
      });
      if (!ok) return;
    } else if (action === 'trash') {
      const ok = await confirm({
        title: 'Reject selected threads?',
        message: `${countLabel} will move to trash (not spam). Learners will no longer see them.`,
        variant: 'danger',
        confirmLabel: 'Move to trash',
      });
      if (!ok) return;
    }

    setBulkBusy(true);
    try {
      const res = await getSikshyaApi().post<DiscussionsBulkResponse>(SIKSHYA_ENDPOINTS.admin.discussionsBulk, {
        action,
        ids,
      });
      if (!res.success) {
        await alert({
          title: 'Bulk action failed',
          message: typeof res.message === 'string' ? res.message : 'Request was not successful.',
        });
        return;
      }
      refreshAll();
      setSelectedIds(new Set());
      setBulkActionValue('');
      const skipped = res.data?.skipped?.length ?? 0;
      if (skipped > 0) {
        await alert({
          title: 'Some threads were skipped',
          message:
            skipped === ids.length
              ? res.message ??
                'No threads were updated. Your selection may include threads you cannot moderate or invalid ids.'
              : `${res.data?.processed ?? 0} updated. ${skipped} skipped (not found or no permission).`,
        });
      }
    } finally {
      setBulkBusy(false);
    }
  };

  const trashThread = async (id: number) => {
    const ok = await confirm({
      title: 'Reject this thread?',
      message:
        'Moves the thread to trash (moderator dismissal) without labeling it as spam. Learners no longer see it.',
      variant: 'danger',
      confirmLabel: 'Move to trash',
    });
    if (!ok) return;
    setRowBusyId(id);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.discussionTrash(id), {});
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

        <div className="mb-4">
          <ListPanel className="">
          <div className="space-y-4 border-b border-slate-100 p-4 dark:border-slate-800">
            <div className="grid gap-3 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
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
              <label className="block text-sm text-slate-600 dark:text-slate-400">
                Instructor inbox
                <select
                  value={filterAttention}
                  onChange={(e) => {
                    setFilterAttention(e.target.value as AttentionFilter);
                    setPage(1);
                  }}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                  title="Triage threads by whether you should moderate or reply as staff."
                >
                  {ATTENTION_OPTIONS.map((o) => (
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
            {filterAttention !== 'all' ? (
              <p className="mx-4 mb-4 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                Inbox filters look at up to{' '}
                <span className="font-semibold text-slate-600 dark:text-slate-300">1,500</span> newest threads matching
                your filters, then paginate.&nbsp;
                Narrow by course if you cannot find an older thread.
              </p>
            ) : null}
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
              <div className="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                <BulkActionsBar
                  disabled={list.loading || selectableOnPage.length === 0}
                  selectedCount={selectedIds.size}
                  value={bulkActionValue}
                  onChange={setBulkActionValue}
                  onApply={() => void applyBulkThreads()}
                  applyBusy={bulkBusy}
                  trashMode={false}
                  customOptions={[...DISCUSSIONS_BULK_OPTIONS]}
                  selectId="sikshya-discussions-bulk"
                />
                {selectableOnPage.length === 0 ? (
                  <span className="text-xs text-slate-500 dark:text-slate-400">
                    No threads on this page can be moderated by you.
                  </span>
                ) : null}
              </div>
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                  <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                    <tr>
                      <th className="w-10 px-3 py-3.5" scope="col">
                        <span className="sr-only">Select</span>
                        <input
                          ref={bulkSelectAllRef}
                          type="checkbox"
                          className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900"
                          checked={
                            selectableOnPage.length > 0 &&
                            selectableOnPage.every((id) => selectedIds.has(id))
                          }
                          disabled={selectableOnPage.length === 0 || list.loading || bulkBusy}
                          onChange={() => toggleSelectAllOnPage()}
                          aria-label="Select all threads you can moderate on this page"
                        />
                      </th>
                      <th className="px-5 py-3.5">Author</th>
                      <th className="px-5 py-3.5">Course</th>
                      <th className="px-5 py-3.5">Content</th>
                      <th className="px-5 py-3.5">What to do</th>
                      <th className="px-5 py-3.5">Thread</th>
                      <th className="px-5 py-3.5">Replies</th>
                      <th className="px-5 py-3.5">Posted</th>
                      <th className="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {rows.map((r) => {
                      const attention = resolveAttention(r);
                      const rowTint =
                        attention === 'moderate'
                          ? 'bg-amber-50/95 dark:bg-amber-950/30'
                          : attention === 'reply'
                            ? 'bg-violet-50/95 dark:bg-violet-950/25'
                            : attention === 'spam'
                              ? 'bg-slate-100/90 dark:bg-slate-900/85'
                              : '';

                      return (
                      <tr
                        key={r.id}
                        className={`dark:bg-slate-900 ${rowTint || 'bg-white'} ${
                          drawerThreadId === r.id ? 'bg-sky-50/90 ring-1 ring-inset ring-sky-200/80 dark:bg-sky-950/35 dark:ring-sky-800/80' : ''
                        }`}
                      >
                        <td className="px-3 py-3.5 align-top">
                          {r.can_moderate ? (
                            <input
                              type="checkbox"
                              className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900"
                              checked={selectedIds.has(r.id)}
                              disabled={list.loading || bulkBusy}
                              onChange={(e) => toggleSelectThread(r.id, e.target.checked)}
                              aria-label={`Select thread #${r.id}`}
                            />
                          ) : (
                            <span className="inline-block w-4" aria-hidden />
                          )}
                        </td>
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
                        <td className="min-w-[10.5rem] max-w-[14rem] px-5 py-3.5 align-top">
                          <div className="flex flex-col gap-1">
                            <AttentionPill attention={attention} />
                            <span className="text-[11px] leading-snug text-slate-500 dark:text-slate-400">
                              {attention === 'moderate'
                                ? 'Approve or reject first.'
                                : attention === 'reply'
                                  ? r.thread_type === 'qa'
                                    ? 'Post an instructor/admin answer.'
                                    : 'Staff should respond when ready.'
                                  : attention === 'spam'
                                    ? 'No further moderation needed unless you reopen.'
                                    : 'Latest reply is from staff (or no reply expected).'}
                            </span>
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
                          <div className="inline-flex items-center justify-end gap-2">
                            <button
                              type="button"
                              className="text-sm font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400"
                              onClick={() => setDrawerThreadId(r.id)}
                            >
                              {attention === 'reply' ? 'Open & reply' : 'View'}
                            </button>
                            <RowActionsMenu
                              ariaLabel={`Thread actions for #${r.id}`}
                              items={buildThreadModerationMenuItems(r, {
                                rowBusyId,
                                onApprove: () => void approve(r.id),
                                onMarkSpam: () => void markSpam(r.id),
                                onTrash: () => void trashThread(r.id),
                                onEdit: () => startEditing(r.id, r.content),
                                onDelete: () => void remove(r.id, 'thread'),
                              })}
                            />
                          </div>
                        </td>
                      </tr>
                      );
                    })}
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

        {gateOpen && drawerThreadId !== null && typeof document !== 'undefined'
          ? createPortal(
              <div
                className="fixed inset-0 z-[100082]"
                role="presentation"
              >
                <button
                  type="button"
                  className={`absolute inset-0 bg-slate-900/45 backdrop-blur-[2px] transition-opacity duration-300 ease-out dark:bg-slate-950/55 ${
                    discussionDrawerEntered ? 'opacity-100' : 'opacity-0'
                  }`}
                  aria-label="Close thread panel"
                  onClick={() => setDrawerThreadId(null)}
                />
                <aside
                  className={`absolute inset-y-0 right-0 z-10 flex flex-col overflow-hidden border-l border-slate-200/95 bg-white shadow-[-16px_0_48px_rgba(15,23,42,0.14)] transition-[transform,width] duration-300 ease-[cubic-bezier(0.33,1,0.68,1)] dark:border-slate-700 dark:bg-slate-950 dark:shadow-[-16px_0_48px_rgba(0,0,0,0.35)] ${
                    detailPanelCollapsed ? 'w-[3.35rem]' : 'w-[min(100vw,448px)]'
                  } ${discussionDrawerEntered ? 'translate-x-0' : 'translate-x-full'}`}
                  role="dialog"
                  aria-modal="true"
                  aria-labelledby="sikshya-disc-drawer-title"
                >
                  <div
                    id="sikshya-disc-drawer-title"
                    className="sr-only"
                  >
                    {detailRow ? `${detailRow.thread_type === 'qa' ? 'Q&A' : 'Discussion'} thread` : 'Thread moderation'}
                  </div>
                  <ModerationThreadPanel
                    collapsed={detailPanelCollapsed}
                    onToggleCollapsed={() => setDetailPanelCollapsed((v) => !v)}
                    collapsibleRail
                    detailLoading={detailPanelLoading}
                    detailError={detail.error}
                    onRetryDetail={() => detail.refetch()}
                    onClose={() => setDrawerThreadId(null)}
                    detailRow={detailRow}
                    editingId={editingId}
                    editingDraft={editingDraft}
                    editingBusy={editingBusy}
                    onEditingChange={setEditingDraft}
                    onCancelEditing={cancelEditing}
                    onSaveEditing={() => void saveEditing()}
                    onStartEditing={startEditing}
                    rowBusyId={rowBusyId}
                    approve={approve}
                    markSpam={markSpam}
                    trashThread={trashThread}
                    remove={remove}
                    replyDraft={replyDraft}
                    replyBusy={replyBusy}
                    onReplyDraftChange={setReplyDraft}
                    submitReply={() => void submitReply()}
                  />
                </aside>
              </div>,
              document.body
            )
          : null}
        </div>

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

function ModerationThreadPanel(props: {
  collapsed: boolean;
  onToggleCollapsed: () => void;
  collapsibleRail: boolean;
  detailLoading: boolean;
  detailError: unknown;
  onRetryDetail: () => void;
  onClose: () => void;
  detailRow: ThreadDetail | null;
  editingId: number | null;
  editingDraft: string;
  editingBusy: boolean;
  onEditingChange: (value: string) => void;
  onCancelEditing: () => void;
  onSaveEditing: () => void | Promise<void>;
  onStartEditing: (id: number, current: string) => void;
  rowBusyId: number | null;
  approve: (id: number) => void | Promise<void>;
  markSpam: (id: number) => void | Promise<void>;
  trashThread: (id: number) => void | Promise<void>;
  remove: (id: number, kind: 'thread' | 'reply') => void | Promise<void>;
  replyDraft: string;
  replyBusy: boolean;
  onReplyDraftChange: (value: string) => void;
  submitReply: () => void | Promise<void>;
}) {
  const {
    collapsed,
    onToggleCollapsed,
    collapsibleRail,
    detailLoading,
    detailError,
    onRetryDetail,
    onClose,
    detailRow,
    editingId,
    editingDraft,
    editingBusy,
    onEditingChange,
    onCancelEditing,
    onSaveEditing,
    onStartEditing,
    rowBusyId,
    approve,
    markSpam,
    trashThread,
    remove,
    replyDraft,
    replyBusy,
    onReplyDraftChange,
    submitReply,
  } = props;

  if (collapsed && collapsibleRail) {
    return (
      <div className="flex h-full min-h-0 w-full flex-col items-center gap-4 border-transparent bg-gradient-to-b from-[#f8fafc] to-white py-5 dark:from-slate-900 dark:to-slate-950">
        <button
          type="button"
          aria-label="Expand thread panel"
          className="rounded-lg p-2 text-slate-600 hover:bg-slate-200/80 dark:text-slate-300 dark:hover:bg-slate-800"
          onClick={onToggleCollapsed}
        >
          <span aria-hidden className="block text-lg leading-none">
            ◀
          </span>
        </button>
        <div className="flex flex-1 items-center justify-center px-1" aria-hidden>
          <span
            className="text-[10px] font-extrabold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500"
            style={{ writingMode: 'vertical-rl', textOrientation: 'mixed' }}
          >
            Thread
          </span>
        </div>
        <button
          type="button"
          aria-label="Close thread panel"
          className="rounded-lg p-2 text-slate-500 hover:bg-slate-200/80 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-white"
          onClick={onClose}
        >
          <span aria-hidden className="block text-lg leading-none">
            ×
          </span>
        </button>
      </div>
    );
  }

  return (
    <div className="flex h-full max-h-[100dvh] min-h-0 w-full flex-1 flex-col">
      <header className="shrink-0 border-b border-slate-200/80 bg-white/90 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/80">
        <div className="flex items-start gap-3">
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="text-base font-semibold text-slate-900 dark:text-white">
                {detailRow ? (detailRow.thread_type === 'qa' ? 'Q&A' : 'Discussion') : 'Thread'}
              </h2>
              {detailRow ? (
                <>
                  <TypeTag type={detailRow.thread_type} />
                  <StatusPill status={detailRow.status} />
                  <AttentionPill attention={resolveAttention(detailRow)} />
                </>
              ) : null}
            </div>
            <p className="mt-1 text-xs leading-snug text-slate-500 dark:text-slate-400">
              {detailRow
                ? `${detailRow.author.name || `User #${detailRow.author.id}`} · ${formatPostDate(detailRow.created_at)}`
                : ' '}
              {detailRow && detailRow.course.title ? (
                <>
                  {' · '}
                  <span className="break-words">
                    {detailRow.course.title || `Course #${detailRow.course.id}`}
                    {' · '}
                    {detailRow.content_ref.title || `#${detailRow.content_ref.id}`}
                  </span>
                </>
              ) : null}
            </p>
          </div>
          <div className="flex shrink-0 items-center gap-1">
            {detailRow ? (
              <RowActionsMenu
                ariaLabel="Thread actions"
                items={buildThreadModerationMenuItems(detailRow, {
                  rowBusyId,
                  onApprove: () => void approve(detailRow.id),
                  onMarkSpam: () => void markSpam(detailRow.id),
                  onTrash: () => void trashThread(detailRow.id),
                  onEdit: () => onStartEditing(detailRow.id, detailRow.content),
                  onDelete: () => void remove(detailRow.id, 'thread'),
                })}
              />
            ) : null}
            {collapsibleRail ? (
              <button
                type="button"
                aria-label="Collapse thread panel"
                className="inline-flex rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-white"
                onClick={onToggleCollapsed}
              >
                <span aria-hidden className="block text-lg leading-none">
                  ▸
                </span>
              </button>
            ) : null}
            <button
              type="button"
              aria-label="Close thread panel"
              className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-white"
              onClick={onClose}
            >
              <span aria-hidden className="block text-xl leading-none">
                ×
              </span>
            </button>
          </div>
        </div>
      </header>

      <div
        className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-3 pt-4"
        style={{
          scrollbarGutter: 'stable',
          background:
            'radial-gradient(115% 65% at 12% -4%, rgba(37,99,235,0.065), transparent 52%), linear-gradient(180deg, rgba(248,250,252,0.92) 0%, #eef2fb 52%, #e8ecf6 100%)',
        }}
      >
        {detailLoading ? (
          <div className="py-16 text-center text-sm text-slate-500 dark:text-slate-400">Loading thread…</div>
        ) : detailError ? (
          <ApiErrorPanel
            error={detailError}
            title="Could not load this thread"
            onRetry={onRetryDetail}
          />
        ) : detailRow ? (
          <div className="space-y-4">
            {/* Root message — learn-style bubble */}
            <section
              className="rounded-[17px] border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200/60 dark:border-slate-600/60 dark:bg-slate-900/85 dark:ring-white/5"
              style={{ borderLeftWidth: 3, borderLeftColor: '#2563eb' }}
            >
              <div className="mb-2 flex flex-wrap items-baseline justify-between gap-2 text-xs">
                <span className="font-bold text-slate-900 dark:text-white">
                  {detailRow.author.name || `User #${detailRow.author.id}`}
                </span>
                <time className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                  {formatPostDate(detailRow.created_at)}
                </time>
              </div>
              {detailRow.author.email ? (
                <div className="mb-2 text-[11px] text-slate-500 dark:text-slate-400">{detailRow.author.email}</div>
              ) : null}
              {editingId === detailRow.id ? (
                <EditingArea
                  value={editingDraft}
                  onChange={onEditingChange}
                  busy={editingBusy}
                  onCancel={onCancelEditing}
                  onSave={onSaveEditing}
                />
              ) : (
                <p className="whitespace-pre-line text-[13px] leading-relaxed text-slate-800 dark:text-slate-100">
                  {detailRow.content}
                </p>
              )}
            </section>

            {/* Replies */}
            <section className="space-y-2">
              <div className="flex items-center justify-between px-0.5">
                <span className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">
                  Replies ({detailRow.replies.length})
                </span>
              </div>
              {detailRow.replies.length === 0 ? (
                <p className="rounded-xl border border-dashed border-slate-300/70 bg-white/60 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-400">
                  No replies yet.
                </p>
              ) : (
                detailRow.replies.map((reply) => (
                  <div
                    key={reply.id}
                    className="flex gap-3 rounded-xl border border-slate-200/80 bg-white/95 px-3 py-2.5 shadow-sm dark:border-slate-700/70 dark:bg-slate-900/70"
                  >
                    <div
                      className="mt-1 h-full w-0.5 shrink-0 self-stretch rounded-full bg-gradient-to-b from-slate-300 to-slate-200/40 dark:from-slate-600 dark:to-slate-800/60"
                      aria-hidden
                    />
                    <div className="min-w-0 flex-1">
                      <div className="mb-2 flex flex-wrap items-center gap-2 text-[11px]">
                        <span className="font-bold text-slate-800 dark:text-slate-100">
                          {reply.author.name || `User #${reply.author.id}`}
                        </span>
                        {reply.author.email ? (
                          <span className="text-slate-500 dark:text-slate-400">{reply.author.email}</span>
                        ) : null}
                        <span className="ml-auto inline-flex flex-wrap items-center gap-2">
                          <StatusPill status={reply.status} />
                          <span className="tabular-nums text-slate-400 dark:text-slate-500">
                            {formatPostDate(reply.created_at)}
                          </span>
                        </span>
                      </div>
                      {editingId === reply.id ? (
                        <EditingArea
                          value={editingDraft}
                          onChange={onEditingChange}
                          busy={editingBusy}
                          onCancel={onCancelEditing}
                          onSave={onSaveEditing}
                        />
                      ) : (
                        <p className="whitespace-pre-line text-[13px] leading-relaxed text-slate-700 dark:text-slate-200">
                          {reply.content}
                        </p>
                      )}
                      {(reply.can_edit || reply.can_delete) && editingId !== reply.id ? (
                        <div className="mt-2 flex flex-wrap gap-3 border-t border-slate-100 pt-2 text-xs font-semibold dark:border-slate-700">
                          {reply.can_edit ? (
                            <button
                              type="button"
                              onClick={() => onStartEditing(reply.id, reply.content)}
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
                  </div>
                ))
              )}
            </section>
          </div>
        ) : (
          <div className="py-16 text-center text-sm text-slate-500 dark:text-slate-400">No thread data.</div>
        )}
      </div>

      {detailRow?.can_moderate ? (
        <footer className="shrink-0 border-t border-slate-200/90 bg-white/95 px-4 py-3 dark:border-slate-700 dark:bg-slate-950/95">
          <div className="rounded-[22px] border border-slate-200/80 bg-slate-50/90 p-2 dark:border-slate-600 dark:bg-slate-900/50">
            <label className="mb-2 block px-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Reply as instructor
            </label>
            <textarea
              rows={3}
              value={replyDraft}
              onChange={(e) => onReplyDraftChange(e.target.value)}
              placeholder="Type your reply…"
              className="mb-3 w-full rounded-[14px] border border-transparent bg-white px-3 py-2.5 text-sm text-slate-900 shadow-inner shadow-slate-200/80 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-950 dark:text-slate-100 dark:shadow-none dark:focus:ring-brand-400/25"
            />
            <div className="flex justify-end px-1 pb-1">
              <ButtonPrimary
                type="button"
                className="rounded-full px-5"
                disabled={replyBusy || replyDraft.trim() === ''}
                onClick={() => void submitReply()}
              >
                {replyBusy ? 'Posting…' : 'Post reply'}
              </ButtonPrimary>
            </div>
          </div>
        </footer>
      ) : null}
    </div>
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
