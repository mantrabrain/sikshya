import { useCallback, useMemo, useState, type FormEvent } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { RowActionsMenu, type RowActionItem } from '../components/shared/list/RowActionsMenu';
import { StatusBadge } from '../components/shared/list/StatusBadge';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import type { SikshyaReactConfig } from '../types';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';
import { __ } from '../lib/i18n';

type OverviewResp = {
  ok?: boolean;
  totals?: { platform_commission: number; vendor_net: number; gross: number; count: number };
  pending_withdrawals?: number;
  vendor_counts?: { all: number; pending: number; active: number; suspended: number };
  currency?: string;
};

type Vendor = {
  id: number;
  user_id: number;
  user_email: string;
  user_display: string;
  store_slug: string;
  display_name: string;
  bio?: string;
  status: 'pending' | 'active' | 'suspended' | string;
  payout_method?: string;
  commission_override?: number | null;
  created_at: string;
  updated_at: string;
};

type VendorsResp = {
  ok?: boolean;
  rows?: Vendor[];
  total?: number;
  page?: number;
  per_page?: number;
};

type Commission = {
  id: number;
  order_id: number;
  course_id: number;
  course_title: string;
  vendor_user_id: number;
  vendor_name: string;
  currency: string;
  gross: number;
  platform_fee: number;
  platform_commission: number;
  vendor_net: number;
  rate: number;
  status: string;
  available_at: string;
  paid_at: string;
  created_at: string;
};

type CommissionsResp = {
  ok?: boolean;
  rows?: Commission[];
  total?: number;
  totals?: { platform_commission: number; vendor_net: number; gross: number; count: number };
  currency?: string;
};

type Withdrawal = {
  id: number;
  vendor_user_id: number;
  vendor_name: string;
  amount: number;
  currency: string;
  method: string;
  status: 'pending' | 'approved' | 'rejected' | 'paid' | 'cancelled' | string;
  notes?: string;
  admin_notes?: string;
  created_at: string;
  decided_at?: string;
  paid_at?: string;
};

type WithdrawalsResp = {
  ok?: boolean;
  rows?: Withdrawal[];
  total?: number;
  currency?: string;
};

type TabId = 'overview' | 'vendors' | 'commissions' | 'withdrawals';

const TABS: { id: TabId; label: string; hint: string }[] = [
  { id: 'overview', label: 'Overview', hint: 'Platform earnings + queue' },
  { id: 'vendors', label: 'Vendors', hint: 'Storefront accounts' },
  { id: 'commissions', label: 'Commissions', hint: 'Per-line ledger' },
  { id: 'withdrawals', label: 'Withdrawals', hint: 'Vendor payouts' },
];

const VENDOR_STATUS_LABEL: Record<string, string> = {
  pending: 'Pending',
  active: 'Active',
  suspended: 'Suspended',
};

const COMMISSION_STATUS_LABEL: Record<string, string> = {
  accrued: 'Pending hold',
  available: 'Available',
  paid: 'Paid',
  reversed: 'Reversed',
};

const WITHDRAWAL_STATUS_LABEL: Record<string, string> = {
  pending: 'Pending',
  approved: 'Approved',
  rejected: 'Rejected',
  paid: 'Paid',
  cancelled: 'Cancelled',
};

function fmtCurrency(amount: number, currency: string): string {
  if (Number.isNaN(amount)) {
    return `0.00 ${currency}`;
  }
  return `${amount.toFixed(2)} ${currency}`;
}

/**
 * Marketplace status pill — delegates to shared StatusBadge. `status` is
 * looked up in the canonical map (which already covers active/pending/paid/
 * rejected/reversed/suspended/cancelled/accrued/approved) so the tone is
 * picked correctly without per-page color logic. The translated `label`
 * comes from the marketplace's own status-label maps.
 */
function StatusPill({ status, kind }: { status: string; kind: 'vendor' | 'commission' | 'withdrawal' }) {
  const label =
    kind === 'vendor'
      ? VENDOR_STATUS_LABEL[status] || status
      : kind === 'commission'
      ? COMMISSION_STATUS_LABEL[status] || status
      : WITHDRAWAL_STATUS_LABEL[status] || status;

  return <StatusBadge status={status} label={label} />;
}

function StatTile(props: { label: string; value: string; hint?: string }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
      <div className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{props.label}</div>
      <div className="mt-1 text-xl font-semibold tabular-nums text-slate-900 dark:text-white">{props.value}</div>
      {props.hint ? <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">{props.hint}</div> : null}
    </div>
  );
}

export function MarketplacePage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const featureOk = isFeatureEnabled(config, 'marketplace_multivendor');
  const addon = useAddonEnabled('marketplace_multivendor');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';
  const [tab, setTab] = useState<TabId>('overview');
  const toast = useTopRightToast(2600);
  const pushToast = useCallback(
    (t: { kind: 'success' | 'error'; text: string } | null) => {
      if (!t) {
        toast.clear();
        return;
      }
      if (t.kind === 'success') {
        toast.success(__('Success', 'sikshya'), t.text);
      } else {
        toast.error(__('Error', 'sikshya'), t.text);
      }
    },
    [toast]
  );

  const overviewLoader = useCallback(async () => {
    if (!enabled) {
      return null;
    }
    return getSikshyaApi().get<OverviewResp>(SIKSHYA_ENDPOINTS.marketplace.admin.overview);
  }, [enabled]);

  const overview = useAsyncData(overviewLoader, [enabled]);
  const currency = overview.data?.currency ?? 'USD';

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle={__('Run a multi-vendor LMS marketplace: track per-line commissions, manage vendor storefronts, and process withdrawals from one workspace.', 'sikshya')}
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="marketplace_multivendor"
        config={config}
        featureTitle={__('Marketplace', 'sikshya')}
        featureDescription={__('Let instructors sell from storefronts, split commissions automatically, and process withdrawals with a clear audit trail.', 'sikshya')}
        previewVariant="cards"
        addonEnableTitle={__('Marketplace is not enabled', 'sikshya')}
        addonEnableDescription={__('Enable the Marketplace addon to register vendor routes and unlock multi-vendor selling.', 'sikshya')}
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => addon.enable()}
        addonError={addon.error}
      >
        <>
          <TopRightToast toast={toast.toast} onDismiss={toast.clear} />

          <div className="mb-5 flex flex-wrap items-center gap-2">
            {TABS.map((t) => (
              <button
                key={t.id}
                type="button"
                title={t.hint}
                onClick={() => setTab(t.id)}
                className={`rounded-lg px-3.5 py-2 text-sm font-medium transition-colors ${
                  tab === t.id
                    ? 'bg-brand-600 text-white shadow-sm dark:bg-brand-500'
                    : 'border border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'
                }`}
              >
                {t.label}
              </button>
            ))}
            <ButtonSecondary
              type="button"
              onClick={() => {
                void overview.refetch();
              }}
              className="ml-auto"
            >
              Refresh
            </ButtonSecondary>
          </div>

          {tab === 'overview' ? (
            <OverviewPanel
              loading={overview.loading}
              data={overview.data}
              error={overview.error}
              onRefresh={() => void overview.refetch()}
              currency={currency}
            />
          ) : null}
          {tab === 'vendors' ? <VendorsPanel enabled={enabled} setToast={pushToast} /> : null}
          {tab === 'commissions' ? <CommissionsPanel enabled={enabled} /> : null}
          {tab === 'withdrawals' ? <WithdrawalsPanel enabled={enabled} setToast={pushToast} /> : null}
        </>
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}

function OverviewPanel(props: {
  loading: boolean;
  data: OverviewResp | null | undefined;
  error: unknown;
  onRefresh: () => void;
  currency: string;
}) {
  const { loading, data, error, onRefresh, currency } = props;
  if (error) {
    return <ApiErrorPanel error={error} title={__('Could not load marketplace overview', 'sikshya')} onRetry={onRefresh} />;
  }
  if (loading) {
    return <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">{__('Loading overview…', 'sikshya')}</div>;
  }
  if (!data) {
    return null;
  }
  const totals = data.totals ?? { platform_commission: 0, vendor_net: 0, gross: 0, count: 0 };
  const vendors = data.vendor_counts ?? { all: 0, pending: 0, active: 0, suspended: 0 };
  return (
    <div className="grid gap-4">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile label="Platform commission" value={fmtCurrency(totals.platform_commission, currency)} hint="All-time, excluding reversed" />
        <StatTile label="Vendor net" value={fmtCurrency(totals.vendor_net, currency)} hint="Earned by all vendors" />
        <StatTile label="Gross sales" value={fmtCurrency(totals.gross, currency)} hint={`${totals.count} commission lines`} />
        <StatTile label="Pending withdrawals" value={String(data.pending_withdrawals ?? 0)} hint="Awaiting your decision" />
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatTile label="Vendors total" value={String(vendors.all ?? 0)} />
        <StatTile label="Active" value={String(vendors.active ?? 0)} />
        <StatTile label="Pending review" value={String(vendors.pending ?? 0)} />
        <StatTile label="Suspended" value={String(vendors.suspended ?? 0)} />
      </div>
      <div className="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-400">
        <strong className="text-slate-800 dark:text-slate-200">{__('Tip:', 'sikshya')}</strong> Commission rows accrue immediately when a paid order fulfills, but only become withdrawable once the hold period passes. You can adjust the hold period in Marketplace settings.
      </div>
    </div>
  );
}

function VendorsPanel(props: { enabled: boolean; setToast: (t: { kind: 'success' | 'error'; text: string } | null) => void }) {
  const { enabled, setToast: pushToast } = props;
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<string>('');
  const [search, setSearch] = useState('');
  const [busy, setBusy] = useState<number | null>(null);

  const queryKey = useMemo(() => `${page}|${status}|${search}`, [page, status, search]);

  const loader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, rows: [] as Vendor[], total: 0 };
    }
    return getSikshyaApi().get<VendorsResp>(
      SIKSHYA_ENDPOINTS.marketplace.admin.vendors({
        page,
        per_page: 25,
        status: status || undefined,
        search: search.trim() || undefined,
      })
    );
  }, [enabled, page, status, search]);

  const v = useAsyncData(loader, [queryKey]);
  const rows = v.data?.rows ?? [];
  const total = v.data?.total ?? 0;

  const setVendorStatus = async (vendor: Vendor, next: 'active' | 'pending' | 'suspended') => {
    setBusy(vendor.id);
    pushToast(null);
    try {
      await getSikshyaApi().put<{ ok?: boolean }>(
        SIKSHYA_ENDPOINTS.marketplace.admin.vendor(vendor.id),
        { status: next }
      );
      pushToast({
        kind: 'success',
        text: `Vendor ${vendor.display_name || vendor.user_display} marked ${next}.`,
      });
      void v.refetch();
    } catch (err) {
      pushToast({
        kind: 'error',
        text: err instanceof Error ? err.message : 'Failed to update vendor.',
      });
    } finally {
      setBusy(null);
    }
  };

  return (
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
              <option value="">{__('All', 'sikshya')}</option>
              <option value="pending">{__('Pending', 'sikshya')}</option>
              <option value="active">{__('Active', 'sikshya')}</option>
              <option value="suspended">{__('Suspended', 'sikshya')}</option>
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
              placeholder={__('Name or store slug…', 'sikshya')}
              className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            />
          </label>
        </div>
        <p className="text-xs text-slate-500 dark:text-slate-400">{total ? `${total} vendor${total === 1 ? '' : 's'}` : null}</p>
      </div>

      {v.error ? (
        <div className="mt-4">
          <ApiErrorPanel error={v.error} title={__('Could not load vendors', 'sikshya')} onRetry={() => void v.refetch()} />
        </div>
      ) : v.loading ? (
        <p className="mt-6 text-sm text-slate-500">{__('Loading…', 'sikshya')}</p>
      ) : rows.length === 0 ? (
        <div className="mt-6">
          <ListEmptyState
            title={__('No vendors yet', 'sikshya')}
            description={__('Vendors are created automatically when an instructor saves their marketplace profile, or when an order with a course you authored fulfills.', 'sikshya')}
          />
        </div>
      ) : (
        <div className="mt-4 overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
              <tr>
                <th className="px-4 py-3">{__('Vendor', 'sikshya')}</th>
                <th className="px-4 py-3">{__('Store', 'sikshya')}</th>
                <th className="px-4 py-3">{__('Status', 'sikshya')}</th>
                <th className="px-4 py-3">Commission %</th>
                <th className="px-4 py-3">{__('Joined', 'sikshya')}</th>
                <th className="w-12 px-4 py-3 text-right">
                  <span className="sr-only">{__('Actions', 'sikshya')}</span>
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {rows.map((r) => (
                <tr key={r.id} className="bg-white dark:bg-slate-900">
                  <td className="px-4 py-3">
                    <div className="font-medium text-slate-900 dark:text-white">{r.display_name || r.user_display || `User #${r.user_id}`}</div>
                    <div className="text-xs text-slate-500">{r.user_email}</div>
                  </td>
                  <td className="px-4 py-3 font-mono text-xs">{r.store_slug || '—'}</td>
                  <td className="px-4 py-3"><StatusPill kind="vendor" status={r.status} /></td>
                  <td className="px-4 py-3 tabular-nums">{r.commission_override !== null && r.commission_override !== undefined ? `${r.commission_override.toFixed(2)} %` : <span className="text-slate-400">{__('default', 'sikshya')}</span>}</td>
                  <td className="px-4 py-3 text-slate-500">{r.created_at ? r.created_at.replace(' ', ' · ') : '—'}</td>
                  <td className="w-12 px-4 py-3 text-right">
                    {(() => {
                      const items: RowActionItem[] = [];
                      if (r.status !== 'active') {
                        items.push({
                          key: 'activate',
                          label: __('Activate', 'sikshya'),
                          onClick: () => void setVendorStatus(r, 'active'),
                          disabled: busy === r.id,
                        });
                      }
                      if (r.status !== 'suspended') {
                        items.push({
                          key: 'suspend',
                          label: __('Suspend', 'sikshya'),
                          onClick: () => void setVendorStatus(r, 'suspended'),
                          danger: true,
                          disabled: busy === r.id,
                        });
                      }
                      return <RowActionsMenu items={items} ariaLabel={__('Vendor actions', 'sikshya')} />;
                    })()}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </ListPanel>
  );
}

function CommissionsPanel(props: { enabled: boolean }) {
  const { enabled } = props;
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('');
  const [vendorId, setVendorId] = useState('');

  const queryKey = useMemo(() => `${page}|${status}|${vendorId}`, [page, status, vendorId]);

  const loader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, rows: [] as Commission[], total: 0 };
    }
    return getSikshyaApi().get<CommissionsResp>(
      SIKSHYA_ENDPOINTS.marketplace.admin.commissions({
        page,
        per_page: 50,
        status: status || undefined,
        vendor_user_id: vendorId ? Number(vendorId) : undefined,
      })
    );
  }, [enabled, page, status, vendorId]);

  const c = useAsyncData(loader, [queryKey]);
  const rows = c.data?.rows ?? [];
  const totals = c.data?.totals ?? { platform_commission: 0, vendor_net: 0, gross: 0, count: 0 };
  const currency = c.data?.currency ?? 'USD';

  return (
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
              <option value="">{__('All', 'sikshya')}</option>
              <option value="accrued">{__('Accrued (in hold)', 'sikshya')}</option>
              <option value="available">{__('Available', 'sikshya')}</option>
              <option value="paid">{__('Paid out', 'sikshya')}</option>
              <option value="reversed">{__('Reversed', 'sikshya')}</option>
            </select>
          </label>
          <label className="block text-sm text-slate-700 dark:text-slate-300">
            Vendor user ID
            <input
              value={vendorId}
              onChange={(e) => {
                setPage(1);
                setVendorId(e.target.value.replace(/[^0-9]/g, ''));
              }}
              placeholder={__('e.g. 12', 'sikshya')}
              className="mt-1 w-32 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            />
          </label>
        </div>
        <div className="grid gap-1 text-right text-xs text-slate-500 dark:text-slate-400">
          <span>{__('Platform:', 'sikshya')}<strong className="text-slate-900 dark:text-white">{fmtCurrency(totals.platform_commission, currency)}</strong></span>
          <span>{__('Vendors:', 'sikshya')}<strong className="text-slate-900 dark:text-white">{fmtCurrency(totals.vendor_net, currency)}</strong></span>
          <span>{__('Gross:', 'sikshya')}<strong className="text-slate-900 dark:text-white">{fmtCurrency(totals.gross, currency)}</strong></span>
        </div>
      </div>

      {c.error ? (
        <div className="mt-4">
          <ApiErrorPanel error={c.error} title={__('Could not load commissions', 'sikshya')} onRetry={() => void c.refetch()} />
        </div>
      ) : c.loading ? (
        <p className="mt-6 text-sm text-slate-500">{__('Loading…', 'sikshya')}</p>
      ) : rows.length === 0 ? (
        <div className="mt-6">
          <ListEmptyState
            title={__('No commission rows yet', 'sikshya')}
            description={__('When a marketplace order fulfills, Sikshya creates one row per line item showing gross, fees, platform commission, and vendor net.', 'sikshya')}
          />
        </div>
      ) : (
        <div className="mt-4 overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
              <tr>
                <th className="px-4 py-3">{__('When', 'sikshya')}</th>
                <th className="px-4 py-3">{__('Order #', 'sikshya')}</th>
                <th className="px-4 py-3">{__('Course', 'sikshya')}</th>
                <th className="px-4 py-3">{__('Vendor', 'sikshya')}</th>
                <th className="px-4 py-3 text-right">{__('Gross', 'sikshya')}</th>
                <th className="px-4 py-3 text-right">{__('Fee', 'sikshya')}</th>
                <th className="px-4 py-3 text-right">{__('Platform', 'sikshya')}</th>
                <th className="px-4 py-3 text-right">{__('Vendor net', 'sikshya')}</th>
                <th className="px-4 py-3">{__('Status', 'sikshya')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {rows.map((r) => (
                <tr key={r.id} className="bg-white dark:bg-slate-900">
                  <td className="px-4 py-3 text-slate-500">{r.created_at}</td>
                  <td className="px-4 py-3">#{r.order_id}</td>
                  <td className="px-4 py-3">{r.course_title || `Course #${r.course_id}`}</td>
                  <td className="px-4 py-3">{r.vendor_name || `User #${r.vendor_user_id}`}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{r.gross.toFixed(2)}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{r.platform_fee.toFixed(2)}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{r.platform_commission.toFixed(2)}</td>
                  <td className="px-4 py-3 text-right tabular-nums font-medium">{r.vendor_net.toFixed(2)}</td>
                  <td className="px-4 py-3"><StatusPill kind="commission" status={r.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </ListPanel>
  );
}

function WithdrawalsPanel(props: { enabled: boolean; setToast: (t: { kind: 'success' | 'error'; text: string } | null) => void }) {
  const { enabled, setToast: pushToast } = props;
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<string>('pending');
  const [busy, setBusy] = useState<number | null>(null);
  const [adjustVendor, setAdjustVendor] = useState('');
  const [adjustAmount, setAdjustAmount] = useState('');
  const [adjustReason, setAdjustReason] = useState('');
  const [adjustBusy, setAdjustBusy] = useState(false);

  const queryKey = useMemo(() => `${page}|${status}`, [page, status]);

  const loader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, rows: [] as Withdrawal[], total: 0 };
    }
    return getSikshyaApi().get<WithdrawalsResp>(
      SIKSHYA_ENDPOINTS.marketplace.admin.withdrawals({
        page,
        per_page: 25,
        status: status || undefined,
      })
    );
  }, [enabled, page, status]);

  const w = useAsyncData(loader, [queryKey]);
  const rows = w.data?.rows ?? [];
  const total = w.data?.total ?? 0;

  const act = async (id: number, action: 'approve' | 'reject' | 'mark-paid') => {
    setBusy(id);
    pushToast(null);
    try {
      const path =
        action === 'approve'
          ? SIKSHYA_ENDPOINTS.marketplace.admin.withdrawalApprove(id)
          : action === 'reject'
          ? SIKSHYA_ENDPOINTS.marketplace.admin.withdrawalReject(id)
          : SIKSHYA_ENDPOINTS.marketplace.admin.withdrawalMarkPaid(id);
      await getSikshyaApi().post(path, {});
      pushToast({ kind: 'success', text: `Withdrawal #${id} ${action.replace('-', ' ')}.` });
      void w.refetch();
    } catch (err) {
      pushToast({
        kind: 'error',
        text: err instanceof Error ? err.message : 'Action failed.',
      });
    } finally {
      setBusy(null);
    }
  };

  const submitAdjustment = async (e: FormEvent) => {
    e.preventDefault();
    setAdjustBusy(true);
    pushToast(null);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.marketplace.admin.adjustments, {
        vendor_user_id: Number(adjustVendor) || 0,
        amount: parseFloat(adjustAmount) || 0,
        reason: adjustReason.trim(),
      });
      pushToast({ kind: 'success', text: 'Adjustment recorded.' });
      setAdjustAmount('');
      setAdjustReason('');
    } catch (err) {
      pushToast({
        kind: 'error',
        text: err instanceof Error ? err.message : 'Could not record adjustment.',
      });
    } finally {
      setAdjustBusy(false);
    }
  };

  return (
    <div className="grid gap-5">
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
                <option value="">{__('All', 'sikshya')}</option>
                <option value="pending">{__('Pending', 'sikshya')}</option>
                <option value="approved">{__('Approved', 'sikshya')}</option>
                <option value="paid">{__('Paid', 'sikshya')}</option>
                <option value="rejected">{__('Rejected', 'sikshya')}</option>
                <option value="cancelled">{__('Cancelled', 'sikshya')}</option>
              </select>
            </label>
          </div>
          <p className="text-xs text-slate-500 dark:text-slate-400">{total ? `${total} request${total === 1 ? '' : 's'}` : null}</p>
        </div>

        {w.error ? (
          <div className="mt-4">
            <ApiErrorPanel error={w.error} title={__('Could not load withdrawals', 'sikshya')} onRetry={() => void w.refetch()} />
          </div>
        ) : w.loading ? (
          <p className="mt-6 text-sm text-slate-500">{__('Loading…', 'sikshya')}</p>
        ) : rows.length === 0 ? (
          <div className="mt-6">
            <ListEmptyState title={__('No withdrawal requests', 'sikshya')} description={__('Vendors can request a payout from their account once their balance is above the minimum withdrawal.', 'sikshya')} />
          </div>
        ) : (
          <div className="mt-4 overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
              <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800">
                <tr>
                  <th className="px-4 py-3">{__('Requested', 'sikshya')}</th>
                  <th className="px-4 py-3">{__('Vendor', 'sikshya')}</th>
                  <th className="px-4 py-3 text-right">{__('Amount', 'sikshya')}</th>
                  <th className="px-4 py-3">{__('Method', 'sikshya')}</th>
                  <th className="px-4 py-3">{__('Status', 'sikshya')}</th>
                  <th className="w-12 px-4 py-3 text-right">
                  <span className="sr-only">{__('Actions', 'sikshya')}</span>
                </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {rows.map((r) => (
                  <tr key={r.id} className="bg-white dark:bg-slate-900">
                    <td className="px-4 py-3 text-slate-500">{r.created_at}</td>
                    <td className="px-4 py-3">{r.vendor_name || `User #${r.vendor_user_id}`}</td>
                    <td className="px-4 py-3 text-right tabular-nums">{fmtCurrency(r.amount, r.currency)}</td>
                    <td className="px-4 py-3 capitalize">{r.method?.replace(/_/g, ' ') || '—'}</td>
                    <td className="px-4 py-3"><StatusPill kind="withdrawal" status={r.status} /></td>
                    <td className="w-12 px-4 py-3 text-right">
                      {(() => {
                        const items: RowActionItem[] = [];
                        if (r.status === 'pending') {
                          items.push(
                            {
                              key: 'approve',
                              label: __('Approve', 'sikshya'),
                              onClick: () => void act(r.id, 'approve'),
                              disabled: busy === r.id,
                            },
                            {
                              key: 'reject',
                              label: __('Reject', 'sikshya'),
                              onClick: () => void act(r.id, 'reject'),
                              danger: true,
                              disabled: busy === r.id,
                            }
                          );
                        }
                        if (r.status === 'pending' || r.status === 'approved') {
                          items.push({
                            key: 'paid',
                            label: __('Mark paid', 'sikshya'),
                            onClick: () => void act(r.id, 'mark-paid'),
                            disabled: busy === r.id,
                          });
                        }
                        return items.length > 0 ? (
                          <RowActionsMenu items={items} ariaLabel={__('Withdrawal actions', 'sikshya')} />
                        ) : (
                          <span className="text-xs text-slate-400">—</span>
                        );
                      })()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </ListPanel>

      <ListPanel className="p-4">
        <h2 className="text-sm font-semibold text-slate-900 dark:text-white">{__('Manual adjustment', 'sikshya')}</h2>
        <p className="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
          Record a credit (positive) or a debit (negative) for a vendor — for refund clawbacks, bonuses, or one-off corrections. Adjustments affect the vendor's withdrawable balance immediately.
        </p>
        <form onSubmit={submitAdjustment} className="mt-3 grid gap-3 sm:grid-cols-3">
          <label className="text-sm text-slate-600 dark:text-slate-400">
            Vendor user ID
            <input
              required
              value={adjustVendor}
              onChange={(e) => setAdjustVendor(e.target.value.replace(/[^0-9]/g, ''))}
              className="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
            />
          </label>
          <label className="text-sm text-slate-600 dark:text-slate-400">
            Amount (use - for debit)
            <input
              required
              type="number"
              step="0.01"
              value={adjustAmount}
              onChange={(e) => setAdjustAmount(e.target.value)}
              className="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
            />
          </label>
          <label className="text-sm text-slate-600 dark:text-slate-400 sm:col-span-3">
            Reason
            <input
              required
              value={adjustReason}
              onChange={(e) => setAdjustReason(e.target.value)}
              placeholder={__('e.g. Refund clawback for order #1234', 'sikshya')}
              className="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
            />
          </label>
          <div className="sm:col-span-3">
            <ButtonPrimary type="submit" disabled={adjustBusy}>
              {adjustBusy ? __('Saving…', 'sikshya') : __('Record adjustment', 'sikshya')}
            </ButtonPrimary>
          </div>
        </form>
      </ListPanel>
    </div>
  );
}
