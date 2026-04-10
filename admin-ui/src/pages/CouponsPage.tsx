import { useCallback, useState, type FormEvent } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAsyncData } from '../hooks/useAsyncData';
import type { NavItem, SikshyaReactConfig } from '../types';

type CouponRow = {
  id: number;
  code: string;
  discount_type: string;
  discount_value: number;
  max_uses: number;
  used_count: number;
  expires_at: string | null;
  status: string;
};

type ListResponse = {
  ok?: boolean;
  coupons?: CouponRow[];
  table_missing?: boolean;
};

export function CouponsPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const [code, setCode] = useState('');
  const [discountType, setDiscountType] = useState<'percent' | 'fixed'>('percent');
  const [discountValue, setDiscountValue] = useState('10');
  const [maxUses, setMaxUses] = useState('0');
  const [expiresAt, setExpiresAt] = useState('');
  const [saving, setSaving] = useState(false);
  const [saveMsg, setSaveMsg] = useState<string | null>(null);

  const loader = useCallback(async () => {
    return getSikshyaApi().get<ListResponse>(SIKSHYA_ENDPOINTS.admin.coupons);
  }, []);

  const { loading, data, error, refetch } = useAsyncData(loader, []);
  const rows = data?.coupons ?? [];
  const tableMissing = Boolean(data?.table_missing);

  const onCreate = async (e: FormEvent) => {
    e.preventDefault();
    setSaveMsg(null);
    setSaving(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.coupons, {
        code: code.trim(),
        discount_type: discountType,
        discount_value: parseFloat(discountValue) || 0,
        max_uses: parseInt(maxUses, 10) || 0,
        expires_at: expiresAt || null,
        status: 'active',
      });
      setSaveMsg('Coupon created.');
      setCode('');
      refetch();
    } catch (err) {
      setSaveMsg(err instanceof Error ? err.message : 'Could not create coupon.');
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
      subtitle="Discount codes applied at native checkout (Stripe / PayPal)."
      pageActions={
        <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
          Refresh
        </ButtonPrimary>
      }
    >
      {error ? (
        <div className="mb-4">
          <ApiErrorPanel error={error} title="Could not load coupons" onRetry={() => refetch()} />
        </div>
      ) : null}

      {tableMissing ? (
        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
          Coupons table is not installed yet. Update the plugin to run database migrations.
        </div>
      ) : null}

      <div className="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Create coupon</h2>
        <form onSubmit={onCreate} className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <label className="block text-sm">
            <span className="text-slate-600 dark:text-slate-400">Code</span>
            <input
              required
              value={code}
              onChange={(e) => setCode(e.target.value.toUpperCase())}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
              placeholder="SAVE10"
            />
          </label>
          <label className="block text-sm">
            <span className="text-slate-600 dark:text-slate-400">Type</span>
            <select
              value={discountType}
              onChange={(e) => setDiscountType(e.target.value as 'percent' | 'fixed')}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
            >
              <option value="percent">Percent off</option>
              <option value="fixed">Fixed amount</option>
            </select>
          </label>
          <label className="block text-sm">
            <span className="text-slate-600 dark:text-slate-400">Value</span>
            <input
              type="number"
              step="0.01"
              min="0"
              value={discountValue}
              onChange={(e) => setDiscountValue(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
            />
          </label>
          <label className="block text-sm">
            <span className="text-slate-600 dark:text-slate-400">Max uses (0 = unlimited)</span>
            <input
              type="number"
              min="0"
              value={maxUses}
              onChange={(e) => setMaxUses(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
            />
          </label>
          <label className="block text-sm sm:col-span-2">
            <span className="text-slate-600 dark:text-slate-400">Expires (optional, local time)</span>
            <input
              type="datetime-local"
              value={expiresAt}
              onChange={(e) => setExpiresAt(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
            />
          </label>
          <div className="flex items-end">
            <ButtonPrimary type="submit" disabled={saving || tableMissing}>
              {saving ? 'Saving…' : 'Create coupon'}
            </ButtonPrimary>
          </div>
        </form>
        {saveMsg ? <p className="mt-3 text-sm text-slate-600 dark:text-slate-400">{saveMsg}</p> : null}
      </div>

      <ListPanel>
        {loading ? (
          <div className="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>
        ) : rows.length === 0 ? (
          <ListEmptyState title="No coupons" description="Create a code learners can enter during checkout." />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
              <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                <tr>
                  <th className="px-5 py-3.5">Code</th>
                  <th className="px-5 py-3.5">Discount</th>
                  <th className="px-5 py-3.5">Uses</th>
                  <th className="px-5 py-3.5">Expires</th>
                  <th className="px-5 py-3.5">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {rows.map((r) => (
                  <tr key={r.id} className="bg-white dark:bg-slate-900">
                    <td className="px-5 py-3.5 font-mono font-semibold text-slate-800 dark:text-slate-200">{r.code}</td>
                    <td className="px-5 py-3.5 text-slate-700 dark:text-slate-300">
                      {r.discount_type === 'percent' ? `${r.discount_value}%` : `${r.discount_value.toFixed(2)} fixed`}
                    </td>
                    <td className="px-5 py-3.5 tabular-nums text-slate-600 dark:text-slate-400">
                      {r.used_count}
                      {r.max_uses > 0 ? ` / ${r.max_uses}` : ''}
                    </td>
                    <td className="px-5 py-3.5 text-slate-600 dark:text-slate-400">{r.expires_at || '—'}</td>
                    <td className="px-5 py-3.5 capitalize">{r.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </ListPanel>
    </AppShell>
  );
}
