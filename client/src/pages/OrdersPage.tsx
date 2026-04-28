import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { getSikshyaApi, getWpApi, getErrorSummary, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { StatusBadge } from '../components/shared/list/StatusBadge';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { Modal } from '../components/shared/Modal';
import { RowActionsMenu, type RowActionItem } from '../components/shared/list/RowActionsMenu';
import { MultiCoursePicker } from '../components/shared/MultiCoursePicker';
import { appViewHref } from '../lib/appUrl';
import { formatPostDate } from '../lib/formatPostDate';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAdminRouting } from '../lib/adminRouting';
import type { SikshyaReactConfig, WpRestUser } from '../types';

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
  dynamic_fields?: Record<string, unknown>;
  dynamic_fields_display?: Array<{ id: string; label: string; value: string }>;
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

type CreateOrderResponse = {
  ok?: boolean;
  order_id?: number;
  mark_paid?: boolean;
  message?: string;
};

function canMarkOrderPaid(r: OrderRow): boolean {
  if (r.status === 'paid') {
    return false;
  }
  if (r.status !== 'pending' && r.status !== 'on-hold') {
    return false;
  }
  const gw = (r.gateway || '').toLowerCase();
  return gw === 'offline' || gw === '';
}

export function OrdersPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const adminBase = config.adminUrl.replace(/\/?$/, '/');
  const { navigateView } = useAdminRouting();
  const [page, setPage] = useState(1);
  const [markBusyId, setMarkBusyId] = useState<number | null>(null);

  const [createOpen, setCreateOpen] = useState(false);
  const [pickedUser, setPickedUser] = useState<WpRestUser | null>(null);
  const [userQuery, setUserQuery] = useState('');
  const [userResults, setUserResults] = useState<WpRestUser[]>([]);
  const [userLoading, setUserLoading] = useState(false);
  const [userOpen, setUserOpen] = useState(false);
  const [courseIds, setCourseIds] = useState<number[]>([]);
  const [couponCode, setCouponCode] = useState('');
  const [markPaidNow, setMarkPaidNow] = useState(false);
  const [creating, setCreating] = useState(false);

  const [editOpen, setEditOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [editStatus, setEditStatus] = useState<'pending' | 'on-hold' | 'paid'>('pending');

  const loader = useCallback(async () => {
    const q = new URLSearchParams({ page: String(page), per_page: '30' });
    return getSikshyaApi().get<ListResponse>(`${SIKSHYA_ENDPOINTS.admin.orders}?${q.toString()}`);
  }, [page]);

  const { loading, data, error, refetch } = useAsyncData(loader, [page]);
  const rows = data?.orders ?? [];
  const total = data?.total ?? 0;
  const pages = data?.pages ?? 0;
  const tableMissing = Boolean(data?.table_missing);

  useEffect(() => {
    if (!createOpen) {
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
  }, [createOpen, userQuery]);

  const resetCreateForm = () => {
    setPickedUser(null);
    setUserQuery('');
    setUserResults([]);
    setUserOpen(false);
    setCourseIds([]);
    setCouponCode('');
    setMarkPaidNow(false);
  };

  const openEdit = (r: OrderRow) => {
    setEditId(r.id);
    setEditStatus((r.status === 'paid' ? 'paid' : r.status === 'on-hold' ? 'on-hold' : 'pending') as any);
    setEditOpen(true);
  };

  const openCreate = () => {
    resetCreateForm();
    setCreateOpen(true);
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Checkout orders and manual entries (offline) — create pending orders or record paid access in one step."
      pageActions={
        <div className="flex flex-wrap items-center gap-2">
          {!tableMissing ? (
            <ButtonSecondary type="button" disabled={loading} onClick={() => openCreate()}>
              New manual order
            </ButtonSecondary>
          ) : null}
          <ButtonPrimary type="button" disabled={loading} onClick={() => refetch()}>
            Refresh
          </ButtonPrimary>
        </div>
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

      {!tableMissing ? (
        <div className="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200">
          <strong className="font-semibold text-slate-900 dark:text-white">Manual / offline orders:</strong>{' '}
          {config.offlineCheckoutEnabled === false ? (
            <>
              Enable offline checkout under{' '}
              <a
                className="text-brand-600 underline-offset-2 hover:underline dark:text-brand-400"
                href={`${adminBase}admin.php?page=sikshya&view=settings&tab=payment`}
              >
                Sikshya → Settings → Payment
              </a>{' '}
              so learners can choose offline on the storefront.{' '}
            </>
          ) : null}
          Use <strong>New manual order</strong> to bill a learner without going through the cart, or open a pending
          offline row and use <strong>Mark paid</strong> after you confirm payment.
        </div>
      ) : null}

      <Modal
        open={createOpen}
        title="New manual order"
        description="Creates a real checkout order with the same prices as the storefront (including coupons). Gateway is set to offline."
        size="xl"
        onClose={() => {
          if (!creating) {
            setCreateOpen(false);
          }
        }}
        footer={
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="text-xs text-slate-500 dark:text-slate-400">
              {pickedUser ? (
                <>
                  Learner: <span className="font-semibold">{pickedUser.name || pickedUser.slug}</span> · #{pickedUser.id}
                </>
              ) : (
                'Select a learner to continue.'
              )}
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                disabled={creating}
                onClick={() => setCreateOpen(false)}
              >
                Cancel
              </button>
              <ButtonPrimary
                type="submit"
                form="sikshya-manual-order"
                disabled={creating || !pickedUser || courseIds.length === 0}
              >
                {creating ? 'Creating…' : 'Create order'}
              </ButtonPrimary>
            </div>
          </div>
        }
      >
        <form
          id="sikshya-manual-order"
          className="space-y-5"
          onSubmit={async (e: FormEvent) => {
            e.preventDefault();
            if (!pickedUser || courseIds.length === 0) {
              return;
            }
            setCreating(true);
            try {
              const res = await getSikshyaApi().post<CreateOrderResponse>(SIKSHYA_ENDPOINTS.admin.orders, {
                user_id: pickedUser.id,
                course_ids: courseIds,
                coupon_code: couponCode.trim() || undefined,
                mark_paid: markPaidNow,
              });
              if (res && res.ok === false) {
                throw new Error(res.message || 'Could not create order.');
              }
              setCreateOpen(false);
              resetCreateForm();
              await refetch();
              window.alert(res?.message || 'Order created.');
            } catch (err) {
              window.alert(getErrorSummary(err));
            } finally {
              setCreating(false);
            }
          }}
        >
          <div>
            <div className="mb-1 text-sm font-medium text-slate-700 dark:text-slate-200">Learner</div>
            <div className="relative">
              <input
                value={
                  pickedUser
                    ? `${pickedUser.name || pickedUser.slug || `User #${pickedUser.id}`} · ${pickedUser.email || ''}`
                    : userQuery
                }
                onChange={(e) => {
                  setPickedUser(null);
                  setUserQuery(e.target.value);
                  setUserOpen(true);
                }}
                onFocus={() => setUserOpen(true)}
                placeholder="Search users by name or email…"
                disabled={creating}
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
          </div>

          <div>
            <div className="mb-1 text-sm font-medium text-slate-700 dark:text-slate-200">Courses</div>
            <MultiCoursePicker
              value={courseIds}
              onChange={setCourseIds}
              placeholder="Choose one or more courses…"
              title="Courses for this order"
              hint="Line totals use each course’s configured price (same as checkout)."
              readOnly={creating}
            />
          </div>

          <label className="block text-sm text-slate-600 dark:text-slate-400">
            Coupon code (optional)
            <input
              value={couponCode}
              onChange={(e) => setCouponCode(e.target.value)}
              disabled={creating}
              placeholder="e.g. SAVE10"
              className="mt-1 w-full max-w-md rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
            />
          </label>

          <label className="flex cursor-pointer items-start gap-3 text-sm text-slate-700 dark:text-slate-200">
            <input
              type="checkbox"
              checked={markPaidNow}
              onChange={(e) => setMarkPaidNow(e.target.checked)}
              disabled={creating}
              className="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600"
            />
            <span>
              <span className="font-medium">Mark as paid and enroll now</span>
              <span className="mt-0.5 block text-xs font-normal text-slate-500 dark:text-slate-400">
                Use when payment is already confirmed (cash, bank transfer you verified, comp). Leave unchecked to create
                a pending order and use <strong>Mark paid</strong> on the list later.
              </span>
            </span>
          </label>
        </form>
      </Modal>

      <Modal
        open={editOpen}
        title={editId ? `Edit order #${editId}` : 'Edit order'}
        description="Change order status. Use Mark paid for offline orders to apply enrollments."
        size="lg"
        onClose={() => {
          if (!markBusyId) {
            setEditOpen(false);
          }
        }}
        footer={
          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
              disabled={markBusyId !== null}
              onClick={() => setEditOpen(false)}
            >
              Cancel
            </button>
            <ButtonPrimary
              type="button"
              disabled={markBusyId !== null || !editId}
              onClick={async () => {
                if (!editId) return;
                setMarkBusyId(editId);
                try {
                  await getSikshyaApi().patch<{ ok?: boolean; message?: string }>(
                    SIKSHYA_ENDPOINTS.admin.orderUpdate(editId),
                    { status: editStatus }
                  );
                  setEditOpen(false);
                  await refetch();
                } catch (err) {
                  window.alert(getErrorSummary(err));
                } finally {
                  setMarkBusyId(null);
                }
              }}
            >
              {markBusyId === editId ? 'Saving…' : 'Save'}
            </ButtonPrimary>
          </div>
        }
      >
        <div className="space-y-4">
          <label className="block text-sm text-slate-700 dark:text-slate-200">
            Status
            <select
              className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
              value={editStatus}
              onChange={(e) => setEditStatus(e.target.value as any)}
              disabled={markBusyId !== null}
            >
              <option value="pending">pending</option>
              <option value="on-hold">on-hold</option>
              <option value="paid">paid</option>
            </select>
          </label>
          <div className="text-xs text-slate-500 dark:text-slate-400">
            Setting status to <strong>paid</strong> here updates the row, but does not run fulfillment hooks. For offline
            payments, prefer <strong>Mark paid</strong>.
          </div>
        </div>
      </Modal>

      <ListPanel>
        {loading ? (
          <div className="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading orders…</div>
        ) : rows.length === 0 ? (
          <ListEmptyState
            title="No orders"
            description="Orders from checkout or manual creation appear here. Use New manual order to add one without the cart."
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
                    <th className="px-5 py-3.5">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {rows.map((r) => (
                    <tr key={r.id} className="bg-white dark:bg-slate-900">
                      <td className="whitespace-nowrap px-5 py-3.5 text-slate-600 dark:text-slate-400">
                        {formatPostDate(r.created_at)}
                        <div className="mt-1">
                          <button
                            type="button"
                            className="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400"
                            onClick={() => navigateView('order', { id: String(r.id) })}
                            title="View order details"
                          >
                            Order #{r.id}
                          </button>
                        </div>
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
                      <td className="px-5 py-3.5">
                        <div onClick={(e) => e.stopPropagation()} className="flex justify-end">
                          {(() => {
                            const items: RowActionItem[] = [
                              {
                                key: 'view',
                                label: 'View details',
                                onClick: () => navigateView('order', { id: String(r.id) }),
                              },
                              {
                                key: 'edit',
                                label: 'Change status',
                                onClick: () => openEdit(r),
                              },
                            ];
                            if (canMarkOrderPaid(r)) {
                              items.push({
                                key: 'mark_paid',
                                label: markBusyId === r.id ? 'Marking…' : 'Mark paid',
                                disabled: markBusyId === r.id || loading,
                                onClick: async () => {
                                  setMarkBusyId(r.id);
                                  try {
                                    await getSikshyaApi().post<{ ok?: boolean; message?: string }>(
                                      SIKSHYA_ENDPOINTS.admin.ordersMarkPaid(r.id),
                                      {}
                                    );
                                    await refetch();
                                  } catch (err) {
                                    window.alert(getErrorSummary(err));
                                  } finally {
                                    setMarkBusyId(null);
                                  }
                                },
                              });
                            }
                            return <RowActionsMenu items={items} ariaLabel={`Order actions for #${r.id}`} />;
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
