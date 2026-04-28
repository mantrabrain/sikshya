import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { useDebouncedValue } from '../hooks/useDebouncedValue';
import { useAsyncData } from '../hooks/useAsyncData';
import { formatPostDate } from '../lib/formatPostDate';
import type { SikshyaReactConfig } from '../types';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';

type Row = {
  user_id: number;
  email: string;
  display_name: string;
  registered: string;
  status: string;
  applied_at: string;
  headline: string;
};

type ListResponse = {
  ok?: boolean;
  rows?: Row[];
  total?: number;
  pages?: number;
  page?: number;
  per_page?: number;
};

export function InstructorApplicationsPage(props: {
  config: SikshyaReactConfig;
  title: string;
  subtitle?: string;
  embedded?: boolean;
}) {
  const { config, title, subtitle, embedded } = props;
  const adminBase = config.adminUrl.replace(/\/?$/, '/');
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('pending');
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 320);
  const [busyId, setBusyId] = useState<number | null>(null);
  const toast = useTopRightToast(2600);

  const queryKey = useMemo(() => `${page}|${status}|${debouncedSearch}`, [page, status, debouncedSearch]);

  const loader = useCallback(async () => {
    return getSikshyaApi().get<ListResponse>(
      SIKSHYA_ENDPOINTS.admin.instructorApplications({
        page,
        per_page: 20,
        status: status || undefined,
        search: debouncedSearch.trim() || undefined,
      })
    );
  }, [page, status, debouncedSearch]);

  const { loading, data, error, refetch } = useAsyncData(loader, [queryKey]);

  const rows = data?.rows ?? [];
  const total = data?.total ?? 0;
  const pages = data?.pages ?? 0;

  const act = async (userId: number, action: 'approve' | 'reject') => {
    setBusyId(userId);
    toast.clear();
    try {
      const path =
        action === 'approve'
          ? SIKSHYA_ENDPOINTS.admin.instructorApplicationApprove(userId)
          : SIKSHYA_ENDPOINTS.admin.instructorApplicationReject(userId);
      await getSikshyaApi().post(path, {});
      toast.success('Saved', action === 'approve' ? 'Approved.' : 'Rejected.');
      void refetch();
    } catch (e) {
      toast.error('Request failed', e instanceof Error ? e.message : 'Request failed');
    } finally {
      setBusyId(null);
    }
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={
        subtitle ??
        'Review “Apply to become an instructor” submissions. Approving assigns the Sikshya instructor role.'
      }
    >
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />

      <ListPanel className="p-4">
        <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div className="flex flex-wrap gap-2">
            <label className="block text-sm text-slate-700 dark:text-slate-300">
              Status
              <select
                className="mt-1 block w-44 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                value={status}
                onChange={(e) => {
                  setPage(1);
                  setStatus(e.target.value);
                }}
              >
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="active">Active</option>
                <option value="rejected">Rejected</option>
                <option value="inactive">Inactive</option>
              </select>
            </label>
            <label className="block min-w-[220px] flex-1 text-sm text-slate-700 dark:text-slate-300">
              Search
              <input
                value={search}
                onChange={(e) => {
                  setPage(1);
                  setSearch(e.target.value);
                }}
                placeholder="Name or email…"
                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              />
            </label>
          </div>
          <p className="text-xs text-slate-500 dark:text-slate-400">{total ? `${total} total` : null}</p>
        </div>

        {error ? (
          <div className="mt-4">
            <ApiErrorPanel error={error} title="Could not load applications" onRetry={() => void refetch()} />
          </div>
        ) : loading ? (
          <p className="mt-6 text-sm text-slate-500">Loading…</p>
        ) : rows.length === 0 ? (
          <div className="mt-6">
            <ListEmptyState
              title="No applications"
              description="When learners submit the instructor form on the account page or shortcode, they appear here."
            />
          </div>
        ) : (
          <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
            <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
              <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                <tr>
                  <th className="px-4 py-3">User</th>
                  <th className="px-4 py-3">Headline</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Submitted</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {rows.map((r) => {
                  const editUrl = `${adminBase}user-edit.php?user_id=${r.user_id}`;
                  const busy = busyId === r.user_id;
                  return (
                    <tr key={r.user_id} className="bg-white dark:bg-slate-950/30">
                      <td className="px-4 py-3">
                        <a href={editUrl} className="font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400">
                          {r.display_name || `User #${r.user_id}`}
                        </a>
                        <div className="text-xs text-slate-500">{r.email}</div>
                      </td>
                      <td className="max-w-xs px-4 py-3 text-slate-700 dark:text-slate-200">
                        {r.headline ? <span className="line-clamp-2">{r.headline}</span> : '—'}
                      </td>
                      <td className="px-4 py-3 capitalize text-slate-700 dark:text-slate-200">{r.status || '—'}</td>
                      <td className="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-400">
                        {r.applied_at ? formatPostDate(r.applied_at) : '—'}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex flex-wrap justify-end gap-2">
                          {r.status === 'pending' ? (
                            <>
                              <ButtonPrimary type="button" disabled={busy} onClick={() => void act(r.user_id, 'approve')}>
                                {busy ? '…' : 'Approve'}
                              </ButtonPrimary>
                              <ButtonSecondary type="button" disabled={busy} onClick={() => void act(r.user_id, 'reject')}>
                                Reject
                              </ButtonSecondary>
                            </>
                          ) : (
                            <span className="text-xs text-slate-400">—</span>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {pages > 1 ? (
          <div className="mt-4 flex items-center justify-between text-sm">
            <button
              type="button"
              className="rounded-lg border border-slate-200 px-3 py-1.5 font-medium hover:bg-slate-50 disabled:opacity-40 dark:border-slate-600 dark:hover:bg-slate-800"
              disabled={page <= 1 || loading}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              Previous
            </button>
            <span className="text-slate-500">
              Page {page} of {pages}
            </span>
            <button
              type="button"
              className="rounded-lg border border-slate-200 px-3 py-1.5 font-medium hover:bg-slate-50 disabled:opacity-40 dark:border-slate-600 dark:hover:bg-slate-800"
              disabled={page >= pages || loading}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </button>
          </div>
        ) : null}
      </ListPanel>
    </EmbeddableShell>
  );
}
