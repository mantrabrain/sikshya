import { useCallback } from 'react';
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

type SubRow = {
  id: number;
  user_id: number;
  plan_id: number;
  status: string;
  gateway: string;
  current_period_end: string | null;
};

type Resp = { ok?: boolean; subscriptions?: SubRow[] };

export function SubscriptionsProPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const lic = getLicensing(config);
  const featureOk = isFeatureEnabled(config, 'subscriptions');
  const addon = useAddonEnabled('subscriptions');
  const enabled = featureOk && Boolean(addon.enabled);

  const loader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, subscriptions: [] as SubRow[] };
    }
    return getSikshyaApi().get<Resp>(SIKSHYA_ENDPOINTS.pro.subscriptions);
  }, [enabled]);

  const { loading, data, error, refetch } = useAsyncData(loader, [enabled]);
  const rows = data?.subscriptions ?? [];

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
        <ListPanel>
          {loading ? (
            <div className="p-8 text-center text-sm text-slate-500">Loading…</div>
          ) : rows.length === 0 ? (
            <ListEmptyState
              title="No subscriptions yet"
              description="When recurring billing is connected, active subscriptions will appear here."
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
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {rows.map((r) => (
                    <tr key={r.id} className="bg-white dark:bg-slate-900">
                      <td className="px-5 py-3.5">{r.id}</td>
                      <td className="px-5 py-3.5">{r.user_id}</td>
                      <td className="px-5 py-3.5">{r.plan_id}</td>
                      <td className="px-5 py-3.5 capitalize">{r.status}</td>
                      <td className="px-5 py-3.5">{r.gateway}</td>
                      <td className="px-5 py-3.5 text-slate-600 dark:text-slate-400">{r.current_period_end || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </ListPanel>
      )}
    </AppShell>
  );
}
