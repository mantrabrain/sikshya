import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary } from '../components/shared/buttons';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { HorizontalEditorTabs } from '../components/shared/HorizontalEditorTabs';
import { RowActionsMenu, type RowActionItem } from '../components/shared/list/RowActionsMenu';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { useAdminRouting } from '../lib/adminRouting';
import { formatPostDate } from '../lib/formatPostDate';
import type { SikshyaReactConfig } from '../types';

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

export function ReviewsPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const { confirm } = useSikshyaDialog();
  const { navigateView } = useAdminRouting();

  const featureOk = isFeatureEnabled(config, 'course_reviews');
  const addon = useAddonEnabled('course_reviews');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const gateOpen = mode === 'full';

  const [status, setStatus] = useState<StatusFilter>('pending');
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [page, setPage] = useState(1);
  const [rowBusyId, setRowBusyId] = useState<number | null>(null);

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

  const approve = useCallback(
    async (id: number) => {
      setRowBusyId(id);
      try {
        await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.reviewApprove(id), {});
        refetch();
      } finally {
        setRowBusyId(null);
      }
    },
    [refetch]
  );

  const reject = useCallback(
    async (id: number) => {
      setRowBusyId(id);
      try {
        await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.reviewReject(id), {});
        refetch();
      } finally {
        setRowBusyId(null);
      }
    },
    [refetch]
  );

  const remove = useCallback(
    async (id: number) => {
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
    },
    [confirm, refetch]
  );

  const onSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    setSearch(searchInput.trim());
  };

  const actionsForRow = useCallback(
    (r: ReviewRow): RowActionItem[] => {
      const busy = rowBusyId === r.id;
      const items: RowActionItem[] = [
        {
          key: 'detail',
          label: 'View details',
          disabled: busy,
          onClick: () => navigateView('review', { id: String(r.id) }),
        },
      ];
      if (r.view_url) {
        items.push({
          key: 'course',
          label: 'Open course page',
          href: r.view_url,
          external: true,
        });
      }
      if (r.is_approved) {
        items.push({
          key: 'unpublish',
          label: 'Unpublish',
          disabled: busy,
          onClick: () => void reject(r.id),
        });
      } else {
        items.push({
          key: 'approve',
          label: 'Approve',
          disabled: busy,
          onClick: () => void approve(r.id),
        });
      }
      items.push({
        key: 'delete',
        label: 'Delete',
        danger: true,
        disabled: busy,
        onClick: () => void remove(r.id),
      });
      return items;
    },
    [navigateView, approve, reject, remove, rowBusyId]
  );

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
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
        onEnable={() => addon.enable()}
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
                      <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                        {Number(r.reported_count ?? 0) > 0 ? (
                          <span>Reports: {Number(r.reported_count).toLocaleString()}</span>
                        ) : null}
                        {r.reply_text ? (
                          <span className="font-medium text-slate-600 dark:text-slate-300">Has official reply</span>
                        ) : null}
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
                      <RowActionsMenu items={actionsForRow(r)} ariaLabel={`Actions for review ${r.id}`} />
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
    </EmbeddableShell>
  );
}
