import { useCallback, useState } from 'react';
import { getErrorSummary, getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { StatusBadge } from '../components/shared/list/StatusBadge';
import { ButtonPrimary } from '../components/shared/buttons';
import { RowActionsMenu, type RowActionItem } from '../components/shared/list/RowActionsMenu';
import { Modal } from '../components/shared/Modal';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAdminRouting } from '../lib/adminRouting';
import type { SikshyaReactConfig } from '../types';

type PaymentRow = {
  id: number;
  user_id: number;
  course_id: number;
  amount: number;
  currency: string;
  payment_method: string;
  transaction_id: string;
  status: string;
  payment_date: string;
  payer_name: string;
  payer_email: string;
  course_title: string;
};

type ListResponse = {
  success?: boolean;
  payments?: PaymentRow[];
  total?: number;
  pages?: number;
  page?: number;
  per_page?: number;
  table_missing?: boolean;
};

export function PaymentsPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const adminBase = config.adminUrl.replace(/\/?$/, '/');
  const { navigateView } = useAdminRouting();
  const [page, setPage] = useState(1);
  const [editOpen, setEditOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [editStatus, setEditStatus] = useState('pending');
  const [saving, setSaving] = useState(false);

  const loader = useCallback(async () => {
    const q = new URLSearchParams({ page: String(page), per_page: '30' });
    return getSikshyaApi().get<ListResponse>(`${SIKSHYA_ENDPOINTS.admin.payments}?${q.toString()}`);
  }, [page]);

  const { loading, data, error, refetch } = useAsyncData(loader, [page]);

  const rows = data?.payments ?? [];
  const total = data?.total ?? 0;
  const pages = data?.pages ?? 0;
  const tableMissing = Boolean(data?.table_missing);

  const openEdit = (r: PaymentRow) => {
    setEditId(r.id);
    setEditStatus(r.status || 'pending');
    setEditOpen(true);
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Recorded transactions linked to courses (when the payments table is present)"
      pageActions={
        <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
          Refresh
        </ButtonPrimary>
      }
    >
      {error ? (
        <div className="mb-4">
          <ApiErrorPanel error={error} title="Could not load payments" onRetry={() => refetch()} />
        </div>
      ) : null}

      {tableMissing ? (
        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100">
          The payments database table is not installed yet. Payments will appear here after migrations and successful
          checkouts.
        </div>
      ) : null}

      <Modal
        open={editOpen}
        title={editId ? `Change payment status (#${editId})` : 'Change payment status'}
        description="Update the payment record status."
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
              disabled={saving || !editId}
              onClick={async () => {
                if (!editId) return;
                setSaving(true);
                try {
                  await getSikshyaApi().patch<{ ok?: boolean; message?: string }>(
                    SIKSHYA_ENDPOINTS.admin.paymentUpdate(editId),
                    { status: editStatus }
                  );
                  setEditOpen(false);
                  await refetch();
                } catch (err) {
                  window.alert(getErrorSummary(err));
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

      <ListPanel>
        {loading ? (
          <div className="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading payments…</div>
        ) : rows.length === 0 ? (
          <ListEmptyState
            title="No payments"
            description="Completed purchases and gateway records will list here for auditing and support."
          />
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead className="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                  <tr>
                    <th className="px-5 py-3.5">Date</th>
                    <th className="px-5 py-3.5">Payer</th>
                    <th className="px-5 py-3.5">Course</th>
                    <th className="px-5 py-3.5">Amount</th>
                    <th className="px-5 py-3.5">Status</th>
                    <th className="px-5 py-3.5 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {rows.map((r) => (
                    <tr key={r.id} className="bg-white dark:bg-slate-900">
                      <td className="whitespace-nowrap px-5 py-3.5 text-slate-600 dark:text-slate-400">
                        {formatPostDate(r.payment_date)}
                        <div className="mt-1 text-xs font-semibold text-brand-600 dark:text-brand-400">
                          Payment #{r.id}
                        </div>
                      </td>
                      <td className="px-5 py-3.5">
                        <a
                          href={`${adminBase}user-edit.php?user_id=${r.user_id}`}
                          className="font-semibold text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                          onClick={(e) => e.stopPropagation()}
                        >
                          {r.payer_name || `User #${r.user_id}`}
                        </a>
                        <div className="text-xs text-slate-500 dark:text-slate-400">{r.payer_email || '—'}</div>
                      </td>
                      <td className="px-5 py-3.5">
                        {r.course_id > 0 ? (
                          <a
                            href={appViewHref(config, 'add-course', { course_id: String(r.course_id) })}
                            className="font-medium text-brand-600 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                            onClick={(e) => e.stopPropagation()}
                          >
                            {r.course_title || `Course #${r.course_id}`}
                          </a>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td className="whitespace-nowrap px-5 py-3.5 font-medium tabular-nums text-slate-800 dark:text-slate-200">
                        {r.amount.toFixed(2)} {r.currency || ''}
                      </td>
                      <td className="px-5 py-3.5">
                        <StatusBadge status={r.status} />
                      </td>
                      <td className="px-5 py-3.5 text-right">
                        <div onClick={(e) => e.stopPropagation()} className="inline-flex">
                          {(() => {
                            const items: RowActionItem[] = [
                              {
                                key: 'view',
                                label: 'View details',
                                onClick: () => navigateView('payment', { id: String(r.id) }),
                              },
                              {
                                key: 'status',
                                label: 'Change status',
                                onClick: () => openEdit(r),
                              },
                            ];
                            return <RowActionsMenu items={items} ariaLabel={`Payment actions for #${r.id}`} />;
                          })()}
                        </div>
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
    </EmbeddableShell>
  );
}
