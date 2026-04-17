import { useCallback, useMemo, useState, type FormEvent } from 'react';
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

type Resp = { ok?: boolean; subscriptions?: SubRow[]; plans?: Plan[] };

export function SubscriptionsProPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const lic = getLicensing(config);
  const featureOk = isFeatureEnabled(config, 'subscriptions');
  const addon = useAddonEnabled('subscriptions');
  const enabled = featureOk && Boolean(addon.enabled);

  const loader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, subscriptions: [] as SubRow[], plans: [] as Plan[] };
    }
    const [subs, plans] = await Promise.all([
      getSikshyaApi().get<{ ok?: boolean; subscriptions?: SubRow[] }>(SIKSHYA_ENDPOINTS.pro.subscriptions),
      getSikshyaApi().get<{ ok?: boolean; plans?: Plan[] }>(SIKSHYA_ENDPOINTS.pro.plans),
    ]);
    return { ok: true, subscriptions: subs.subscriptions || [], plans: plans.plans || [] };
  }, [enabled]);

  const { loading, data, error, refetch } = useAsyncData(loader, [enabled]);
  const rows = Array.isArray(data?.subscriptions) ? data.subscriptions : [];
  const plans = Array.isArray(data?.plans) ? data.plans : [];
  const planById = useMemo(() => new Map(plans.map((p) => [p.id, p])), [plans]);

  const [newPlanName, setNewPlanName] = useState('');
  const [newPlanAmount, setNewPlanAmount] = useState('29');
  const [newPlanCurrency, setNewPlanCurrency] = useState('USD');
  const [newPlanInterval, setNewPlanInterval] = useState<'month' | 'year'>('month');
  const [savingPlan, setSavingPlan] = useState(false);
  const [subUserId, setSubUserId] = useState('');
  const [subPlanId, setSubPlanId] = useState('');
  const [creatingSub, setCreatingSub] = useState(false);

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
      {!featureOk ? (
        <FeatureUpsell
          title="Subscriptions"
          description="Sell monthly or yearly plans, sync status from payment webhooks, and expire access when billing stops."
          licensing={lic}
        />
      ) : !enabled ? (
        <AddonEnablePanel
          title="Subscriptions is not enabled"
          description="Enable the Subscriptions addon to register recurring billing routes and show subscription settings."
          canEnable={Boolean(addon.licenseOk)}
          enableBusy={addon.loading}
          onEnable={() => void addon.enable()}
          upgradeUrl={lic.upgradeUrl}
          error={addon.error}
        />
      ) : error ? (
        <ApiErrorPanel
          error={error}
          title="Could not load subscriptions"
          onRetry={() => refetch()}
        />
      ) : (
        <>
          <form
            className="mb-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
            onSubmit={async (e: FormEvent) => {
              e.preventDefault();
              setSavingPlan(true);
              try {
                await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.plans, {
                  name: newPlanName,
                  amount: parseFloat(newPlanAmount) || 0,
                  currency: newPlanCurrency || 'USD',
                  interval_unit: newPlanInterval,
                  status: 'active',
                });
                setNewPlanName('');
                refetch();
              } finally {
                setSavingPlan(false);
              }
            }}
          >
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Plans</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-5">
              <label className="text-sm text-slate-600 dark:text-slate-400 sm:col-span-2">
                Name
                <input
                  required
                  value={newPlanName}
                  onChange={(e) => setNewPlanName(e.target.value)}
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
                  value={newPlanAmount}
                  onChange={(e) => setNewPlanAmount(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <label className="text-sm text-slate-600 dark:text-slate-400">
                Currency
                <input
                  value={newPlanCurrency}
                  onChange={(e) => setNewPlanCurrency(e.target.value.toUpperCase())}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <label className="text-sm text-slate-600 dark:text-slate-400">
                Interval
                <select
                  value={newPlanInterval}
                  onChange={(e) => setNewPlanInterval(e.target.value === 'year' ? 'year' : 'month')}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                >
                  <option value="month">month</option>
                  <option value="year">year</option>
                </select>
              </label>
            </div>
            <div className="mt-4">
              <ButtonPrimary type="submit" disabled={savingPlan}>
                {savingPlan ? 'Saving…' : 'Create plan'}
              </ButtonPrimary>
            </div>
          </form>

          <form
            className="mb-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
            onSubmit={async (e: FormEvent) => {
              e.preventDefault();
              setCreatingSub(true);
              try {
                await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.subscriptions, {
                  user_id: parseInt(subUserId, 10),
                  plan_id: parseInt(subPlanId, 10),
                });
                setSubUserId('');
                setSubPlanId('');
                refetch();
              } finally {
                setCreatingSub(false);
              }
            }}
          >
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Create subscription (manual)</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-4">
              <label className="text-sm text-slate-600 dark:text-slate-400">
                User ID
                <input
                  required
                  type="number"
                  value={subUserId}
                  onChange={(e) => setSubUserId(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <label className="text-sm text-slate-600 dark:text-slate-400">
                Plan ID
                <input
                  required
                  type="number"
                  value={subPlanId}
                  onChange={(e) => setSubPlanId(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <div className="flex items-end sm:col-span-2">
                <ButtonPrimary type="submit" disabled={creatingSub}>
                  {creatingSub ? 'Creating…' : 'Create subscription'}
                </ButtonPrimary>
              </div>
            </div>
          </form>

          <ListPanel>
            {loading ? (
              <div className="p-8 text-center text-sm text-slate-500">Loading…</div>
            ) : rows.length === 0 ? (
              <ListEmptyState
                title="No subscriptions yet"
                description="Create a subscription manually, or connect a gateway to sync recurring billing."
              />
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                  <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                    <tr>
                      <th className="px-5 py-3.5">ID</th>
                      <th className="px-5 py-3.5">User</th>
                      <th className="px-5 py-3.5">Plan</th>
                      <th className="px-5 py-3.5">Status</th>
                      <th className="px-5 py-3.5">Gateway</th>
                      <th className="px-5 py-3.5">Period end</th>
                      <th className="px-5 py-3.5"></th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {rows.map((r) => (
                      <tr key={r.id} className="bg-white dark:bg-slate-900">
                        <td className="px-5 py-3.5">{r.id}</td>
                        <td className="px-5 py-3.5">{r.user_id}</td>
                        <td className="px-5 py-3.5">
                          {r.plan_id}
                          {planById.get(r.plan_id)?.name ? (
                            <span className="ml-2 text-slate-500">({planById.get(r.plan_id)!.name})</span>
                          ) : null}
                        </td>
                        <td className="px-5 py-3.5 capitalize">{r.status}</td>
                        <td className="px-5 py-3.5">{r.gateway}</td>
                        <td className="px-5 py-3.5 text-slate-600 dark:text-slate-400">{r.current_period_end || '—'}</td>
                        <td className="px-5 py-3.5">
                          {r.status === 'active' ? (
                            <button
                              type="button"
                              className="text-xs font-semibold text-rose-600 hover:underline"
                              onClick={async () => {
                                await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.subscriptionsCancel, { id: r.id });
                                refetch();
                              }}
                            >
                              Cancel
                            </button>
                          ) : null}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </ListPanel>
        </>
      )}
    </AppShell>
  );
}
