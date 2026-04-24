import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { getErrorSummary, getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { HorizontalEditorTabs } from '../components/shared/HorizontalEditorTabs';
import { DataTable, type Column } from '../components/shared/DataTable';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary } from '../components/shared/buttons';
import { Modal } from '../components/shared/Modal';
import { RowActionsMenu } from '../components/shared/list/RowActionsMenu';
import { DataTableSkeleton } from '../components/shared/Skeleton';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { useAdminRouting } from '../lib/adminRouting';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import type { NavItem, SikshyaReactConfig, WpRestUser } from '../types';

type Plan = {
  id: number;
  name: string;
  amount: number;
  currency: string;
  interval_unit: string;
  status: string;
};

type SubRow = {
  id: number;
  user_id: number;
  plan_id: number;
  status: string;
  gateway: string;
  current_period_end: string | null;
};

type TabId = 'plans' | 'list';

const TAB_IDS: TabId[] = ['plans', 'list'];

type ToastState = { kind: 'success' | 'error'; text: string } | null;

/**
 * Tabbed workspace that separates plan definitions from the subscriptions
 * assigned to users. Keeping these on two tabs (instead of stacked lists)
 * matches the rest of our admin hubs (Sales, Certificates, People) and lets
 * each view own its own toolbar, empty state, and modal.
 */
export function SubscriptionsProPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const featureOk = isFeatureEnabled(config, 'subscriptions');
  const addon = useAddonEnabled('subscriptions');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [toast, setToast] = useState<ToastState>(null);
  useEffect(() => {
    if (!toast || toast.kind !== 'success') return;
    const t = window.setTimeout(() => setToast(null), 2600);
    return () => window.clearTimeout(t);
  }, [toast]);

  const { route, navigateView } = useAdminRouting();
  const activeTab: TabId = (() => {
    const fromUrl = String(route.query?.tab || '').trim();
    return (TAB_IDS as string[]).includes(fromUrl) ? (fromUrl as TabId) : 'list';
  })();
  const setActiveTab = (id: string) => {
    navigateView(route.page, { tab: id });
  };

  const loader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, subscriptions: [] as SubRow[], plans: [] as Plan[], users: [] as WpRestUser[] };
    }
    const [subs, plans] = await Promise.all([
      getSikshyaApi().get<{ ok?: boolean; subscriptions?: SubRow[] }>(SIKSHYA_ENDPOINTS.pro.subscriptions),
      getSikshyaApi().get<{ ok?: boolean; plans?: Plan[] }>(SIKSHYA_ENDPOINTS.pro.plans),
    ]);
    const rawSubs = Array.isArray(subs.subscriptions) ? subs.subscriptions : [];
    const rawPlans = Array.isArray(plans.plans) ? plans.plans : [];

    // Defensive: some responses serialize numbers as strings.
    const normPlans: Plan[] = rawPlans
      .map((p) => ({
        id: Number((p as unknown as { id?: unknown }).id ?? 0),
        name: String((p as unknown as { name?: unknown }).name ?? ''),
        amount: Number((p as unknown as { amount?: unknown }).amount ?? 0),
        currency: String((p as unknown as { currency?: unknown }).currency ?? 'USD'),
        interval_unit: String((p as unknown as { interval_unit?: unknown }).interval_unit ?? 'month'),
        status: String((p as unknown as { status?: unknown }).status ?? 'active'),
      }))
      .filter((p) => p.id > 0);

    const normSubs: SubRow[] = rawSubs
      .map((r) => ({
        id: Number((r as unknown as { id?: unknown }).id ?? 0),
        user_id: Number((r as unknown as { user_id?: unknown }).user_id ?? 0),
        plan_id: Number((r as unknown as { plan_id?: unknown }).plan_id ?? 0),
        status: String((r as unknown as { status?: unknown }).status ?? ''),
        gateway: String((r as unknown as { gateway?: unknown }).gateway ?? ''),
        current_period_end:
          (r as unknown as { current_period_end?: unknown }).current_period_end === null
            ? null
            : String((r as unknown as { current_period_end?: unknown }).current_period_end ?? ''),
      }))
      .filter((r) => r.id > 0);

    const userIds = Array.from(new Set(normSubs.map((s) => s.user_id).filter((id) => id > 0)));
    const users =
      userIds.length > 0
        ? await getWpApi().get<WpRestUser[]>(
            `/users?context=edit&per_page=100&include=${encodeURIComponent(userIds.join(','))}`
          )
        : ([] as WpRestUser[]);

    return { ok: true, subscriptions: normSubs, plans: normPlans, users };
  }, [enabled]);

  const { loading, data, error, refetch } = useAsyncData(loader, [enabled]);
  const rows = Array.isArray(data?.subscriptions) ? data.subscriptions : [];
  const plans = Array.isArray(data?.plans) ? data.plans : [];
  const planById = useMemo(() => new Map(plans.map((p) => [p.id, p])), [plans]);
  const users = Array.isArray((data as unknown as { users?: WpRestUser[] } | undefined)?.users)
    ? (data as unknown as { users: WpRestUser[] }).users
    : [];
  const userById = useMemo(() => new Map(users.map((u) => [u.id, u])), [users]);

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle="Recurring billing and access periods synced from your payment provider."
      pageActions={
        enabled ? (
          <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
            Refresh
          </ButtonPrimary>
        ) : null
      }
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="subscriptions"
        config={config}
        featureTitle="Subscriptions"
        featureDescription="Sell monthly or yearly plans, sync status from payment webhooks, and expire access when billing stops."
        previewVariant="cards"
        addonEnableTitle="Subscriptions is not enabled"
        addonEnableDescription="Enable the Subscriptions addon to register recurring billing routes and show subscription settings."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
        <div className="-mt-2 mb-5 flex flex-wrap items-center gap-2 overflow-x-auto">
          <HorizontalEditorTabs
            ariaLabel="Subscriptions sections"
            tabs={[
              { id: 'list', label: 'Subscriptions', icon: 'table' },
              { id: 'plans', label: 'Plans', icon: 'badge' },
            ]}
            value={activeTab}
            onChange={setActiveTab}
          />
        </div>

        {error ? (
          <ApiErrorPanel error={error} title="Could not load subscriptions" onRetry={() => refetch()} />
        ) : activeTab === 'plans' ? (
          <PlansTab loading={loading} plans={plans} onRefetch={refetch} onToast={setToast} />
        ) : (
          <SubscriptionsTab
            loading={loading}
            rows={rows}
            plans={plans}
            planById={planById}
            userById={userById}
            onRefetch={refetch}
            onJumpToPlans={() => setActiveTab('plans')}
            onToast={setToast}
          />
        )}

        {/* Toast */}
        {toast ? (
          <div
            role="status"
            className={`fixed right-4 top-4 z-[9999] w-[min(28rem,calc(100vw-2rem))] rounded-xl border px-4 py-3 text-sm shadow-lg ${
              toast.kind === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-200'
                : 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-200'
            }`}
          >
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">{toast.text}</div>
              <button
                type="button"
                className="shrink-0 rounded-md px-2 py-1 text-xs font-semibold hover:bg-black/5 dark:hover:bg-white/10"
                onClick={() => setToast(null)}
              >
                Dismiss
              </button>
            </div>
          </div>
        ) : null}
      </GatedFeatureWorkspace>
    </AppShell>
  );
}

function PlansTab(props: { loading: boolean; plans: Plan[]; onRefetch: () => void; onToast: (t: ToastState) => void }) {
  const { loading, plans, onRefetch, onToast } = props;

  const [createOpen, setCreateOpen] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<number>(0);

  const [name, setName] = useState('');
  const [amount, setAmount] = useState('29');
  const [currency, setCurrency] = useState('USD');
  const [interval, setInterval] = useState<'month' | 'year'>('month');
  const [status, setStatus] = useState<'active' | 'inactive'>('active');

  const columns: Column<Plan>[] = useMemo(
    () => [
      {
        id: 'id',
        header: 'ID',
        alwaysVisible: true,
        cellClassName: 'whitespace-nowrap tabular-nums text-slate-600 dark:text-slate-400',
        render: (p) => p.id,
      },
      {
        id: 'plan',
        header: 'Plan',
        alwaysVisible: true,
        render: (p) => (
          <div className="min-w-0">
            <div className="truncate font-semibold text-slate-900 dark:text-white">{p.name}</div>
            <div className="truncate text-xs text-slate-500 dark:text-slate-400">#{p.id}</div>
          </div>
        ),
      },
      {
        id: 'price',
        header: 'Price',
        cellClassName: 'whitespace-nowrap tabular-nums',
        render: (p) => `${Number.isFinite(p.amount) ? p.amount.toFixed(2) : String(p.amount)} ${p.currency}`,
      },
      {
        id: 'interval',
        header: 'Interval',
        cellClassName: 'whitespace-nowrap capitalize text-slate-700 dark:text-slate-300',
        render: (p) => p.interval_unit,
      },
      {
        id: 'status',
        header: 'Status',
        cellClassName: 'whitespace-nowrap capitalize text-slate-700 dark:text-slate-300',
        render: (p) => p.status,
      },
      {
        id: 'actions',
        header: '',
        headerClassName: 'w-10',
        cellClassName: 'w-10 whitespace-nowrap text-right',
        render: (p) => (
          <RowActionsMenu
            ariaLabel={`Plan actions for ${p.name}`}
            items={[
              {
                key: 'edit',
                label: 'Edit',
                onClick: () => {
                  setEditingId(p.id);
                  setName(p.name || '');
                  setAmount(String(p.amount ?? 0));
                  setCurrency(String(p.currency || 'USD'));
                  setInterval(p.interval_unit === 'year' ? 'year' : 'month');
                  setStatus(p.status === 'inactive' ? 'inactive' : 'active');
                  setEditOpen(true);
                },
              },
              {
                key: 'delete',
                label: 'Delete',
                danger: true,
                onClick: async () => {
                  const ok = window.confirm(`Delete plan \"${p.name}\"? This cannot be undone.`);
                  if (!ok) return;
                  try {
                    await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.pro.plan(p.id));
                    onRefetch();
                    onToast({ kind: 'success', text: 'Plan deleted.' });
                  } catch (e) {
                    onToast({ kind: 'error', text: getErrorSummary(e) || 'Could not delete plan.' });
                  }
                },
              },
            ]}
          />
        ),
      },
    ],
    [onRefetch]
  );

  return (
    <>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-base font-semibold text-slate-900 dark:text-white">Plans</h2>
          <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Define the price and billing interval that subscriptions attach to.
          </p>
        </div>
        <ButtonPrimary type="button" onClick={() => setCreateOpen(true)}>
          Add plan
        </ButtonPrimary>
      </div>

      <ListPanel>
        {loading ? (
          <DataTableSkeleton headers={['ID', 'Plan', 'Price', 'Interval', 'Status', '']} />
        ) : (
          <DataTable
            wrapInCard={false}
            columns={columns}
            rows={plans}
            rowKey={(p) => p.id}
            emptyContent={
              <ListEmptyState
                title="No plans yet"
                description="Create your first membership plan to start selling subscriptions."
              />
            }
          />
        )}
      </ListPanel>

      <Modal
        open={createOpen}
        title="Add plan"
        description="Plans define the price and billing interval for membership access."
        onClose={() => setCreateOpen(false)}
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
              onClick={() => setCreateOpen(false)}
            >
              Cancel
            </button>
            <ButtonPrimary type="submit" form="sikshya-plan-create" disabled={saving}>
              {saving ? 'Saving…' : 'Create plan'}
            </ButtonPrimary>
          </div>
        }
      >
        <form
          id="sikshya-plan-create"
          className="grid gap-4 sm:grid-cols-2"
          onSubmit={async (e: FormEvent) => {
            e.preventDefault();
            setSaving(true);
            try {
              await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.plans, {
                name,
                amount: parseFloat(amount) || 0,
                currency: currency || 'USD',
                interval_unit: interval,
                status,
              });
              setName('');
              setCreateOpen(false);
              onRefetch();
              window.setTimeout(() => onRefetch(), 350);
              onToast({ kind: 'success', text: 'Plan created.' });
            } catch (e) {
              onToast({ kind: 'error', text: getErrorSummary(e) || 'Could not create plan.' });
            } finally {
              setSaving(false);
            }
          }}
        >
          <label className="text-sm text-slate-600 dark:text-slate-400 sm:col-span-2">
            Plan name
            <input
              required
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
            />
          </label>
          <label className="text-sm text-slate-600 dark:text-slate-400">
            Amount
            <input
              required
              type="number"
              step="0.01"
              min={0}
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
            />
          </label>
          <label className="text-sm text-slate-600 dark:text-slate-400">
            Currency
            <input
              value={currency}
              onChange={(e) => setCurrency(e.target.value.toUpperCase())}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
            />
          </label>
          <label className="text-sm text-slate-600 dark:text-slate-400 sm:col-span-2">
            Billing interval
            <select
              value={interval}
              onChange={(e) => setInterval(e.target.value === 'year' ? 'year' : 'month')}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
            >
              <option value="month">Monthly</option>
              <option value="year">Yearly</option>
            </select>
          </label>
          <label className="text-sm text-slate-600 dark:text-slate-400 sm:col-span-2">
            Status
            <select
              value={status}
              onChange={(e) => setStatus(e.target.value === 'inactive' ? 'inactive' : 'active')}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </label>
        </form>
      </Modal>

      <Modal
        open={editOpen}
        title="Edit plan"
        description="Update the plan name, price, interval, and status."
        onClose={() => setEditOpen(false)}
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
              onClick={() => setEditOpen(false)}
            >
              Cancel
            </button>
            <ButtonPrimary type="submit" form="sikshya-plan-edit" disabled={saving || editingId <= 0}>
              {saving ? 'Saving…' : 'Save changes'}
            </ButtonPrimary>
          </div>
        }
      >
        <form
          id="sikshya-plan-edit"
          className="grid gap-4 sm:grid-cols-2"
          onSubmit={async (e: FormEvent) => {
            e.preventDefault();
            if (editingId <= 0) return;
            setSaving(true);
            try {
              await getSikshyaApi().put(SIKSHYA_ENDPOINTS.pro.plan(editingId), {
                name,
                amount: parseFloat(amount) || 0,
                currency: currency || 'USD',
                interval_unit: interval,
                status,
              });
              setEditOpen(false);
              onRefetch();
              window.setTimeout(() => onRefetch(), 350);
              onToast({ kind: 'success', text: 'Plan updated.' });
            } catch (e) {
              onToast({ kind: 'error', text: getErrorSummary(e) || 'Could not update plan.' });
            } finally {
              setSaving(false);
            }
          }}
        >
          <label className="text-sm text-slate-600 dark:text-slate-400 sm:col-span-2">
            Plan name
            <input
              required
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
            />
          </label>
          <label className="text-sm text-slate-600 dark:text-slate-400">
            Amount
            <input
              required
              type="number"
              step="0.01"
              min={0}
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
            />
          </label>
          <label className="text-sm text-slate-600 dark:text-slate-400">
            Currency
            <input
              value={currency}
              onChange={(e) => setCurrency(e.target.value.toUpperCase())}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
            />
          </label>
          <label className="text-sm text-slate-600 dark:text-slate-400 sm:col-span-2">
            Billing interval
            <select
              value={interval}
              onChange={(e) => setInterval(e.target.value === 'year' ? 'year' : 'month')}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
            >
              <option value="month">Monthly</option>
              <option value="year">Yearly</option>
            </select>
          </label>
          <label className="text-sm text-slate-600 dark:text-slate-400 sm:col-span-2">
            Status
            <select
              value={status}
              onChange={(e) => setStatus(e.target.value === 'inactive' ? 'inactive' : 'active')}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </label>
        </form>
      </Modal>
    </>
  );
}

function SubscriptionsTab(props: {
  loading: boolean;
  rows: SubRow[];
  plans: Plan[];
  planById: Map<number, Plan>;
  userById: Map<number, WpRestUser>;
  onRefetch: () => void;
  onJumpToPlans: () => void;
  onToast: (t: ToastState) => void;
}) {
  const { loading, rows, plans, planById, userById, onRefetch, onJumpToPlans, onToast } = props;

  const [open, setOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [pickedUser, setPickedUser] = useState<WpRestUser | null>(null);
  const [userQuery, setUserQuery] = useState<string>('');
  const [userResults, setUserResults] = useState<WpRestUser[]>([]);
  const [userLoading, setUserLoading] = useState(false);
  const [userOpen, setUserOpen] = useState(false);

  const [pickedPlanId, setPickedPlanId] = useState<number>(0);
  const [planQuery, setPlanQuery] = useState<string>('');
  const [planOpen, setPlanOpen] = useState(false);

  const canAdd = plans.length > 0;

  const filteredPlans = useMemo(() => {
    const q = planQuery.trim().toLowerCase();
    if (!q) return plans;
    return plans.filter((p) => `${p.name} ${p.id}`.toLowerCase().includes(q));
  }, [planQuery, plans]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const q = userQuery.trim();
    if (q.length < 2) {
      setUserResults([]);
      return;
    }

    let alive = true;
    const t = window.setTimeout(async () => {
      setUserLoading(true);
      try {
        const users = await getWpApi().get<WpRestUser[]>(
          `/users?context=edit&per_page=20&search=${encodeURIComponent(q)}`
        );
        if (alive) {
          setUserResults(Array.isArray(users) ? users : []);
        }
      } catch {
        if (alive) {
          setUserResults([]);
        }
      } finally {
        if (alive) {
          setUserLoading(false);
        }
      }
    }, 250);

    return () => {
      alive = false;
      window.clearTimeout(t);
    };
  }, [open, userQuery]);

  const columns: Column<SubRow>[] = useMemo(
    () => [
      {
        id: 'id',
        header: 'ID',
        alwaysVisible: true,
        cellClassName: 'whitespace-nowrap tabular-nums text-slate-600 dark:text-slate-400',
        render: (r) => r.id,
      },
      {
        id: 'user',
        header: 'User',
        alwaysVisible: true,
        render: (r) =>
          userById.get(r.user_id) ? (
            <div className="min-w-0">
              <div className="truncate font-semibold text-slate-900 dark:text-white">
                {userById.get(r.user_id)!.name || userById.get(r.user_id)!.slug}
              </div>
              <div className="truncate text-xs text-slate-500 dark:text-slate-400">
                {userById.get(r.user_id)!.email} · #{r.user_id}
              </div>
            </div>
          ) : (
            <span className="text-slate-700 dark:text-slate-300">#{r.user_id}</span>
          ),
      },
      {
        id: 'plan',
        header: 'Plan',
        alwaysVisible: true,
        render: (r) =>
          planById.get(r.plan_id) ? (
            <div className="min-w-0">
              <div className="truncate font-semibold text-slate-900 dark:text-white">{planById.get(r.plan_id)!.name}</div>
              <div className="truncate text-xs text-slate-500 dark:text-slate-400">
                #{r.plan_id} · {planById.get(r.plan_id)!.amount.toFixed(2)} {planById.get(r.plan_id)!.currency} /{' '}
                {planById.get(r.plan_id)!.interval_unit}
              </div>
            </div>
          ) : (
            <span className="text-slate-700 dark:text-slate-300">#{r.plan_id}</span>
          ),
      },
      { id: 'status', header: 'Status', cellClassName: 'whitespace-nowrap capitalize', render: (r) => r.status },
      { id: 'gateway', header: 'Gateway', cellClassName: 'whitespace-nowrap', render: (r) => r.gateway || '—' },
      {
        id: 'period',
        header: 'Period end',
        cellClassName: 'whitespace-nowrap text-slate-600 dark:text-slate-400',
        render: (r) => r.current_period_end || '—',
      },
      {
        id: 'actions',
        header: '',
        headerClassName: 'w-10',
        cellClassName: 'w-10 whitespace-nowrap text-right',
        render: (r) => (
          <RowActionsMenu
            ariaLabel={`Subscription actions for #${r.id}`}
            items={[
              ...(r.status === 'active'
                ? [
                    {
                      key: 'cancel',
                      label: 'Cancel',
                      danger: true,
                      onClick: async () => {
                        const ok = window.confirm('Cancel this subscription?');
                        if (!ok) return;
                        try {
                          await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.subscriptionsCancel, { id: r.id });
                          onRefetch();
                          onToast({ kind: 'success', text: 'Subscription cancelled.' });
                        } catch (e) {
                          onToast({ kind: 'error', text: getErrorSummary(e) || 'Could not cancel subscription.' });
                        }
                      },
                    },
                  ]
                : []),
            ]}
          />
        ),
      },
    ],
    [onRefetch, planById, userById]
  );

  return (
    <>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-base font-semibold text-slate-900 dark:text-white">Subscriptions</h2>
          <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Manual subscriptions are useful for offline renewals or admin grants.
          </p>
        </div>
        <ButtonPrimary
          type="button"
          disabled={!canAdd}
          onClick={() => {
            setPickedUser(null);
            setUserQuery('');
            setUserResults([]);
            setUserOpen(false);
            setPlanQuery('');
            setPlanOpen(false);
            setPickedPlanId(plans[0]?.id ?? 0);
            setOpen(true);
          }}
        >
          Add subscription
        </ButtonPrimary>
      </div>

      {!canAdd && !loading ? (
        <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
          You need at least one plan before you can create a subscription.{' '}
          <button type="button" className="font-semibold underline" onClick={onJumpToPlans}>
            Add a plan first
          </button>
          .
        </div>
      ) : null}

      <ListPanel>
        {loading ? (
          <DataTableSkeleton headers={['ID', 'User', 'Plan', 'Status', 'Gateway', 'Period end', '']} />
        ) : (
          <DataTable
            wrapInCard={false}
            columns={columns}
            rows={rows}
            rowKey={(r) => r.id}
            emptyContent={
              <ListEmptyState
                title="No subscriptions yet"
                description="Create a subscription manually, or connect a gateway to sync recurring billing."
              />
            }
          />
        )}
      </ListPanel>

      <Modal
        open={open}
        title="Add subscription (manual)"
        description="Pick a user and a plan. Use this for offline renewals or admin grants."
        size="xl"
        onClose={() => setOpen(false)}
        footer={
          <div className="flex items-center justify-between gap-2">
            <div className="text-xs text-slate-500 dark:text-slate-400">
              {pickedUser ? (
                <>
                  Selected: <span className="font-semibold">{pickedUser.name || pickedUser.slug}</span> · #{pickedUser.id}
                </>
              ) : (
                'Select a user to continue.'
              )}
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                onClick={() => setOpen(false)}
              >
                Cancel
              </button>
              <ButtonPrimary type="submit" form="sikshya-sub-create" disabled={creating || !pickedUser || pickedPlanId <= 0}>
                {creating ? 'Creating…' : 'Create subscription'}
              </ButtonPrimary>
            </div>
          </div>
        }
      >
        <form
          id="sikshya-sub-create"
          onSubmit={async (e: FormEvent) => {
            e.preventDefault();
            if (!pickedUser || pickedPlanId <= 0) {
              return;
            }
            setCreating(true);
            try {
              const created = await getSikshyaApi().post<{
                ok?: boolean;
                id?: number;
                subscription?: SubRow;
                message?: string;
                db_error?: string;
              }>(SIKSHYA_ENDPOINTS.pro.subscriptions, {
                user_id: pickedUser.id,
                plan_id: pickedPlanId,
              });
              if (created && created.ok === false) {
                throw new Error(created.message || 'Could not create subscription.');
              }
              setOpen(false);
              onRefetch();
              window.setTimeout(() => onRefetch(), 350);
              onToast({ kind: 'success', text: 'Subscription created.' });
            } catch (e) {
              onToast({ kind: 'error', text: getErrorSummary(e) || 'Could not create subscription.' });
            } finally {
              setCreating(false);
            }
          }}
        >
          <div className="mb-5 grid gap-4 sm:grid-cols-2">
            <div className="text-sm text-slate-600 dark:text-slate-400">
              <div className="mb-1">User</div>
              <div className="relative">
                <input
                  value={pickedUser ? `${pickedUser.name || pickedUser.slug || `User #${pickedUser.id}`} · ${pickedUser.email}` : userQuery}
                  onChange={(e) => {
                    setPickedUser(null);
                    setUserQuery(e.target.value);
                    setUserOpen(true);
                  }}
                  onFocus={() => setUserOpen(true)}
                  placeholder="Search users by name or email…"
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
                {userOpen ? (
                  <div className="absolute z-20 mt-2 max-h-64 w-full overflow-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                    {userLoading ? (
                      <div className="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">Searching…</div>
                    ) : userQuery.trim().length < 2 ? (
                      <div className="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">Type at least 2 characters.</div>
                    ) : userResults.length === 0 ? (
                      <div className="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">No users found.</div>
                    ) : (
                      userResults.map((u) => (
                        <button
                          key={u.id}
                          type="button"
                          className="flex w-full items-start justify-between gap-3 rounded-lg px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800"
                          onClick={() => {
                            setPickedUser(u);
                            setUserOpen(false);
                          }}
                        >
                          <div className="min-w-0">
                            <div className="truncate font-semibold text-slate-900 dark:text-white">
                              {u.name || u.slug || `User #${u.id}`}
                            </div>
                            <div className="truncate text-xs text-slate-500 dark:text-slate-400">{u.email} · #{u.id}</div>
                          </div>
                        </button>
                      ))
                    )}
                  </div>
                ) : null}
              </div>
              {pickedUser ? (
                <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Selected user: <span className="font-semibold">#{pickedUser.id}</span>
                </div>
              ) : null}
            </div>

            <div className="text-sm text-slate-600 dark:text-slate-400">
              <div className="mb-1">Plan</div>
              <div className="relative">
                <input
                  value={
                    pickedPlanId > 0 && planById.get(pickedPlanId)
                      ? `${planById.get(pickedPlanId)!.name} · ${planById.get(pickedPlanId)!.amount.toFixed(2)} ${
                          planById.get(pickedPlanId)!.currency
                        }/${planById.get(pickedPlanId)!.interval_unit} · #${pickedPlanId}`
                      : planQuery
                  }
                  onChange={(e) => {
                    setPickedPlanId(0);
                    setPlanQuery(e.target.value);
                    setPlanOpen(true);
                  }}
                  onFocus={() => setPlanOpen(true)}
                  placeholder="Search plans…"
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
                {planOpen ? (
                  <div className="absolute z-20 mt-2 max-h-64 w-full overflow-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                    {filteredPlans.length === 0 ? (
                      <div className="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">No plans found.</div>
                    ) : (
                      filteredPlans.map((p) => (
                        <button
                          key={p.id}
                          type="button"
                          className="flex w-full items-start justify-between gap-3 rounded-lg px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800"
                          onClick={() => {
                            setPickedPlanId(p.id);
                            setPlanOpen(false);
                          }}
                        >
                          <div className="min-w-0">
                            <div className="truncate font-semibold text-slate-900 dark:text-white">{p.name}</div>
                            <div className="truncate text-xs text-slate-500 dark:text-slate-400">
                              {p.amount.toFixed(2)} {p.currency}/{p.interval_unit} · #{p.id}
                            </div>
                          </div>
                        </button>
                      ))
                    )}
                  </div>
                ) : null}
              </div>
            </div>
          </div>
        </form>
      </Modal>
    </>
  );
}
