import { useCallback, useMemo, useState } from 'react';
import { getErrorSummary, getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { StatusBadge } from '../components/shared/list/StatusBadge';
import { Modal } from '../components/shared/Modal';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAdminRouting } from '../lib/adminRouting';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import type { SikshyaReactConfig } from '../types';
import type { ApiError } from '../api/http';

type OrderLine = {
  course_id: number;
  course_title: string;
  quantity: number;
  unit_price: number;
  line_total: number;
};

type OrderSubscriptionSummary = {
  is_subscription_checkout?: boolean;
  plan_id?: number;
  interval_unit?: string;
  gateway_subscription_ref?: string;
  plan_name?: string;
  plan_amount?: number;
  plan_currency?: string;
};

type OrderDetails = {
  id: number;
  user_id: number;
  status: string;
  currency: string;
  subtotal: number;
  discount_total: number;
  total: number;
  gateway: string;
  gateway_intent_id: string;
  public_token: string;
  created_at: string;
  payer_name: string;
  payer_email: string;
  meta?: Record<string, unknown>;
  subscription?: OrderSubscriptionSummary;
  dynamic_fields?: Record<string, unknown>;
  dynamic_fields_display?: Array<{ id: string; label: string; value: string }>;
  receipt_url?: string;
  invoice_number?: string;
  invoice_issued_at?: string;
  invoice_url?: string;
  lines: OrderLine[];
};

type DetailsResponse = { ok?: boolean; order?: OrderDetails; message?: string };

function canMarkPaid(o: OrderDetails): boolean {
  if (o.status === 'paid') return false;
  if (o.status !== 'pending' && o.status !== 'on-hold') return false;
  const gw = (o.gateway || '').toLowerCase();
  return gw === 'offline' || gw === '';
}

export function OrderDetailsPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, embedded, title } = props;
  const dialog = useSikshyaDialog();
  const { route, navigateView } = useAdminRouting();
  const orderId = useMemo(() => parseInt(route.query?.id || '0', 10) || 0, [route.query]);
  const [markBusy, setMarkBusy] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [editStatus, setEditStatus] = useState<'pending' | 'on-hold' | 'paid'>('pending');

  const loader = useCallback(async () => {
    if (!orderId) throw new Error('Missing order id.');
    return getSikshyaApi().get<DetailsResponse>(SIKSHYA_ENDPOINTS.admin.order(orderId));
  }, [orderId]);

  const { loading, data, error, refetch } = useAsyncData(loader, [orderId]);
  const order = data?.order;
  const [saving, setSaving] = useState(false);

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={orderId ? `Order #${orderId}` : 'Order'}
      pageActions={
        <div className="flex flex-wrap items-center gap-2">
          <ButtonSecondary type="button" onClick={() => navigateView('orders')}>
            Back to orders
          </ButtonSecondary>
          <ButtonSecondary
            type="button"
            disabled={!order || loading}
            onClick={() => {
              if (!order) return;
              setEditStatus((order.status === 'paid' ? 'paid' : order.status === 'on-hold' ? 'on-hold' : 'pending') as any);
              setEditOpen(true);
            }}
          >
            Edit
          </ButtonSecondary>
          <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
            Refresh
          </ButtonPrimary>
        </div>
      }
    >
      {error ? (
        <div className="mb-4">
          <ApiErrorPanel error={error as ApiError} title="Could not load order" onRetry={() => refetch()} />
        </div>
      ) : null}

      {loading ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
          Loading order…
        </div>
      ) : !order ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
          Order not found.
        </div>
      ) : (
        <div className="space-y-6">
          <Modal
            open={editOpen}
            title={`Edit order #${order.id}`}
            description="Change order status. For offline payments, prefer “Mark paid” to apply fulfillment."
            size="lg"
            onClose={() => {
              if (!saving) setEditOpen(false);
            }}
            footer={
              <div className="flex items-center justify-end gap-2">
                <button
                  type="button"
                  className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                  disabled={saving}
                  onClick={() => setEditOpen(false)}
                >
                  Cancel
                </button>
                <ButtonPrimary
                  type="button"
                  disabled={saving}
                  onClick={async () => {
                    setSaving(true);
                    try {
                      await getSikshyaApi().patch<{ ok?: boolean; message?: string }>(
                        SIKSHYA_ENDPOINTS.admin.orderUpdate(order.id),
                        { status: editStatus }
                      );
                      setEditOpen(false);
                      await refetch();
                    } catch (err) {
                      void dialog.alert({ title: 'Something went wrong', message: getErrorSummary(err) });
                    } finally {
                      setSaving(false);
                    }
                  }}
                >
                  {saving ? 'Saving…' : 'Save'}
                </ButtonPrimary>
              </div>
            }
          >
            <label className="block text-sm text-slate-700 dark:text-slate-200">
              Status
              <select
                className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                value={editStatus}
                onChange={(e) => setEditStatus(e.target.value as any)}
                disabled={saving}
              >
                <option value="pending">pending</option>
                <option value="on-hold">on-hold</option>
                <option value="paid">paid</option>
              </select>
            </label>
          </Modal>

          <div className="grid gap-6 lg:grid-cols-3">
            <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
              <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Status
              </div>
              <div className="mt-2 flex items-center justify-between gap-3">
                <StatusBadge status={order.status} />
                <div className="text-xs text-slate-500 dark:text-slate-400">{formatPostDate(order.created_at)}</div>
              </div>
              <div className="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Gateway
              </div>
              <div className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                {(order.gateway || '—').toUpperCase()}
              </div>
              {order.gateway_intent_id ? (
                <div className="mt-1 truncate font-mono text-[11px] text-slate-500 dark:text-slate-400">
                  {order.gateway_intent_id}
                </div>
              ) : null}
              <div className="mt-4 flex flex-wrap gap-2">
                {canMarkPaid(order) ? (
                  <button
                    type="button"
                    disabled={markBusy}
                    onClick={async () => {
                      setMarkBusy(true);
                      try {
                        await getSikshyaApi().post<{ ok?: boolean; message?: string }>(
                          SIKSHYA_ENDPOINTS.admin.ordersMarkPaid(order.id),
                          {}
                        );
                        await refetch();
                      } catch (err) {
                        void dialog.alert({ title: 'Something went wrong', message: getErrorSummary(err) });
                      } finally {
                        setMarkBusy(false);
                      }
                    }}
                    className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900 hover:bg-emerald-100 disabled:opacity-50 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100 dark:hover:bg-emerald-900/40"
                  >
                    {markBusy ? 'Marking…' : 'Mark paid'}
                  </button>
                ) : null}
                {order.receipt_url ? (
                  <a
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950/20 dark:text-slate-200 dark:hover:bg-slate-800"
                    href={order.receipt_url}
                    target="_blank"
                    rel="noreferrer"
                  >
                    View receipt
                  </a>
                ) : null}
                {order.invoice_url ? (
                  <a
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950/20 dark:text-slate-200 dark:hover:bg-slate-800"
                    href={order.invoice_url}
                    target="_blank"
                    rel="noreferrer"
                  >
                    {order.invoice_number ? `Invoice ${order.invoice_number}` : 'View invoice'}
                  </a>
                ) : null}
                {order.invoice_url ? (
                  <a
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950/20 dark:text-slate-200 dark:hover:bg-slate-800"
                    href={`${order.invoice_url}${order.invoice_url.includes('?') ? '&' : '?'}pdf=1`}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Download PDF
                  </a>
                ) : null}
              </div>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
              <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Customer
              </div>
              <div className="mt-2 text-sm font-semibold text-slate-900 dark:text-white">
                {order.payer_name || `User #${order.user_id}`}
              </div>
              <div className="text-sm text-slate-600 dark:text-slate-300">{order.payer_email || '—'}</div>
              <div className="mt-3 text-xs text-slate-500 dark:text-slate-400">User ID: #{order.user_id || '—'}</div>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
              <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Totals
              </div>
              <div className="mt-2 space-y-2 text-sm">
                <div className="flex items-center justify-between">
                  <span className="text-slate-600 dark:text-slate-300">Subtotal</span>
                  <span className="font-semibold text-slate-900 dark:text-white">
                    {order.subtotal.toFixed(2)} {order.currency}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-slate-600 dark:text-slate-300">Discount</span>
                  <span className="font-semibold text-slate-900 dark:text-white">
                    {order.discount_total.toFixed(2)} {order.currency}
                  </span>
                </div>
                <div className="flex items-center justify-between border-t border-slate-200 pt-2 dark:border-slate-800">
                  <span className="text-slate-700 dark:text-slate-200">Total</span>
                  <span className="text-base font-bold text-slate-900 dark:text-white">
                    {order.total.toFixed(2)} {order.currency}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {order.subscription?.is_subscription_checkout ? (
            <div className="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 dark:border-amber-900/45 dark:bg-amber-950/25">
              <div className="text-xs font-semibold uppercase tracking-wide text-amber-950 dark:text-amber-100">
                Subscription checkout
              </div>
              <p className="mt-1 text-sm text-amber-950/90 dark:text-amber-50/90">
                This order created or renewed access through a membership plan. The row in{' '}
                <strong>Subscriptions</strong> for this learner is keyed by user + plan; gateway webhooks keep status
                and billing period in sync when configured.
              </p>
              <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div>
                  <dt className="text-xs font-semibold uppercase tracking-wide text-amber-900/80 dark:text-amber-200/80">
                    Plan
                  </dt>
                  <dd className="font-semibold text-slate-900 dark:text-white">
                    {(order.subscription.plan_name && String(order.subscription.plan_name)) ||
                      (order.subscription.plan_id ? `Plan #${order.subscription.plan_id}` : '—')}
                    {order.subscription.plan_id ? (
                      <span className="ml-1 text-xs font-normal text-slate-600 dark:text-slate-400">
                        (ID {order.subscription.plan_id})
                      </span>
                    ) : null}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs font-semibold uppercase tracking-wide text-amber-900/80 dark:text-amber-200/80">
                    Billing interval
                  </dt>
                  <dd className="capitalize text-slate-900 dark:text-white">
                    {(order.subscription.interval_unit && String(order.subscription.interval_unit)) || '—'}
                  </dd>
                </div>
              </dl>
              <div className="mt-4 flex flex-wrap gap-2">
                <a
                  className="rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs font-semibold text-amber-950 hover:bg-amber-100 dark:border-amber-800 dark:bg-slate-900 dark:text-amber-50 dark:hover:bg-slate-800"
                  href={appViewHref(config, 'subscriptions', { tab: 'list' })}
                >
                  Open subscriptions list
                </a>
                <button
                  type="button"
                  className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                  onClick={() => navigateView('sales', { tab: 'payments' })}
                >
                  Payments (same sale)
                </button>
              </div>
              <p className="mt-3 text-xs text-slate-700 dark:text-slate-300">
                Gateway intent / subscription id (above) is what your provider uses for renewals; it may differ from the
                internal subscription row id in Sikshya.
              </p>
            </div>
          ) : null}

          <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div className="text-sm font-semibold text-slate-900 dark:text-white">Items</div>
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                  <tr>
                    <th className="px-4 py-2.5">Course</th>
                    <th className="px-4 py-2.5">Qty</th>
                    <th className="px-4 py-2.5">Unit</th>
                    <th className="px-4 py-2.5">Line</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {(order.lines || []).map((ln, idx) => (
                    <tr key={`${ln.course_id}-${idx}`}>
                      <td className="px-4 py-3">
                        <span className="font-semibold text-slate-900 dark:text-white">
                          {ln.course_title || `Course #${ln.course_id}`}
                        </span>
                        <div className="text-xs text-slate-500 dark:text-slate-400">ID: {ln.course_id}</div>
                      </td>
                      <td className="px-4 py-3">{ln.quantity || 1}</td>
                      <td className="px-4 py-3 tabular-nums">
                        {ln.unit_price.toFixed(2)} {order.currency}
                      </td>
                      <td className="px-4 py-3 tabular-nums font-semibold text-slate-900 dark:text-white">
                        {ln.line_total.toFixed(2)} {order.currency}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {order.dynamic_fields_display && order.dynamic_fields_display.length ? (
            <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
              <div className="text-sm font-semibold text-slate-900 dark:text-white">Dynamic checkout fields</div>
              <div className="mt-3 space-y-1 text-sm">
                {order.dynamic_fields_display.map((it) => (
                  <div key={it.id} className="flex gap-3">
                    <div className="min-w-[220px] text-[12px] font-semibold text-slate-700 dark:text-slate-200">
                      {it.label || it.id}
                    </div>
                    <div className="text-slate-800 dark:text-slate-200">{String(it.value ?? '') || '—'}</div>
                  </div>
                ))}
              </div>
            </div>
          ) : null}
        </div>
      )}
    </EmbeddableShell>
  );
}

