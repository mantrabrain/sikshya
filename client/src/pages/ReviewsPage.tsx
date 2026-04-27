import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary } from '../components/shared/buttons';
import { HorizontalEditorTabs } from '../components/shared/HorizontalEditorTabs';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { formatPostDate } from '../lib/formatPostDate';
import type { NavItem, SikshyaReactConfig } from '../types';

type ReviewRow = {
  id: number;
  user_id: number;
  course_id: number;
  rating: number;
  review_text: string;
  is_approved: boolean;
  author_name: string;
  author_email: string;
  course_title: string;
  created_at: string;
  created_at_label: string;
  edit_url: string;
  view_url: string;
  reported_count?: number;
  last_reported_at?: string;
  reply_text?: string;
  reply_user_id?: number;
  reply_created_at?: string;
};

type ListResponse = {
  success: boolean;
  data: {
    items: ReviewRow[];
    total: number;
    page: number;
    per_page: number;
    counts: { pending: number; approved: number; total: number };
  };
};

type StatusFilter = 'pending' | 'approved' | 'all';

const STATUS_TABS = [
  { id: 'pending', label: 'Pending' },
  { id: 'approved', label: 'Approved' },
  { id: 'all', label: 'All' },
] as const;

function StarDisplay({ value }: { value: number }) {
  const v = Math.max(0, Math.min(5, Math.round(value)));
  return (
    <span aria-label={`${v} out of 5`} className="inline-flex items-center gap-0.5 text-amber-500">
      {[1, 2, 3, 4, 5].map((n) => (
        <span key={n} className={n <= v ? '' : 'text-slate-300 dark:text-slate-600'}>
          {n <= v ? '★' : '☆'}
        </span>
      ))}
    </span>
  );
}

export function ReviewsPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const { confirm } = useSikshyaDialog();

  const featureOk = isFeatureEnabled(config, 'course_reviews');
  const addon = useAddonEnabled('course_reviews');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const gateOpen = mode === 'full';

  const [status, setStatus] = useState<StatusFilter>('pending');
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [page, setPage] = useState(1);
  const [rowBusyId, setRowBusyId] = useState<number | null>(null);
  const [replyDrafts, setReplyDrafts] = useState<Record<number, string>>({});

  const loader = useCallback(async () => {
    if (!gateOpen) {
      return { success: true, data: { items: [], total: 0, page: 1, per_page: 20, counts: { pending: 0, approved: 0, total: 0 } } } as ListResponse;
    }
    return getSikshyaApi().get<ListResponse>(
      SIKSHYA_ENDPOINTS.admin.reviews({
        status: status === 'all' ? undefined : status,
        search: search || undefined,
        page,
        per_page: 20,
      })
    );
  }, [gateOpen, status, search, page]);

  const { loading, data, error, refetch } = useAsyncData(loader, [gateOpen, status, search, page]);
  const rows = data?.data?.items ?? [];
  const counts = data?.data?.counts ?? { pending: 0, approved: 0, total: 0 };
  const totalPages = useMemo(() => {
    if (!data?.data) return 1;
    return Math.max(1, Math.ceil(data.data.total / Math.max(1, data.data.per_page)));
  }, [data]);

  const tabsWithBadges: { id: string; label: string }[] = STATUS_TABS.map((t) => ({
    id: t.id,
    label:
      t.id === 'pending'
        ? `Pending (${counts.pending})`
        : t.id === 'approved'
          ? `Approved (${counts.approved})`
          : `All (${counts.total})`,
  }));

  const approve = async (id: number) => {
    setRowBusyId(id);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.reviewApprove(id), {});
      refetch();
    } finally {
      setRowBusyId(null);
    }
  };

  const reject = async (id: number) => {
    setRowBusyId(id);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.reviewReject(id), {});
      refetch();
    } finally {
      setRowBusyId(null);
    }
  };

  const saveReply = async (id: number) => {
    const text = (replyDrafts[id] ?? '').trim();
    if (!text) return;
    setRowBusyId(id);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.reviewReply(id), { reply_text: text });
      refetch();
    } finally {
      setRowBusyId(null);
    }
  };

  const removeReply = async (id: number) => {
    const ok = await confirm({
      title: 'Remove reply?',
      message: 'This removes the public instructor/admin reply from the course page.',
      variant: 'danger',
      confirmLabel: 'Remove',
    });
    if (!ok) return;
    setRowBusyId(id);
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.admin.reviewReply(id));
      refetch();
    } finally {
      setRowBusyId(null);
    }
  };

  const remove = async (id: number) => {
    const ok = await confirm({
      title: 'Delete review?',
      message: 'This review will be permanently removed and the course rating recalculated.',
      variant: 'danger',
      confirmLabel: 'Delete',
    });
    if (!ok) return;
    setRowBusyId(id);
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.admin.reviewDelete(id));
      refetch();
    } finally {
      setRowBusyId(null);
    }
  };

  const onSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    setSearch(searchInput.trim());
  };

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle="Approve, unpublish, or delete learner reviews. Course averages update automatically."
      pageActions={
        gateOpen ? (
          <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
            Refresh
          </ButtonPrimary>
        ) : null
      }
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="course_reviews"
        config={config}
        featureTitle="Course reviews & ratings"
        featureDescription="Collect star ratings and written reviews on your course pages, with optional moderation — boosting social proof on catalog cards and learner trust."
        previewVariant="table"
        addonEnableTitle="Reviews moderation is not enabled"
        addonEnableDescription="Enable the Course reviews addon to register the public review form, the REST endpoints, and this moderation screen."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
      {error ? (
        <div className="mb-4">
          <ApiErrorPanel error={error} title="Could not load reviews" onRetry={() => refetch()} />
        </div>
      ) : null}

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <HorizontalEditorTabs
          tabs={tabsWithBadges}
          value={status}
          onChange={(id) => {
            setStatus(id as StatusFilter);
            setPage(1);
          }}
          ariaLabel="Review status"
        />

        <form onSubmit={onSearchSubmit} className="flex items-center gap-2">
          <input
            type="search"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search text, student, course…"
            className="w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
          />
          <ButtonPrimary type="submit">Search</ButtonPrimary>
          {search ? (
            <button
              type="button"
              className="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400"
              onClick={() => {
                setSearch('');
                setSearchInput('');
                setPage(1);
              }}
            >
              Clear
            </button>
          ) : null}
        </form>
      </div>

      <ListPanel>
        {loading ? (
          <div className="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>
        ) : rows.length === 0 ? (
          <ListEmptyState
            title={status === 'pending' ? 'Nothing waiting for moderation' : 'No reviews found'}
            description={
              status === 'pending'
                ? 'All caught up! New learner reviews will appear here when Approval mode is set to Manual.'
                : 'Try a different filter or clear your search.'
            }
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
              <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                <tr>
                  <th className="px-5 py-3.5">Submitted</th>
                  <th className="px-5 py-3.5">Student</th>
                  <th className="px-5 py-3.5">Course</th>
                  <th className="px-5 py-3.5">Rating</th>
                  <th className="px-5 py-3.5">Review</th>
                  <th className="px-5 py-3.5">Status</th>
                  <th className="px-5 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {rows.map((r) => (
                  <tr key={r.id} className="bg-white dark:bg-slate-900">
                    <td className="whitespace-nowrap px-5 py-3.5 text-slate-600 dark:text-slate-400">
                      <div>{formatPostDate(r.created_at)}</div>
                      <div className="text-xs text-slate-400">{r.created_at_label}</div>
                    </td>
                    <td className="px-5 py-3.5">
                      <div className="font-semibold text-slate-900 dark:text-white">
                        {r.author_name || `User #${r.user_id}`}
                      </div>
                      {r.author_email ? (
                        <div className="text-xs text-slate-500 dark:text-slate-400">{r.author_email}</div>
                      ) : null}
                    </td>
                    <td className="px-5 py-3.5">
                      {r.view_url ? (
                        <a
                          href={r.view_url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400"
                        >
                          {r.course_title || `#${r.course_id}`}
                        </a>
                      ) : (
                        <span>{r.course_title || `#${r.course_id}`}</span>
                      )}
                    </td>
                    <td className="whitespace-nowrap px-5 py-3.5">
                      {r.rating > 0 ? <StarDisplay value={r.rating} /> : <span className="text-slate-400">—</span>}
                    </td>
                    <td className="max-w-[420px] px-5 py-3.5 text-slate-700 dark:text-slate-300">
                      {r.review_text ? (
                        <p className="whitespace-pre-line text-sm leading-relaxed">
                          {r.review_text.length > 260 ? `${r.review_text.slice(0, 260)}…` : r.review_text}
                        </p>
                      ) : (
                        <span className="text-xs italic text-slate-400">(rating only)</span>
                      )}
                      <div className="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950">
                        <div className="flex items-center justify-between gap-2">
                          <div className="text-xs font-semibold text-slate-600 dark:text-slate-300">Official reply</div>
                          <div className="text-xs text-slate-500 dark:text-slate-400">
                            Reports: {Number(r.reported_count ?? 0).toLocaleString()}
                          </div>
                        </div>
                        <textarea
                          rows={2}
                          value={replyDrafts[r.id] ?? r.reply_text ?? ''}
                          onChange={(e) => setReplyDrafts((prev) => ({ ...prev, [r.id]: e.target.value }))}
                          placeholder="Write a short reply (shown publicly under the review)…"
                          className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        />
                        <div className="mt-2 flex items-center justify-end gap-2">
                          {r.reply_text ? (
                            <button
                              type="button"
                              className="text-xs font-semibold text-slate-600 hover:text-slate-800 disabled:opacity-50 dark:text-slate-300 dark:hover:text-white"
                              disabled={rowBusyId === r.id}
                              onClick={() => void removeReply(r.id)}
                            >
                              Remove reply
                            </button>
                          ) : null}
                          <ButtonPrimary
                            type="button"
                            disabled={rowBusyId === r.id || ((replyDrafts[r.id] ?? r.reply_text ?? '').trim() === '')}
                            onClick={() => void saveReply(r.id)}
                          >
                            Save reply
                          </ButtonPrimary>
                        </div>
                      </div>
                    </td>
                    <td className="px-5 py-3.5">
                      {r.is_approved ? (
                        <span className="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                          Approved
                        </span>
                      ) : (
                        <span className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                          Pending
                        </span>
                      )}
                    </td>
                    <td className="whitespace-nowrap px-5 py-3.5 text-right">
                      <div className="inline-flex items-center gap-3 text-sm font-medium">
                        {r.is_approved ? (
                          <button
                            type="button"
                            disabled={rowBusyId === r.id}
                            onClick={() => reject(r.id)}
                            className="text-amber-700 hover:text-amber-900 disabled:opacity-50 dark:text-amber-300"
                          >
                            Unpublish
                          </button>
                        ) : (
                          <button
                            type="button"
                            disabled={rowBusyId === r.id}
                            onClick={() => approve(r.id)}
                            className="text-emerald-700 hover:text-emerald-900 disabled:opacity-50 dark:text-emerald-300"
                          >
                            Approve
                          </button>
                        )}
                        <button
                          type="button"
                          disabled={rowBusyId === r.id}
                          onClick={() => remove(r.id)}
                          className="text-red-600 hover:text-red-800 disabled:opacity-50 dark:text-red-400"
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </ListPanel>

      {totalPages > 1 ? (
        <div className="mt-4 flex items-center justify-between text-sm text-slate-600 dark:text-slate-400">
          <div>
            Page {page} of {totalPages}
          </div>
          <div className="flex gap-2">
            <button
              type="button"
              disabled={page <= 1 || loading}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              className="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40 dark:border-slate-700"
            >
              Previous
            </button>
            <button
              type="button"
              disabled={page >= totalPages || loading}
              onClick={() => setPage((p) => p + 1)}
              className="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40 dark:border-slate-700"
            >
              Next
            </button>
          </div>
        </div>
      ) : null}
      </GatedFeatureWorkspace>
    </AppShell>
  );
}
