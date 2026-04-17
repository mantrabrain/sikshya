import { useCallback, useState, type FormEvent } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { AddonEnablePanel } from '../components/AddonEnablePanel';
import { FeatureUpsell } from '../components/FeatureUpsell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { getLicensing, isFeatureEnabled } from '../lib/licensing';
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

export function CourseTeamPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const lic = getLicensing(config);
  const featureOk = isFeatureEnabled(config, 'multi_instructor');
  const addon = useAddonEnabled('multi_instructor');
  const enabled = featureOk && Boolean(addon.enabled);
  const qCourse = config.query?.course_id;
  const [courseId, setCourseId] = useState(qCourse || '');
  const [newUserId, setNewUserId] = useState('');
  const [share, setShare] = useState('0');
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [earningsUserId, setEarningsUserId] = useState('');

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
      {!featureOk ? (
        <FeatureUpsell
          title="Course staff"
          description="Link co-instructors to a course, set revenue share, and keep payouts aligned with your commerce data."
          licensing={lic}
        />
      ) : !enabled ? (
        <AddonEnablePanel
          title="Course staff is not enabled"
          description="Enable the Multi-instructor addon to register course staff routes and unlock co-instructor management."
          canEnable={Boolean(addon.licenseOk)}
          enableBusy={addon.loading}
          onEnable={() => void addon.enable()}
          upgradeUrl={lic.upgradeUrl}
          error={addon.error}
        />
      ) : (
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
              <label className="text-sm text-slate-600 dark:text-slate-400">
                User ID (instructor)
                <input
                  required
                  type="number"
                  value={newUserId}
                  onChange={(e) => setNewUserId(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
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
      )}
    </AppShell>
  );
}
