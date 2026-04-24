import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { useDebouncedValue } from '../hooks/useDebouncedValue';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import type { NavItem, SikshyaReactConfig } from '../types';

type InstructorRow = {
  id: number;
  course_id: number;
  user_id: number;
  role: string;
  revenue_share: number;
};

type Resp = { ok?: boolean; instructors?: InstructorRow[] };
type EarningsRow = { id: number; order_item_id: number; amount: number; status: string; created_at: string };
type EarningsResp = { ok?: boolean; rows?: EarningsRow[]; total?: number };
type UserOpt = { id: number; name: string };

export function CourseTeamPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const featureOk = isFeatureEnabled(config, 'multi_instructor');
  const addon = useAddonEnabled('multi_instructor');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';
  const qCourse = config.query?.course_id;
  const [courseId, setCourseId] = useState(qCourse || '');
  const [newUserId, setNewUserId] = useState('');
  const [userQuery, setUserQuery] = useState('');
  const [userDropdownOpen, setUserDropdownOpen] = useState(false);
  const [share, setShare] = useState('0');
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [earningsUserId, setEarningsUserId] = useState('');

  const debouncedUserQuery = useDebouncedValue(userQuery, 240);
  const userSearch = useAsyncData(
    async () => {
      if (!enabled) return { data: [] as UserOpt[] };
      if (!userDropdownOpen) return { data: [] as UserOpt[] };
      const q = debouncedUserQuery.trim();
      const params = new URLSearchParams({
        per_page: '20',
        page: '1',
        context: 'edit',
        orderby: 'name',
        order: 'asc',
        role: 'sikshya_instructor',
      });
      if (q) {
        params.set('search', q);
      }
      const r = await getWpApi().getWithTotal<Array<{ id: number; name: string }>>(`/users?${params.toString()}`);
      const out = Array.isArray(r.data) ? r.data : [];
      return { data: out.map((u) => ({ id: u.id, name: u.name })) };
    },
    [enabled, debouncedUserQuery, userDropdownOpen]
  );

  const pickedUserLabel = useMemo(() => {
    const uid = parseInt(newUserId, 10);
    if (!Number.isFinite(uid) || uid <= 0) return null;
    const hit = (userSearch.data?.data || []).find((u) => u.id === uid);
    return hit ? hit.name : `User #${uid}`;
  }, [newUserId, userSearch.data?.data]);

  useEffect(() => {
    if (!userDropdownOpen) return;
    const onDoc = (e: MouseEvent) => {
      const t = e.target as HTMLElement | null;
      if (!t) return;
      // Close if click is outside this input/list block.
      if (!t.closest('[data-instructor-picker="1"]')) {
        setUserDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [userDropdownOpen]);

  const earningsLoader = useCallback(async () => {
    if (!enabled) return { ok: true, rows: [] as EarningsRow[], total: 0 };
    const uid = parseInt(earningsUserId, 10);
    if (!Number.isFinite(uid) || uid <= 0) return { ok: true, rows: [] as EarningsRow[], total: 0 };
    return getSikshyaApi().get<EarningsResp>(SIKSHYA_ENDPOINTS.pro.earnings(uid));
  }, [earningsUserId, enabled]);

  const {
    loading: earningsLoading,
    data: earningsData,
    error: earningsError,
    refetch: refetchEarnings,
  } = useAsyncData(earningsLoader, [earningsUserId, enabled]);

  const loader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, instructors: [] as InstructorRow[] };
    }
    const cid = parseInt(courseId, 10);
    if (!Number.isFinite(cid) || cid <= 0) {
      return { ok: true, instructors: [] };
    }
    return getSikshyaApi().get<Resp>(SIKSHYA_ENDPOINTS.pro.courseInstructors(cid));
  }, [courseId, enabled]);

  const { loading, data, error, refetch } = useAsyncData(loader, [courseId, enabled]);
  const rows = data?.instructors ?? [];

  const addInstructor = async (e: FormEvent) => {
    e.preventDefault();
    setMsg(null);
    const cid = parseInt(courseId, 10);
    const uid = parseInt(newUserId, 10);
    if (!Number.isFinite(cid) || cid <= 0 || !Number.isFinite(uid) || uid <= 0) {
      setMsg('Enter valid course and user IDs.');
      return;
    }
    setSaving(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.addCourseInstructor, {
        course_id: cid,
        user_id: uid,
        revenue_share: parseFloat(share) || 0,
      });
      setMsg('Instructor linked.');
      setNewUserId('');
      refetch();
    } catch (err) {
      setMsg(err instanceof Error ? err.message : 'Request failed');
    } finally {
      setSaving(false);
    }
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
      subtitle="Co-instructors, roles, and optional revenue share by course."
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="multi_instructor"
        config={config}
        featureTitle="Course staff"
        featureDescription="Link co-instructors to a course, set revenue share, and keep payouts aligned with your commerce data."
        previewVariant="table"
        addonEnableTitle="Course staff is not enabled"
        addonEnableDescription="Enable the Multi-instructor addon to register course staff routes and unlock co-instructor management."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
        <>
          {error ? <ApiErrorPanel error={error} title="Could not load team" onRetry={() => refetch()} /> : null}

          <form
            onSubmit={addInstructor}
            className="mb-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
          >
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Course team</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-4">
              <label className="text-sm text-slate-600 dark:text-slate-400">
                Course ID
                <input
                  required
                  type="number"
                  value={courseId}
                  onChange={(e) => setCourseId(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <div className="text-sm text-slate-600 dark:text-slate-400" data-instructor-picker="1">
                Instructor
                <input
                  type="text"
                  value={userQuery}
                  onChange={(e) => setUserQuery(e.target.value)}
                  onFocus={() => setUserDropdownOpen(true)}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                  placeholder="Search by name/email…"
                />
                {pickedUserLabel ? (
                  <div className="mt-2 flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                    <span className="min-w-0 truncate">
                      Selected: <span className="font-semibold">{pickedUserLabel}</span>
                    </span>
                    <button
                      type="button"
                      className="rounded-md px-2 py-1 text-slate-500 hover:bg-red-50 hover:text-red-700 dark:text-slate-300 dark:hover:bg-red-950/30 dark:hover:text-red-200"
                      onClick={() => setNewUserId('')}
                      title="Clear selected instructor"
                    >
                      Clear
                    </button>
                  </div>
                ) : null}
                {userSearch.loading ? (
                  <div className="mt-2 text-xs text-slate-500 dark:text-slate-400">Searching…</div>
                ) : userSearch.error ? (
                  <div className="mt-2 text-xs text-red-600 dark:text-red-400">Could not search users.</div>
                ) : userDropdownOpen ? (
                  <div className="mt-2 max-h-56 overflow-auto rounded-lg border border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-950">
                    {(userSearch.data?.data || []).length ? (
                      (userSearch.data?.data || []).map((u) => (
                        <button
                          key={u.id}
                          type="button"
                          className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-900"
                          onClick={() => {
                            setNewUserId(String(u.id));
                            setUserQuery(u.name);
                            setUserDropdownOpen(false);
                          }}
                          title={`Select ${u.name}`}
                        >
                          <span className="min-w-0 truncate">{u.name}</span>
                          <span className="shrink-0 text-xs text-slate-500 dark:text-slate-400">#{u.id}</span>
                        </button>
                      ))
                    ) : (
                      <div className="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
                        {debouncedUserQuery.trim() ? 'No matches.' : 'Start typing to search, or pick from the list.'}
                      </div>
                    )}
                  </div>
                ) : null}
                <input type="hidden" value={newUserId} required />
              </div>
              <label className="text-sm text-slate-600 dark:text-slate-400">
                Revenue share %
                <input
                  type="number"
                  step="0.01"
                  min={0}
                  max={100}
                  value={share}
                  onChange={(e) => setShare(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <div className="flex items-end">
                <ButtonPrimary type="submit" disabled={saving}>
                  {saving ? 'Saving…' : 'Add / update'}
                </ButtonPrimary>
              </div>
            </div>
            {msg ? <p className="mt-3 text-sm text-slate-600 dark:text-slate-400">{msg}</p> : null}
          </form>

          <ListPanel>
            {loading ? (
              <div className="p-8 text-center text-sm text-slate-500">Loading…</div>
            ) : !courseId || parseInt(courseId, 10) <= 0 ? (
              <ListEmptyState title="Enter a course ID" description="Set the course ID above to load co-instructors." />
            ) : rows.length === 0 ? (
              <ListEmptyState title="No extra instructors" description="Only the primary author is linked, or the table is empty." />
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                  <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                    <tr>
                      <th className="px-5 py-3.5">User</th>
                      <th className="px-5 py-3.5">Role</th>
                      <th className="px-5 py-3.5">Share %</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {rows.map((r) => (
                      <tr key={r.id} className="bg-white dark:bg-slate-900">
                        <td className="px-5 py-3.5">{r.user_id}</td>
                        <td className="px-5 py-3.5">{r.role}</td>
                        <td className="px-5 py-3.5 tabular-nums">{Number(r.revenue_share).toFixed(2)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </ListPanel>

          <div className="mt-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Instructor earnings (v1)</h2>
            <div className="mt-4 flex flex-wrap items-end gap-3">
              <label className="text-sm text-slate-600 dark:text-slate-400">
                Instructor user ID
                <input
                  type="number"
                  value={earningsUserId}
                  onChange={(e) => setEarningsUserId(e.target.value)}
                  className="ml-2 mt-1 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <ButtonPrimary type="button" disabled={earningsLoading} onClick={() => refetchEarnings()}>
                {earningsLoading ? 'Loading…' : 'Load'}
              </ButtonPrimary>
              <div className="text-sm text-slate-600 dark:text-slate-400">
                Total: <span className="font-semibold tabular-nums">{Number(earningsData?.total || 0).toFixed(2)}</span>
              </div>
            </div>

            {earningsError ? <ApiErrorPanel error={earningsError} title="Could not load earnings" onRetry={() => refetchEarnings()} /> : null}
            {(earningsData?.rows?.length || 0) > 0 ? (
              <div className="mt-4 overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                  <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                    <tr>
                      <th className="px-5 py-3.5">ID</th>
                      <th className="px-5 py-3.5">Order item</th>
                      <th className="px-5 py-3.5">Amount</th>
                      <th className="px-5 py-3.5">Status</th>
                      <th className="px-5 py-3.5">Created</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {(earningsData?.rows || []).map((r) => (
                      <tr key={r.id} className="bg-white dark:bg-slate-900">
                        <td className="px-5 py-3.5">{r.id}</td>
                        <td className="px-5 py-3.5">{r.order_item_id}</td>
                        <td className="px-5 py-3.5 tabular-nums">{Number(r.amount).toFixed(2)}</td>
                        <td className="px-5 py-3.5 capitalize">{r.status}</td>
                        <td className="px-5 py-3.5 text-slate-600 dark:text-slate-400">{r.created_at || '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="mt-4 text-sm text-slate-600 dark:text-slate-400">No earnings rows yet.</p>
            )}
          </div>
        </>
      </GatedFeatureWorkspace>
    </AppShell>
  );
}
