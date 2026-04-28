import { useCallback, useEffect, useMemo, useState } from 'react';
import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { StatusBadge } from '../components/shared/list/StatusBadge';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { Modal } from '../components/shared/Modal';
import { SingleCoursePicker } from '../components/shared/SingleCoursePicker';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import { useDebouncedValue } from '../hooks/useDebouncedValue';
import { useAsyncData } from '../hooks/useAsyncData';
import { term, termLower } from '../lib/terminology';
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

type UserOpt = { id: number; name: string; email?: string };
export function EnrollmentsPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const adminBase = config.adminUrl.replace(/\/?$/, '/');
  const course = term(config, 'course');
  const courseLower = termLower(config, 'course');
  const coursesLower = termLower(config, 'courses');
  const student = term(config, 'student');
  const studentLower = termLower(config, 'student');
  const enrollment = term(config, 'enrollment');
  const enrollmentsLower = termLower(config, 'enrollments');
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('');
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 320);

  const [manualOpen, setManualOpen] = useState(false);
  const [manualSaving, setManualSaving] = useState(false);
  const [manualMsg, setManualMsg] = useState<string | null>(null);
  const [pickedUserId, setPickedUserId] = useState<number | null>(null);
  const [pickedCourseId, setPickedCourseId] = useState<number | null>(null);
  const [userQuery, setUserQuery] = useState('');
  const [userDropdownOpen, setUserDropdownOpen] = useState(false);
  const debouncedUserQuery = useDebouncedValue(userQuery, 240);

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

  const userSearch = useAsyncData(
    async () => {
      if (!manualOpen) return { data: [] as UserOpt[] };
      if (!userDropdownOpen) return { data: [] as UserOpt[] };

      const params = new URLSearchParams({
        per_page: '20',
        page: '1',
        context: 'edit',
        orderby: 'name',
        order: 'asc',
      });
      const q = debouncedUserQuery.trim();
      if (q) params.set('search', q);

      const r = await getWpApi().getWithTotal<Array<{ id: number; name: string; email?: string }>>(`/users?${params.toString()}`);
      const out = Array.isArray(r.data) ? r.data : [];
      return { data: out.map((u) => ({ id: u.id, name: u.name, email: u.email })) };
    },
    [manualOpen, userDropdownOpen, debouncedUserQuery]
  );

  const pickedUserLabel = useMemo(() => {
    if (!pickedUserId) return null;
    const hit = (userSearch.data?.data || []).find((u) => u.id === pickedUserId);
    return hit ? (hit.email ? `${hit.name} (${hit.email})` : hit.name) : `User #${pickedUserId}`;
  }, [pickedUserId, userSearch.data?.data]);

  useEffect(() => {
    if (!manualOpen) {
      return;
    }
    const onDoc = (e: MouseEvent) => {
      const t = e.target as HTMLElement | null;
      if (!t) return;
      if (!t.closest('[data-manual-enroll-user="1"]')) setUserDropdownOpen(false);
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [manualOpen]);

  const resetManual = () => {
    setManualMsg(null);
    setManualSaving(false);
    setPickedUserId(null);
    setPickedCourseId(null);
    setUserQuery('');
    setUserDropdownOpen(false);
  };

  const submitManualEnroll = async () => {
    setManualMsg(null);
    const uid = pickedUserId || 0;
    const cid = pickedCourseId || 0;
    if (uid <= 0 || cid <= 0) {
      setManualMsg(`Pick a ${studentLower} and a ${courseLower}.`);
      return;
    }
    setManualSaving(true);
    try {
      const r = await getSikshyaApi().post<{ ok?: boolean; message?: string }>(SIKSHYA_ENDPOINTS.admin.enrollmentsManual, {
        user_id: uid,
        course_id: cid,
      });
      setManualMsg(r?.message || `${student} enrolled.`);
      setManualOpen(false);
      resetManual();
      refetch();
    } catch (err) {
      setManualMsg(err instanceof Error ? err.message : 'Request failed');
    } finally {
      setManualSaving(false);
    }
  };

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle={`Who is enrolled in which ${coursesLower}, with status and dates`}
      pageActions={
        <div className="flex flex-wrap items-center gap-2">
          <ButtonSecondary
            type="button"
            disabled={loading || tableMissing}
            onClick={() => {
              setManualOpen(true);
              setManualMsg(null);
            }}
          >
            Manual enroll
          </ButtonSecondary>
          <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
            Refresh
          </ButtonPrimary>
        </div>
      }
    >
      <Modal
        open={manualOpen}
        title={`Manual ${enrollment}`}
        description={`Enroll a ${studentLower} into a ${courseLower} right now. This creates an ${enrollmentsLower} row and initializes progress.`}
        onClose={() => {
          setManualOpen(false);
          resetManual();
        }}
        size="lg"
        footer={
          <div className="flex items-center justify-between gap-3">
            <ButtonSecondary
              type="button"
              disabled={manualSaving}
              onClick={() => {
                setManualOpen(false);
                resetManual();
              }}
            >
              Cancel
            </ButtonSecondary>
            <div className="flex items-center gap-2">
              <ButtonPrimary type="button" disabled={manualSaving} onClick={() => void submitManualEnroll()}>
                {manualSaving ? 'Enrolling…' : `Enroll ${studentLower}`}
              </ButtonPrimary>
            </div>
          </div>
        }
      >
        <div className="space-y-4">
          {manualMsg ? (
            <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-200">
              {manualMsg}
            </div>
          ) : null}

          <div className="grid gap-4 sm:grid-cols-2">
            <div data-manual-enroll-user="1">
              <label className="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {student}
              </label>
              <input
                type="text"
                value={userQuery}
                onChange={(e) => {
                  setUserQuery(e.target.value);
                  setPickedUserId(null);
                }}
                onFocus={() => setUserDropdownOpen(true)}
                placeholder="Search by name or email…"
                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              />
              {pickedUserLabel ? (
                <div className="mt-2 text-xs text-slate-600 dark:text-slate-400">
                  Selected: <span className="font-semibold text-slate-900 dark:text-white">{pickedUserLabel}</span>
                </div>
              ) : null}
              {userDropdownOpen ? (
                <div className="mt-2 max-h-64 overflow-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-950">
                  {userSearch.loading ? (
                    <div className="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">Searching…</div>
                  ) : (userSearch.data?.data || []).length === 0 ? (
                    <div className="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">No users found.</div>
                  ) : (
                    <ul className="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                      {(userSearch.data?.data || []).map((u) => (
                        <li key={u.id}>
                          <button
                            type="button"
                            className="flex w-full items-start justify-between gap-3 px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-900"
                            onClick={() => {
                              setPickedUserId(u.id);
                              setUserQuery(u.email ? `${u.name} (${u.email})` : u.name);
                              setUserDropdownOpen(false);
                            }}
                          >
                            <span className="min-w-0">
                              <span className="block truncate font-medium text-slate-900 dark:text-white">{u.name}</span>
                              <span className="block truncate text-xs text-slate-500 dark:text-slate-400">
                                {u.email || `User #${u.id}`}
                              </span>
                            </span>
                            <span className="shrink-0 text-xs text-slate-400">#{u.id}</span>
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              ) : null}
            </div>

            <div data-manual-enroll-course="1">
              <SingleCoursePicker
                label={course}
                value={pickedCourseId ?? 0}
                onChange={(id) => setPickedCourseId(id > 0 ? id : null)}
                placeholder={`Click to choose a ${courseLower}…`}
                hint={`Pick the ${courseLower} this ${studentLower} should be enrolled in.`}
              />
            </div>
          </div>
        </div>
      </Modal>

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
            placeholder={`${student} name, email, or ${courseLower} title…`}
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
          <ApiErrorPanel error={error} title={`Could not load ${enrollmentsLower}`} onRetry={() => refetch()} />
        </div>
      ) : null}

      {tableMissing ? (
        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
          The enrollments database table is not installed yet. Run plugin activation or database updates to create it.
        </div>
      ) : null}

      <ListPanel>
        {loading ? (
          <div className="p-8 text-center text-sm text-slate-500 dark:text-slate-400">
            Loading {enrollmentsLower}…
          </div>
        ) : rows.length === 0 ? (
          <ListEmptyState
            title={`No ${enrollmentsLower}`}
            description={`When ${termLower(config, 'students')} enroll in ${coursesLower}, each row will appear here with links to the user and ${courseLower}.`}
          />
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                  <tr>
                    <th className="px-5 py-3.5">{student}</th>
                    <th className="px-5 py-3.5">{course}</th>
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
                            {r.course_title || `${course} #${r.course_id}`}
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
    </EmbeddableShell>
  );
}
