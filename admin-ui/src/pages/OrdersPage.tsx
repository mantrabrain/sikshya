import { useCallback, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { StatusBadge } from '../components/shared/list/StatusBadge';
import { ButtonPrimary } from '../components/shared/buttons';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import { useAsyncData } from '../hooks/useAsyncData';
import type { NavItem, SikshyaReactConfig } from '../types';

type OrderLine = { course_id: number; course_title: string; line_total: number };

type OrderRow = {
  id: number;
  user_id: number;
  status: string;
  currency: string;
  subtotal: number;
  discount_total: number;
  total: number;
  gateway: string;
  gateway_intent_id: string;
  created_at: string;
  payer_name: string;
  payer_email: string;
  lines: OrderLine[];
};

type ListResponse = {
  success?: boolean;
  orders?: OrderRow[];
  total?: number;
  pages?: number;
  page?: number;
  per_page?: number;
  table_missing?: boolean;
};

export function OrdersPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const adminBase = config.adminUrl.replace(/\/?$/, '/');
  const [page, setPage] = useState(1);

  const loader = useCallback(async () => {
    const q = new URLSearchParams({ page: String(page), per_page: '30' });
    return getSikshyaApi().get<ListResponse>(`${SIKSHYA_ENDPOINTS.admin.orders}?${q.toString()}`);
  }, [page]);

  const { loading, data, error, refetch } = useAsyncData(loader, [page]);
  const rows = data?.orders ?? [];
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
      subtitle="Normalized checkout orders (Stripe, PayPal) before and after fulfillment."
      pageActions={
        <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
          Refresh
        </ButtonPrimary>
      }
    >
      {error ? (
        <div className="mb-4">
          <ApiErrorPanel error={error} title="Could not load orders" onRetry={() => refetch()} />
        </div>
      ) : null}

      {tableMissing ? (
        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
          Orders table is not installed yet. Run plugin updates / migrations, then complete a test checkout.
        </div>
      ) : null}

      <ListPanel>
        {loading ? (
          <div className="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading orders…</div>
        ) : rows.length === 0 ? (
          <ListEmptyState
            title="No orders"
            description="Orders created by the checkout API will appear here with gateway references and line items."
          />
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                  <tr>
                    <th className="px-5 py-3.5">Created</th>
                    <th className="px-5 py-3.5">Customer</th>
                    <th className="px-5 py-3.5">Courses</th>
                    <th className="px-5 py-3.5">Total</th>
                    <th className="px-5 py-3.5">Gateway</th>
                    <th className="px-5 py-3.5">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {rows.map((r) => (
                    <tr key={r.id} className="bg-white dark:bg-slate-900">
                      <td className="whitespace-nowrap px-5 py-3.5 text-slate-600 dark:text-slate-400">
                        {formatPostDate(r.created_at)}
                      </td>
                      <td className="px-5 py-3.5">
                        <a
                          href={`${adminBase}user-edit.php?user_id=${r.user_id}`}
                          className="font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400"
                        >
                          {r.payer_name || `User #${r.user_id}`}
                        </a>
                        <div className="text-xs text-slate-500 dark:text-slate-400">{r.payer_email || '—'}</div>
                      </td>
                      <td className="px-5 py-3.5">
                        <ul className="space-y-1 text-xs">
                          {r.lines?.length
                            ? r.lines.map((ln) => (
                                <li key={`${r.id}-${ln.course_id}`}>
                                  <a
                                    href={appViewHref(config, 'add-course', { course_id: String(ln.course_id) })}
                                    className="text-brand-600 hover:underline dark:text-brand-400"
                                  >
                                    {ln.course_title || `Course #${ln.course_id}`}
                                  </a>
                                </li>
                              ))
                            : '—'}
                        </ul>
                      </td>
                      <td className="whitespace-nowrap px-5 py-3.5 font-medium tabular-nums text-slate-800 dark:text-slate-200">
                        {r.total.toFixed(2)} {r.currency}
                        {r.discount_total > 0 ? (
                          <span className="ml-1 text-xs text-emerald-600 dark:text-emerald-400">
                            (−{r.discount_total.toFixed(2)})
                          </span>
                        ) : null}
                      </td>
                      <td className="px-5 py-3.5">
                        <span className="uppercase text-slate-700 dark:text-slate-300">{r.gateway || '—'}</span>
                        {r.gateway_intent_id ? (
                          <div className="mt-0.5 max-w-[180px] truncate font-mono text-[11px] text-slate-500 dark:text-slate-400">
                            {r.gateway_intent_id}
                          </div>
                        ) : null}
                      </td>
                      <td className="px-5 py-3.5">
                        <StatusBadge status={r.status} />
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
