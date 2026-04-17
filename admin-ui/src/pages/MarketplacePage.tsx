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

type Vendor = {
  id: number;
  user_id: number;
  store_slug: string;
  display_name: string;
  status: string;
};

type Commission = {
  id: number;
  order_id: number;
  vendor_user_id: number;
  amount: number;
  rate: number;
  status: string;
};

type VendorsResp = { ok?: boolean; vendors?: Vendor[] };
type CommResp = { ok?: boolean; rows?: Commission[]; sum?: number };

export function MarketplacePage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const lic = getLicensing(config);
  const featureOk = isFeatureEnabled(config, 'marketplace_multivendor');
  const addon = useAddonEnabled('marketplace_multivendor');
  const enabled = featureOk && Boolean(addon.enabled);
  const [tab, setTab] = useState<'vendors' | 'commissions' | 'withdraw'>('vendors');
  const [slug, setSlug] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [bio, setBio] = useState('');
  const [withdrawAmount, setWithdrawAmount] = useState('');
  const [withdrawNotes, setWithdrawNotes] = useState('');
  const [saving, setSaving] = useState(false);
  const [vendorMsg, setVendorMsg] = useState<string | null>(null);
  const [withdrawMsg, setWithdrawMsg] = useState<string | null>(null);

  const vendorsLoader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, vendors: [] as Vendor[] };
    }
    return getSikshyaApi().get<VendorsResp>(SIKSHYA_ENDPOINTS.elite.vendors);
  }, [enabled]);

  const commLoader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, rows: [] as Commission[], sum: 0 };
    }
    return getSikshyaApi().get<CommResp>(SIKSHYA_ENDPOINTS.elite.commissionsReport);
  }, [enabled]);

  const v = useAsyncData(vendorsLoader, [enabled]);
  const c = useAsyncData(commLoader, [enabled]);

  const registerVendor = async (e: FormEvent) => {
    e.preventDefault();
    setVendorMsg(null);
    setSaving(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.elite.vendors, {
        store_slug: slug.trim(),
        display_name: displayName.trim(),
        bio: bio.trim(),
      });
      setVendorMsg('Vendor profile saved.');
      v.refetch();
    } catch (err) {
      setVendorMsg(err instanceof Error ? err.message : 'Failed');
    } finally {
      setSaving(false);
    }
  };

  const requestWithdrawal = async (e: FormEvent) => {
    e.preventDefault();
    setWithdrawMsg(null);
    setSaving(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.elite.withdrawals, {
        amount: parseFloat(withdrawAmount) || 0,
        notes: withdrawNotes,
      });
      setWithdrawMsg('Withdrawal request submitted.');
      setWithdrawAmount('');
      setWithdrawNotes('');
    } catch (err) {
      setWithdrawMsg(err instanceof Error ? err.message : 'Failed');
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
      subtitle="Vendor profiles, platform commission tracking, and withdrawal requests — explained in everyday language on each tab."
    >
      {!featureOk ? (
        <FeatureUpsell
          title="Marketplace"
          description="Let instructors sell from storefronts, split commissions automatically, and process withdrawals with a clear audit trail."
          licensing={lic}
        />
      ) : !enabled ? (
        <AddonEnablePanel
          title="Marketplace is not enabled"
          description="Enable the Marketplace addon to register vendor routes and unlock multi-vendor selling."
          canEnable={Boolean(addon.licenseOk)}
          enableBusy={addon.loading}
          onEnable={() => void addon.enable()}
          upgradeUrl={lic.upgradeUrl}
          error={addon.error}
        />
      ) : (
        <>
          <div className="mb-4 flex flex-wrap gap-2">
            {(
              [
                { id: 'vendors' as const, label: 'Vendors', hint: 'Who can sell' },
                { id: 'commissions' as const, label: 'Commissions', hint: 'Platform share per order' },
                { id: 'withdraw' as const, label: 'Withdraw', hint: 'Request a payout' },
              ] as const
            ).map((t) => (
              <button
                key={t.id}
                type="button"
                title={t.hint}
                onClick={() => setTab(t.id)}
                className={`rounded-lg px-4 py-2 text-sm font-medium ${
                  tab === t.id
                    ? 'bg-brand-600 text-white dark:bg-brand-500'
                    : 'border border-slate-200 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'
                }`}
              >
                {t.label}
              </button>
            ))}
            <ButtonPrimary type="button" onClick={() => { v.refetch(); c.refetch(); }}>
              Refresh data
            </ButtonPrimary>
          </div>

          {tab === 'vendors' ? (
            <>
              {v.error ? <ApiErrorPanel error={v.error} title="Vendors" onRetry={() => v.refetch()} /> : null}
              <form
                onSubmit={registerVendor}
                className="mb-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
              >
                <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Register vendor profile</h2>
                <p className="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                  A vendor is simply the WordPress user who “owns” marketplace courses. The store slug becomes part of
                  their public URL — use lowercase letters and dashes only (for example <span className="font-mono">jane-teaches</span>
                  ).
                </p>
                <div className="mt-4 grid gap-4 sm:grid-cols-3">
                  <label className="text-sm text-slate-600 dark:text-slate-400">
                    Store slug
                    <input
                      required
                      value={slug}
                      onChange={(e) => setSlug(e.target.value)}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                    />
                  </label>
                  <label className="text-sm text-slate-600 dark:text-slate-400">
                    Display name
                    <input
                      required
                      value={displayName}
                      onChange={(e) => setDisplayName(e.target.value)}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                    />
                  </label>
                  <label className="text-sm text-slate-600 dark:text-slate-400 sm:col-span-3">
                    Bio
                    <textarea
                      value={bio}
                      onChange={(e) => setBio(e.target.value)}
                      rows={2}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                    />
                  </label>
                </div>
                <ButtonPrimary type="submit" className="mt-4" disabled={saving}>
                  {saving ? 'Saving…' : 'Save profile'}
                </ButtonPrimary>
                {vendorMsg ? <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">{vendorMsg}</p> : null}
              </form>
              <ListPanel>
                {v.loading ? (
                  <div className="p-8 text-center text-sm text-slate-500">Loading…</div>
                ) : (v.data?.vendors?.length ?? 0) === 0 ? (
                  <ListEmptyState title="No vendors" description="Create a vendor profile to start selling on the marketplace." />
                ) : (
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                      <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                        <tr>
                          <th className="px-5 py-3.5">Slug</th>
                          <th className="px-5 py-3.5">Name</th>
                          <th className="px-5 py-3.5">User</th>
                          <th className="px-5 py-3.5">Status</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {(v.data?.vendors ?? []).map((r) => (
                          <tr key={r.id} className="bg-white dark:bg-slate-900">
                            <td className="px-5 py-3.5 font-mono text-xs">{r.store_slug}</td>
                            <td className="px-5 py-3.5">{r.display_name}</td>
                            <td className="px-5 py-3.5">{r.user_id}</td>
                            <td className="px-5 py-3.5 capitalize">{r.status}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </ListPanel>
            </>
          ) : null}

          {tab === 'commissions' ? (
            <>
              {c.error ? <ApiErrorPanel error={c.error} title="Commissions" onRetry={() => c.refetch()} /> : null}
              <p className="mb-4 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                Each row is the platform’s share of a paid order (not the full sale price). Sikshya picks the vendor from
                the course author unless you set a custom vendor on the course. The percentage comes from course settings
                (defaults to 10% if you leave it blank).
              </p>
              <div className="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-900">
                Total accrued:{' '}
                <span className="font-semibold tabular-nums text-slate-900 dark:text-white">
                  {(c.data?.sum ?? 0).toFixed(2)}
                </span>
              </div>
              <ListPanel>
                {c.loading ? (
                  <div className="p-8 text-center text-sm text-slate-500">Loading…</div>
                ) : (c.data?.rows?.length ?? 0) === 0 ? (
                  <ListEmptyState
                    title="No commission rows yet"
                    description="When learners buy marketplace courses, you will see one line per order (grouped by vendor). Run a test purchase while logged out as a student to see your first row."
                  />
                ) : (
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                      <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                        <tr>
                          <th className="px-5 py-3.5">Order</th>
                          <th className="px-5 py-3.5">Vendor</th>
                          <th className="px-5 py-3.5">Amount</th>
                          <th className="px-5 py-3.5">Rate %</th>
                          <th className="px-5 py-3.5">Status</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {(c.data?.rows ?? []).map((r) => (
                          <tr key={r.id} className="bg-white dark:bg-slate-900">
                            <td className="px-5 py-3.5">{r.order_id}</td>
                            <td className="px-5 py-3.5">{r.vendor_user_id}</td>
                            <td className="px-5 py-3.5 tabular-nums">{r.amount.toFixed(2)}</td>
                            <td className="px-5 py-3.5 tabular-nums">{r.rate.toFixed(2)}</td>
                            <td className="px-5 py-3.5 capitalize">{r.status}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </ListPanel>
            </>
          ) : null}

          {tab === 'withdraw' ? (
            <form
              onSubmit={requestWithdrawal}
              className="max-w-md rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
            >
              <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Request withdrawal</h2>
              <p className="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                Use this form to tell the site owner how much you would like paid out. A human or payment processor still
                marks it as paid — this is the paper trail.
              </p>
              <label className="mt-4 block text-sm text-slate-600 dark:text-slate-400">
                Amount
                <input
                  required
                  type="number"
                  step="0.01"
                  min={0}
                  value={withdrawAmount}
                  onChange={(e) => setWithdrawAmount(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <label className="mt-3 block text-sm text-slate-600 dark:text-slate-400">
                Notes
                <textarea
                  value={withdrawNotes}
                  onChange={(e) => setWithdrawNotes(e.target.value)}
                  rows={2}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                />
              </label>
              <ButtonPrimary type="submit" className="mt-4" disabled={saving}>
                {saving ? 'Submitting…' : 'Submit request'}
              </ButtonPrimary>
              {withdrawMsg ? <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">{withdrawMsg}</p> : null}
            </form>
          ) : null}
        </>
      )}
    </AppShell>
  );
}
