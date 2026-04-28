import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AddonSettingsPage } from './AddonSettingsPage';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { MultiCoursePicker } from '../components/shared/MultiCoursePicker';
import { DateTimePickerField } from '../components/shared/DateTimePickerField';
import { HorizontalEditorTabs } from '../components/shared/HorizontalEditorTabs';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
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

type AdvancedMetaResponse = {
  ok?: boolean;
  meta?: Record<string, string>;
};

type EditorMode = 'create' | 'manage';
type PageTab = 'list' | 'create' | 'manage';

function clampNumberInput(v: string, fallback = 0) {
  const n = parseFloat(v);
  return Number.isFinite(n) ? n : fallback;
}

export function CouponsPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;

  const [tab, setTab] = useState<PageTab>('list');
  const [selected, setSelected] = useState<CouponRow | null>(null);
  const [search, setSearch] = useState('');

  const [code, setCode] = useState('');
  const [discountType, setDiscountType] = useState<'percent' | 'fixed'>('percent');
  const [discountValue, setDiscountValue] = useState('10');
  const [maxUses, setMaxUses] = useState('0');
  const [expiresAt, setExpiresAt] = useState('');
  const [saving, setSaving] = useState(false);
  const [saveMsg, setSaveMsg] = useState<string | null>(null);

  const advFeature = isFeatureEnabled(config, 'coupons_advanced');
  const advAddon = useAddonEnabled('coupons_advanced');
  const advMode = resolveGatedWorkspaceMode(advFeature, advAddon.enabled, advAddon.loading);
  const advEnabled = advMode === 'full';
  const [advCouponId, setAdvCouponId] = useState<number | null>(null);
  const [advMin, setAdvMin] = useState<string>('');
  const [advMaxSub, setAdvMaxSub] = useState<string>('');
  const [advCourseIds, setAdvCourseIds] = useState<number[]>([]);
  const [advExcludedCourseIds, setAdvExcludedCourseIds] = useState<number[]>([]);
  const [advMaxDiscount, setAdvMaxDiscount] = useState<string>('');
  const [advPerUser, setAdvPerUser] = useState<string>('');
  const [advFirstOrder, setAdvFirstOrder] = useState(false);
  const [advValidFrom, setAdvValidFrom] = useState<string>('');
  const [advValidUntil, setAdvValidUntil] = useState<string>('');
  const [advLoading, setAdvLoading] = useState(false);
  const [advSaving, setAdvSaving] = useState(false);
  const [advMsg, setAdvMsg] = useState<string | null>(null);

  const loader = useCallback(async () => {
    return getSikshyaApi().get<ListResponse>(SIKSHYA_ENDPOINTS.admin.coupons);
  }, []);

  const { loading, data, error, refetch } = useAsyncData(loader, []);
  const rows = data?.coupons ?? [];
  const tableMissing = Boolean(data?.table_missing);

  useEffect(() => {
    if (!advCouponId || !advEnabled) return;
    setAdvLoading(true);
    setAdvMsg(null);
    void (async () => {
      try {
        const r = await getSikshyaApi().get<AdvancedMetaResponse>(
          SIKSHYA_ENDPOINTS.pro.couponAdvanced(advCouponId)
        );
        const rules = r.rules || {};
        setAdvMin(rules.min_subtotal != null && rules.min_subtotal > 0 ? String(rules.min_subtotal) : '');
        setAdvMaxSub(rules.max_subtotal != null && rules.max_subtotal > 0 ? String(rules.max_subtotal) : '');
        setAdvCourseIds(Array.isArray(rules.allowed_course_ids) ? rules.allowed_course_ids.filter((n) => n > 0) : []);
        setAdvExcludedCourseIds(
          Array.isArray(rules.excluded_course_ids) ? rules.excluded_course_ids.filter((n) => n > 0) : []
        );
        setAdvMaxDiscount(
          rules.max_discount_amount != null && rules.max_discount_amount > 0 ? String(rules.max_discount_amount) : ''
        );
        setAdvPerUser(rules.per_user_limit != null && rules.per_user_limit > 0 ? String(rules.per_user_limit) : '');
        setAdvFirstOrder(Boolean(rules.first_order_only));
        setAdvValidFrom(rules.valid_from ? String(rules.valid_from) : '');
        setAdvValidUntil(rules.valid_until ? String(rules.valid_until) : '');
      } catch (err) {
        setAdvMsg(err instanceof Error ? err.message : 'Could not load advanced rules.');
      } finally {
        setAdvLoading(false);
      }
    })();
  }, [advCouponId, advEnabled]);

  const buildAdvancedRulesPayload = (): AdvancedRules => ({
    min_subtotal: clampNumberInput(advMin, 0),
    max_subtotal: clampNumberInput(advMaxSub, 0),
    allowed_course_ids: advCourseIds,
    excluded_course_ids: advExcludedCourseIds,
    max_discount_amount: clampNumberInput(advMaxDiscount, 0),
    per_user_limit: Math.max(0, parseInt(advPerUser, 10) || 0),
    first_order_only: advFirstOrder,
    valid_from: advValidFrom.trim(),
    valid_until: advValidUntil.trim(),
  });

  const saveAdvanced = async () => {
    if (!advCouponId) return;
    setAdvSaving(true);
    setAdvMsg(null);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.couponAdvanced(advCouponId), {
        rules: buildAdvancedRulesPayload(),
      });
      setAdvMsg('Advanced rules saved.');
    } catch (err) {
      setAdvMsg(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setAdvSaving(false);
    }
  };

  const resetEditor = () => {
    setCode('');
    setDiscountType('percent');
    setDiscountValue('10');
    setMaxUses('0');
    setExpiresAt('');
    setSaveMsg(null);

    setAdvCouponId(null);
    setAdvMin('');
    setAdvMaxSub('');
    setAdvCourseIds([]);
    setAdvExcludedCourseIds([]);
    setAdvMaxDiscount('');
    setAdvPerUser('');
    setAdvFirstOrder(false);
    setAdvValidFrom('');
    setAdvValidUntil('');
    setAdvMsg(null);
  };

  const beginCreate = () => {
    resetEditor();
    setSelected(null);
    setTab('create');
  };

  const beginManage = (row: CouponRow) => {
    setSelected(row);
    setTab('manage');
    setSaveMsg(null);
    setCode(row.code);
    setDiscountType((row.discount_type as 'percent' | 'fixed') || 'percent');
    setDiscountValue(String(row.discount_value ?? 0));
    setMaxUses(String(row.max_uses ?? 0));
    setExpiresAt(row.expires_at ? String(row.expires_at) : '');

    setAdvCouponId(row.id);
    setAdvMsg(null);
  };

  const editorMode: EditorMode = tab === 'manage' ? 'manage' : 'create';

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setSaveMsg(null);
    setSaving(true);
    try {
      if (editorMode === 'manage' && selected) {
        await getSikshyaApi().patch(SIKSHYA_ENDPOINTS.admin.coupon(selected.id), {
          code: code.trim(),
          discount_type: discountType,
          discount_value: clampNumberInput(discountValue, 0),
          max_uses: parseInt(maxUses, 10) || 0,
          expires_at: expiresAt || null,
          status: selected.status || 'active',
        });
        setSaveMsg('Coupon updated.');
        await refetch();
        return;
      }

      const r = await getSikshyaApi().post<{ ok?: boolean; id?: number }>(SIKSHYA_ENDPOINTS.admin.coupons, {
        code: code.trim(),
        discount_type: discountType,
        discount_value: clampNumberInput(discountValue, 0),
        max_uses: parseInt(maxUses, 10) || 0,
        expires_at: expiresAt || null,
        status: 'active',
      });

      const createdId = Number(r?.id) || 0;
      if (createdId > 0 && advEnabled) {
        const rules = buildAdvancedRulesPayload();
        const hasAdvanced =
          (rules.min_subtotal ?? 0) > 0 ||
          (rules.max_subtotal ?? 0) > 0 ||
          (rules.allowed_course_ids?.length ?? 0) > 0 ||
          (rules.excluded_course_ids?.length ?? 0) > 0 ||
          (rules.max_discount_amount ?? 0) > 0 ||
          (rules.per_user_limit ?? 0) > 0 ||
          rules.first_order_only ||
          (rules.valid_from && String(rules.valid_from).trim() !== '') ||
          (rules.valid_until && String(rules.valid_until).trim() !== '');
        if (hasAdvanced) {
          await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.couponAdvanced(createdId), { rules });
        }
      }

      setSaveMsg('Coupon created.');
      await refetch();
      setTab('list');
      resetEditor();
    } catch (err) {
      setSaveMsg(err instanceof Error ? err.message : 'Could not create coupon.');
    } finally {
      setSaving(false);
    }
  };

  const visibleRows = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter((r) => r.code.toLowerCase().includes(q));
  }, [rows, search]);

  const tabs = useMemo(() => {
    const items = [
      { id: 'list', label: 'Coupons' },
      { id: 'create', label: 'Create coupon' },
    ] as { id: PageTab; label: string }[];
    if (advFeature) {
      items.push({ id: 'settings', label: 'Add-on defaults' });
    }
    if (tab === 'manage' && selected) {
      items.push({ id: 'manage', label: `Manage: ${selected.code}` });
    }
    return items;
  }, [tab, selected, advFeature]);

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle="Discount codes applied at native checkout (Stripe / PayPal)."
      pageActions={
        <ButtonSecondary type="button" disabled={loading} onClick={() => refetch()}>
          Refresh
        </ButtonSecondary>
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

      <div className="mb-5">
        <HorizontalEditorTabs
          tabs={tabs}
          value={tab}
          onChange={(id) => {
            const next = id as PageTab;
            if (next === 'list') {
              setSelected(null);
              setTab('list');
              return;
            }
            if (next === 'create') {
              beginCreate();
              return;
            }
            if (next === 'settings') {
              setSelected(null);
              setTab('settings');
              resetEditor();
              return;
            }
            if (next === 'manage' && selected) {
              beginManage(selected);
            }
          }}
          ariaLabel="Coupons tabs"
        />
      </div>

      {tab === 'settings' ? (
        <AddonSettingsPage
          embedded
          config={config}
          title={title}
          addonId="coupons_advanced"
          subtitle="Storefront hints, cart promo, and checkout guidance."
          featureTitle="Advanced coupons & upsells"
          featureDescription="Tune where learners see coupon guidance and optional cart merchandising. Per-coupon rules stay on each coupon’s Manage screen."
          relatedCoreSettingsTab="payment"
          relatedCoreSettingsLabel="Payment"
        />
      ) : tab === 'list' ? (
        <ListPanel>
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 dark:border-slate-800">
            <div>
              <div className="text-sm font-semibold text-slate-900 dark:text-white">All coupons</div>
              <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Tip: click “Manage” on a coupon to view advanced rules (and set them if you have Advanced coupons).
              </div>
            </div>
            <div className="flex items-center gap-2">
              <input
                type="search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search code…"
                className="w-56 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
              />
              <ButtonPrimary type="button" onClick={beginCreate} disabled={tableMissing}>
                Create coupon
              </ButtonPrimary>
            </div>
          </div>

          {loading ? (
            <div className="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading…</div>
          ) : visibleRows.length === 0 ? (
            <ListEmptyState
              title={rows.length === 0 ? 'No coupons' : 'No matches'}
              description={
                rows.length === 0
                  ? 'Create a code learners can enter during checkout.'
                  : 'Try a different search term.'
              }
            />
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
                    <th className="px-5 py-3.5 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {visibleRows.map((r) => (
                    <tr key={r.id} className="bg-white dark:bg-slate-900">
                      <td className="px-5 py-3.5 font-mono font-semibold text-slate-800 dark:text-slate-200">
                        {r.code}
                      </td>
                      <td className="px-5 py-3.5 text-slate-700 dark:text-slate-300">
                        {r.discount_type === 'percent' ? `${r.discount_value}%` : `${r.discount_value.toFixed(2)} fixed`}
                      </td>
                      <td className="px-5 py-3.5 tabular-nums text-slate-600 dark:text-slate-400">
                        {r.used_count}
                        {r.max_uses > 0 ? ` / ${r.max_uses}` : ''}
                      </td>
                      <td className="px-5 py-3.5 text-slate-600 dark:text-slate-400">{r.expires_at || '—'}</td>
                      <td className="px-5 py-3.5 capitalize">{r.status}</td>
                      <td className="px-5 py-3.5 text-right">
                        <ButtonSecondary type="button" onClick={() => beginManage(r)}>
                          Manage
                        </ButtonSecondary>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </ListPanel>
      ) : (
        <div className="space-y-6">
          <ListPanel className="p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-sm font-semibold text-slate-900 dark:text-white">
                  {editorMode === 'create' ? 'Create coupon' : `Manage coupon: ${selected?.code || ''}`}
                </h2>
                <p className="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                  {editorMode === 'create'
                    ? 'Create a basic coupon, then optionally add advanced rules (cart thresholds, course targeting, limits, schedule).'
                    : 'Edit the code, discount, and limits above. Advanced targeting and caps are below.'}
                </p>
              </div>
              <div className="flex items-center gap-2">
                {tab === 'manage' ? (
                  <ButtonSecondary
                    type="button"
                    onClick={() => {
                      setTab('list');
                      setSelected(null);
                      resetEditor();
                    }}
                  >
                    Back to list
                  </ButtonSecondary>
                ) : null}
              </div>
            </div>

            <form id="sikshya-coupon-form" onSubmit={onSubmit} className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <label className="block text-sm">
                <span className="text-slate-600 dark:text-slate-400">Code</span>
                <input
                  required
                  value={code}
                  onChange={(e) => setCode(e.target.value.toUpperCase())}
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-700 dark:bg-slate-950"
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
              <DateTimePickerField
                kind="datetime"
                value={expiresAt}
                onChange={setExpiresAt}
                className="sm:col-span-2"
                label="Expires (optional, local time)"
              />
            </form>

            {saveMsg ? <p className="mt-3 text-sm text-slate-600 dark:text-slate-400">{saveMsg}</p> : null}

            <div className="mt-6 border-t border-slate-100 pt-6 dark:border-slate-800">
              <GatedFeatureWorkspace
                mode={advMode}
                featureId="coupons_advanced"
                config={config}
                featureTitle="Advanced coupon rules"
                featureDescription="Target carts with min/max totals, allow or block specific courses, cap percent discounts, limit uses per learner, first-purchase-only windows, and optional schedule."
                previewVariant="form"
                addonEnableTitle="Advanced coupons is not enabled"
                addonEnableDescription="Enable the Advanced coupons add-on for targeting, caps, and storefront hints."
                canEnable={Boolean(advAddon.licenseOk)}
                enableBusy={advAddon.loading}
                onEnable={() => advAddon.enable()}
                addonError={advAddon.error}
              >
                {editorMode === 'create' || (editorMode === 'manage' && selected) ? (
                  <div className="space-y-6">
                    <div>
                      <h3 className="text-sm font-semibold text-slate-900 dark:text-white">
                        {editorMode === 'create' ? 'Advanced rules (optional)' : `Advanced rules for ${selected?.code}`}
                      </h3>
                      <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Leave fields empty or zero to disable that rule. Course exclude list blocks the code if any
                        excluded course is in the cart.
                      </p>
                      {editorMode === 'manage' && advLoading ? <p className="mt-2 text-sm text-slate-500">Loading…</p> : null}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                      <label className="block text-sm">
                        <span className="text-slate-600 dark:text-slate-400">Minimum cart subtotal</span>
                        <input
                          type="number"
                          step="0.01"
                          min={0}
                          value={advMin}
                          onChange={(e) => setAdvMin(e.target.value)}
                          placeholder="0 = none"
                          className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        />
                      </label>
                      <label className="block text-sm">
                        <span className="text-slate-600 dark:text-slate-400">Maximum cart subtotal</span>
                        <input
                          type="number"
                          step="0.01"
                          min={0}
                          value={advMaxSub}
                          onChange={(e) => setAdvMaxSub(e.target.value)}
                          placeholder="0 = none"
                          className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        />
                      </label>
                      <label className="block text-sm sm:col-span-2">
                        <span className="text-slate-600 dark:text-slate-400">Max discount amount (caps % off)</span>
                        <input
                          type="number"
                          step="0.01"
                          min={0}
                          value={advMaxDiscount}
                          onChange={(e) => setAdvMaxDiscount(e.target.value)}
                          placeholder="0 = no cap"
                          className="mt-1 w-full max-w-xs rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        />
                      </label>
                      <label className="block text-sm">
                        <span className="text-slate-600 dark:text-slate-400">Uses per learner (0 = unlimited)</span>
                        <input
                          type="number"
                          min={0}
                          value={advPerUser}
                          onChange={(e) => setAdvPerUser(e.target.value)}
                          className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        />
                      </label>
                      <label className="flex items-center gap-2 text-sm sm:col-span-2">
                        <input
                          type="checkbox"
                          checked={advFirstOrder}
                          onChange={(e) => setAdvFirstOrder(e.target.checked)}
                          className="rounded border-slate-300"
                        />
                        <span className="text-slate-700 dark:text-slate-300">First paid order only (logged-in checkout)</span>
                      </label>
                      <DateTimePickerField
                        kind="datetime"
                        value={advValidFrom}
                        onChange={setAdvValidFrom}
                        className=""
                        label="Valid from (optional)"
                      />
                      <DateTimePickerField
                        kind="datetime"
                        value={advValidUntil}
                        onChange={setAdvValidUntil}
                        className=""
                        label="Valid until (optional, extra window)"
                      />
                    </div>
                    <div className="text-sm">
                      <span className="block text-slate-600 dark:text-slate-400">Allowed courses</span>
                      <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        If set, the cart must include at least one of these courses.
                      </p>
                      <div className="mt-3">
                        <MultiCoursePicker
                          value={advCourseIds}
                          onChange={setAdvCourseIds}
                          title="Select allowed courses"
                          placeholder="Click to select courses…"
                        />
                      </div>
                    </div>
                    <div className="text-sm">
                      <span className="block text-slate-600 dark:text-slate-400">Excluded courses</span>
                      <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        If the cart contains any of these courses, the code is rejected.
                      </p>
                      <div className="mt-3">
                        <MultiCoursePicker
                          value={advExcludedCourseIds}
                          onChange={setAdvExcludedCourseIds}
                          title="Select excluded courses"
                          placeholder="Click to select courses…"
                        />
                      </div>
                    </div>
                    {editorMode === 'manage' && selected ? (
                      <div className="flex flex-wrap items-center gap-3">
                        <ButtonPrimary type="button" onClick={() => void saveAdvanced()} disabled={advSaving || !advCouponId}>
                          {advSaving ? 'Saving…' : 'Save advanced rules'}
                        </ButtonPrimary>
                        {advMsg ? <span className="text-xs text-slate-600 dark:text-slate-400">{advMsg}</span> : null}
                      </div>
                    ) : (
                      <p className="text-xs text-slate-500 dark:text-slate-400">
                        Advanced rules save automatically when you create the coupon.
                      </p>
                    )}
                  </div>
                ) : (
                  <ListEmptyState
                    title="Pick a coupon"
                    description="Open “Manage” from the Coupons list to load and edit advanced rules."
                  />
                )}
              </GatedFeatureWorkspace>
            </div>

            <div className="mt-6 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
              {editorMode === 'create' ? (
                <ButtonSecondary
                  type="button"
                  onClick={() => {
                    setTab('list');
                    resetEditor();
                  }}
                  disabled={saving}
                >
                  Cancel
                </ButtonSecondary>
              ) : null}
              <ButtonPrimary type="submit" form="sikshya-coupon-form" disabled={saving || tableMissing}>
                {saving ? 'Saving…' : editorMode === 'create' ? 'Create coupon' : 'Save coupon'}
              </ButtonPrimary>
            </div>
          </ListPanel>
        </div>
      )}
    </EmbeddableShell>
  );
}
