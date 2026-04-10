import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { StatusBadge } from '../components/shared/list/StatusBadge';
import { ButtonPrimary } from '../components/shared/buttons';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import { useDebouncedValue } from '../hooks/useDebouncedValue';
import { useAsyncData } from '../hooks/useAsyncData';
import type { NavItem, SikshyaReactConfig } from '../types';

type EnrollmentRow = {
  id: number;
  user_id: number;
  course_id: number;
  status: string;
  enrolled_date: string;
  learner_name: string;
  learner_email: string;
  course_title: string;
};

type ListResponse = {
  success?: boolean;
  enrollments?: EnrollmentRow[];
  total?: number;
  pages?: number;
  page?: number;
  per_page?: number;
  table_missing?: boolean;
};

export function EnrollmentsPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const adminBase = config.adminUrl.replace(/\/?$/, '/');
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('');
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 320);

  const queryKey = useMemo(() => `${page}|${status}|${debouncedSearch}`, [page, status, debouncedSearch]);

  const loader = useCallback(async () => {
    const q = new URLSearchParams({
      page: String(page),
      per_page: '20',
    });
    if (status) {
      q.set('status', status);
    }
    if (debouncedSearch.trim()) {
      q.set('search', debouncedSearch.trim());
    }
    return getSikshyaApi().get<ListResponse>(`${SIKSHYA_ENDPOINTS.admin.enrollments}?${q.toString()}`);
  }, [page, status, debouncedSearch]);

  const { loading, data, error, refetch } = useAsyncData(loader, [queryKey]);

  const rows = data?.enrollments ?? [];
  const total = data?.total ?? 0;
  const pages = data?.pages ?? 0;
  const tableMissing = Boolean(data?.table_missing);

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle="Who is enrolled in which courses, with status and dates"
      pageActions={
        <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
          Refresh
        </ButtonPrimary>
      }
    >
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <div className="min-w-[12rem] flex-1">
          <label htmlFor="sik-enr-search" className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Search
          </label>
          <input
            id="sik-enr-search"
            type="search"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder="Learner name, email, or course title…"
            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          />
        </div>
        <div className="w-full min-w-[10rem] sm:w-48">
          <label htmlFor="sik-enr-status" className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Status
          </label>
          <select
            id="sik-enr-status"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          >
            <option value="">All statuses</option>
            <option value="enrolled">Enrolled</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>

      {error ? (
        <div className="mb-4">
          <ApiErrorPanel error={error} title="Could not load enrollments" onRetry={() => refetch()} />
        </div>
      ) : null}

      {tableMissing ? (
        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
          The enrollments database table is not installed yet. Run plugin activation or database updates to create it.
        </div>
      ) : null}

      <ListPanel>
        {loading ? (
          <div className="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading enrollments…</div>
        ) : rows.length === 0 ? (
          <ListEmptyState
            title="No enrollments"
            description="When learners enroll in courses, each row will appear here with links to the user and course."
          />
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                  <tr>
                    <th className="px-5 py-3.5">Learner</th>
                    <th className="px-5 py-3.5">Course</th>
                    <th className="px-5 py-3.5">Status</th>
                    <th className="px-5 py-3.5">Enrolled</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {rows.map((r) => (
                    <tr key={r.id} className="bg-white dark:bg-slate-900">
                      <td className="px-5 py-3.5">
                        <a
                          href={`${adminBase}user-edit.php?user_id=${r.user_id}`}
                          className="font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                        >
                          {r.learner_name || `User #${r.user_id}`}
                        </a>
                        <div className="text-xs text-slate-500 dark:text-slate-400">{r.learner_email || '—'}</div>
                      </td>
                      <td className="px-5 py-3.5">
                        {r.course_id > 0 ? (
                          <a
                            href={appViewHref(config, 'add-course', { course_id: String(r.course_id) })}
                            className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                          >
                            {r.course_title || `Course #${r.course_id}`}
                          </a>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td className="px-5 py-3.5">
                        <StatusBadge status={r.status} />
                      </td>
                      <td className="whitespace-nowrap px-5 py-3.5 text-slate-600 dark:text-slate-400">
                        {formatPostDate(r.enrolled_date)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {pages > 1 ? (
              <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  Page {page} of {pages} · {total} total
                </p>
                <div className="flex gap-2">
                  <button
                    type="button"
                    disabled={page <= 1}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                    className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium disabled:opacity-40 dark:border-slate-600 dark:bg-slate-800"
                  >
                    Previous
                  </button>
                  <button
                    type="button"
                    disabled={page >= pages}
                    onClick={() => setPage((p) => p + 1)}
                    className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium disabled:opacity-40 dark:border-slate-600 dark:bg-slate-800"
                  >
                    Next
                  </button>
                </div>
              </div>
            ) : null}
          </>
        )}
      </ListPanel>
    </AppShell>
  );
}
