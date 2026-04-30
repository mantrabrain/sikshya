import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi, getErrorSummary, SIKSHYA_ENDPOINTS } from '../api';
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

type PaymentDetails = {
  id: number;
  user_id: number;
  course_id: number;
  course_title: string;
  amount: number;
  currency: string;
  payment_method: string;
  transaction_id: string;
  status: string;
  payment_date: string;
  payer_name: string;
  payer_email: string;
  gateway_response?: unknown;
  /** Checkout vs automated subscription renewal (see payments table migration). */
  charge_kind?: string;
  /** Checkout order id stored by fulfillment when gateway snapshot is missing (see legacy payment row). */
  related_order_id?: number;
};

type DetailsResponse = { ok?: boolean; payment?: PaymentDetails; message?: string };

export function PaymentDetailsPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, embedded, title } = props;
  const dialog = useSikshyaDialog();
  const { route, navigateView } = useAdminRouting();
  const adminBase = config.adminUrl.replace(/\/?$/, '/');
  const paymentId = useMemo(() => parseInt(route.query?.id || '0', 10) || 0, [route.query]);

  const loader = useCallback(async () => {
    if (!paymentId) throw new Error('Missing payment id.');
    return getSikshyaApi().get<DetailsResponse>(SIKSHYA_ENDPOINTS.admin.payment(paymentId));
  }, [paymentId]);

  const { loading, data, error, refetch } = useAsyncData(loader, [paymentId]);
  const p = data?.payment;
  const [editOpen, setEditOpen] = useState(false);
  const [editStatus, setEditStatus] = useState('pending');
  const [saving, setSaving] = useState(false);

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={paymentId ? `Payment #${paymentId}` : 'Payment'}
      pageActions={
        <div className="flex flex-wrap items-center gap-2">
          <ButtonSecondary type="button" onClick={() => navigateView('sales', { tab: 'payments' })}>
            Back to payments
          </ButtonSecondary>
          <ButtonSecondary
            type="button"
            disabled={!p || loading}
            onClick={() => {
              if (!p) return;
              setEditStatus(p.status || 'pending');
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
          <ApiErrorPanel error={error} title="Could not load payment" onRetry={() => refetch()} />
        </div>
      ) : null}

      {loading ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
          Loading payment…
        </div>
      ) : !p ? (
        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
          Payment not found.
        </div>
      ) : (
        <div className="space-y-6">
          <Modal
            open={editOpen}
            title={`Edit payment #${p.id}`}
            description="Change payment status."
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
                        SIKSHYA_ENDPOINTS.admin.paymentUpdate(p.id),
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
                onChange={(e) => setEditStatus(e.target.value)}
                disabled={saving}
              >
                <option value="pending">pending</option>
                <option value="completed">completed</option>
                <option value="failed">failed</option>
                <option value="refunded">refunded</option>
              </select>
            </label>
          </Modal>

          <div className="grid gap-6 lg:grid-cols-3">
            <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
              <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Status
              </div>
              <div className="mt-2 flex items-center justify-between gap-3">
                <StatusBadge status={p.status} />
                <div className="text-xs text-slate-500 dark:text-slate-400">{formatPostDate(p.payment_date)}</div>
              </div>
              {(p.charge_kind || '').toLowerCase() === 'renewal' ? (
                <div className="mt-2 text-xs font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-300">
                  Renewal
                </div>
              ) : null}
              <div className="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Amount
              </div>
              <div className="mt-1 text-base font-bold text-slate-900 dark:text-white">
                {p.amount.toFixed(2)} {p.currency}
              </div>
              <div className="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Method
              </div>
              <div className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                {(p.payment_method || '—').toUpperCase()}
              </div>
              {p.transaction_id ? (
                <div className="mt-1 truncate font-mono text-[11px] text-slate-500 dark:text-slate-400">
                  {p.transaction_id}
                </div>
              ) : null}
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
              <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Payer
              </div>
              <div className="mt-2 text-sm font-semibold text-slate-900 dark:text-white">
                {p.payer_name || `User #${p.user_id}`}
              </div>
              <div className="text-sm text-slate-600 dark:text-slate-300">{p.payer_email || '—'}</div>
              <div className="mt-3">
                {p.user_id ? (
                  <a
                    href={`${adminBase}user-edit.php?user_id=${p.user_id}`}
                    className="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400"
                  >
                    Open user
                  </a>
                ) : null}
              </div>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
              <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Course / plan
              </div>
              <div className="mt-2">
                {p.course_id > 0 ? (
                  <a
                    href={appViewHref(config, 'add-course', { course_id: String(p.course_id) })}
                    className="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-400"
                  >
                    {p.course_title || `Course #${p.course_id}`}
                  </a>
                ) : (p.charge_kind || '').toLowerCase() === 'renewal' ? (
                  <div className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {(() => {
                      const gw =
                        p.gateway_response && typeof p.gateway_response === 'object' && p.gateway_response !== null
                          ? (p.gateway_response as Record<string, unknown>)
                          : null;
                      const name = gw && typeof gw.plan_name === 'string' ? gw.plan_name.trim() : '';
                      return name || 'Subscription renewal';
                    })()}
                  </div>
                ) : (
                  <div className="text-sm text-slate-600 dark:text-slate-300">—</div>
                )}
                {(p.charge_kind || '').toLowerCase() === 'renewal' &&
                p.gateway_response &&
                typeof p.gateway_response === 'object' &&
                p.gateway_response !== null ? (
                  <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {(() => {
                      const gw = p.gateway_response as Record<string, unknown>;
                      const pid = typeof gw.subscription_plan_id === 'number' ? gw.subscription_plan_id : null;
                      return pid ? `Plan ID: ${pid}` : 'Linked to Sikshya subscription plan';
                    })()}
                  </div>
                ) : null}
                {p.course_id > 0 ? (
                  <div className="text-xs text-slate-500 dark:text-slate-400">Course ID: {p.course_id}</div>
                ) : null}
              </div>
            </div>
          </div>

          {p.related_order_id && p.related_order_id > 0 ? (
            <div className="rounded-2xl border border-sky-200 bg-sky-50/50 p-5 dark:border-sky-900/50 dark:bg-sky-950/30">
              <div className="text-xs font-semibold uppercase tracking-wide text-sky-900 dark:text-sky-100">
                Linked checkout order
              </div>
              <p className="mt-1 text-sm text-slate-700 dark:text-slate-300">
                This legacy payment row was written when the order was fulfilled. Subscription context (plan id, recurring
                id) lives on the order meta and gateway intent — open the order for the full picture.
              </p>
              <button
                type="button"
                className="mt-3 rounded-lg border border-sky-300 bg-white px-3 py-2 text-xs font-semibold text-sky-950 hover:bg-sky-100 dark:border-sky-800 dark:bg-slate-900 dark:text-sky-50 dark:hover:bg-slate-800"
                onClick={() => navigateView('order', { id: String(p.related_order_id) })}
              >
                Open order #{p.related_order_id}
              </button>
            </div>
          ) : null}

          <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div className="text-sm font-semibold text-slate-900 dark:text-white">Gateway response</div>
            <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-200">
              <pre className="whitespace-pre-wrap break-words">
                {(() => {
                  try {
                    return JSON.stringify(p.gateway_response ?? null, null, 2);
                  } catch (err) {
                    return String(p.gateway_response ?? '') || getErrorSummary(err);
                  }
                })()}
              </pre>
            </div>
          </div>
        </div>
      )}
    </EmbeddableShell>
  );
}

