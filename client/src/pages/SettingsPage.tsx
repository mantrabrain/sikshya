import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { NavIcon } from '../components/NavIcon';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AsyncBoundary } from '../components/shared/AsyncBoundary';
import { SkeletonCard } from '../components/shared/Skeleton';
import { useAsyncData } from '../hooks/useAsyncData';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { useAdminRouting } from '../lib/adminRouting';
import type { NavItem, SikshyaReactConfig } from '../types';
import type { SettingsField, SettingsSection } from '../types/settingsSchema';
import { CourseSettingsTab } from '../components/CourseSettingsTab';
import { EnrollmentSettingsTab } from '../components/EnrollmentSettingsTab';
import { isTruthyCheckboxValue, renderSettingsField } from './settingsRenderField';
import { normalizeTabSections } from './settingsTabUtils';
import { appViewHref } from '../lib/appUrl';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';

type SettingsSchema = Record<string, SettingsSection[]>;

type SettingsTabMeta = { id: string; label: string; description: string; icon: string };

function fieldIsVisible(f: SettingsField, values: Record<string, unknown>): boolean {
  const rules = (f as any).depends_all as Array<{ on: string; value?: string }> | undefined;
  if (Array.isArray(rules) && rules.length > 0) {
    for (const rule of rules) {
      const cur = values[rule.on];
      if (rule.value !== undefined) {
        if (String(cur ?? '') !== String(rule.value)) {
          return false;
        }
      } else if (!cur || cur === '0' || cur === false) {
        return false;
      }
    }
    return true;
  }
  const dependsOn = (f as any).depends_on as string | undefined;
  if (dependsOn) {
    const p = values[dependsOn];
    const dependsIn = (f as any).depends_in as string[] | undefined;
    if (Array.isArray(dependsIn) && dependsIn.length > 0) {
      const cur = String(p ?? '');
      return dependsIn.some((v) => String(v) === cur);
    }
    const dependsValue = (f as any).depends_value as string | undefined;
    if (dependsValue !== undefined) {
      return String(p ?? '') === String(dependsValue);
    }
    return Boolean(p);
  }
  return true;
}

type PaymentGatewayMeta = {
  id: string;
  label: string;
  description: string;
  tier: string;
  locked: boolean;
  enabled_setting_key: string;
  setting_keys: string[];
  icon_url?: string;
};

type SettingsSchemaMeta = {
  payment_gateways?: PaymentGatewayMeta[];
};

function PaymentSettingsTab(props: {
  tabSchema: SettingsSection[];
  schemaMeta: SettingsSchemaMeta;
  draft: Record<string, unknown>;
  setDraft: React.Dispatch<React.SetStateAction<Record<string, unknown>>>;
  renderField: (f: SettingsField) => React.ReactNode;
}) {
  const { tabSchema, schemaMeta, draft, setDraft, renderField } = props;
  const gateways = (
    Array.isArray(schemaMeta.payment_gateways) ? schemaMeta.payment_gateways : []
  ) as PaymentGatewayMeta[];

  const [open, setOpen] = useState<string | null>(gateways[0]?.id || 'offline');

  const byTitle = (t: string) =>
    tabSchema.find((s) => (s.title || '').toLowerCase().trim() === t.toLowerCase().trim());
  /** Prefer stable `section_key` from PHP so translated titles do not break the gateway manager. */
  const secGateways =
    tabSchema.find((s) => (s.section_key || '') === 'payment_gateways') || byTitle('Payment Gateways');

  const gatewayFields = Array.isArray(secGateways?.fields) ? secGateways!.fields! : [];

  // Pull key global fields into the gateway manager header.
  const getField = (key: string) => gatewayFields.find((f) => f.key === key);
  const fPrimary = getField('payment_gateway');
  const fTestMode = getField('enable_test_mode');
  const fOrder = getField('payment_gateways_order');

  const fieldsByKey = useMemo(() => {
    const map = new Map<string, SettingsField>();
    for (const sec of tabSchema) {
      for (const f of sec.fields || []) {
        if (f?.key) map.set(f.key, f);
      }
    }
    return map;
  }, [tabSchema]);

  const orderedGateways = useMemo(() => {
    const current = parseGatewayOrder(draft['payment_gateways_order']);
    const byId = new Map(gateways.map((g) => [g.id, g]));
    const out: PaymentGatewayMeta[] = [];

    for (const id of current) {
      const g = byId.get(id);
      if (g) out.push(g);
    }
    for (const g of gateways) {
      if (!out.find((x) => x.id === g.id)) out.push(g);
    }
    return out;
  }, [gateways, draft]);

  const setGatewayOrder = (ids: string[]) => {
    setDraft((p) => ({ ...p, payment_gateways_order: serializeGatewayOrder(ids) }));
  };

  const otherSections = tabSchema.filter(
    (s) => s !== secGateways && Array.isArray(s.fields) && s.fields.length
  );

  const GatewayRow = ({
    id,
    title,
    subtitle,
    badge,
    enabledKey,
    locked,
    canReorder,
    onMoveUp,
    onMoveDown,
  }: {
    id: string;
    title: string;
    subtitle: string;
    badge?: 'ACTIVE' | 'TEST' | 'PRO';
    enabledKey?: string;
    locked?: boolean;
    canReorder?: boolean;
    onMoveUp?: () => void;
    onMoveDown?: () => void;
  }) => {
    const selected = open === id;
    const enabled = enabledKey ? isTruthyCheckboxValue(draft[enabledKey]) : true;
    return (
      <button
        type="button"
        onClick={() => setOpen((o) => (o === id ? null : id))}
        className={`w-full rounded-2xl border px-4 py-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/35 ${
          selected
            ? 'border-brand-200 bg-white shadow-sm dark:border-brand-900/60 dark:bg-slate-900'
            : 'border-slate-200 bg-white hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900/70 dark:hover:bg-slate-900'
        }`}
      >
        <div className="flex items-center justify-between gap-4">
          <div className="flex min-w-0 items-center gap-3">
            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 p-1.5 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
              {(gateways.find((g) => g.id === id)?.icon_url || '') ? (
                <img
                  src={gateways.find((g) => g.id === id)?.icon_url}
                  alt=""
                  className="h-6 w-6 object-contain"
                  loading="lazy"
                  decoding="async"
                />
              ) : (
                <NavIcon name={id === 'offline' ? 'tag' : id === 'paypal' ? 'users' : 'badge'} className="h-5 w-5" />
              )}
            </span>
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <div className="truncate text-sm font-semibold text-slate-900 dark:text-white">{title}</div>
                {badge ? (
                  <span
                    className={`inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ${
                      badge === 'PRO'
                        ? 'bg-violet-100 text-violet-700 dark:bg-violet-950/40 dark:text-violet-200'
                        : badge === 'TEST'
                          ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200'
                          : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200'
                    }`}
                  >
                    {badge}
                  </span>
                ) : null}
              </div>
              <div className="truncate text-xs text-slate-400/90 dark:text-slate-500/80">{subtitle}</div>
            </div>
          </div>

          <div className="flex items-center gap-3">
            {canReorder ? (
              <div className="flex items-center gap-1" onClick={(e) => e.stopPropagation()}>
                <button
                  type="button"
                  className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50 disabled:opacity-40 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-200 dark:hover:bg-slate-900"
                  onClick={onMoveUp}
                  aria-label="Move up"
                  title="Move up"
                  disabled={!onMoveUp}
                >
                  ↑
                </button>
                <button
                  type="button"
                  className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50 disabled:opacity-40 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-200 dark:hover:bg-slate-900"
                  onClick={onMoveDown}
                  aria-label="Move down"
                  title="Move down"
                  disabled={!onMoveDown}
                >
                  ↓
                </button>
              </div>
            ) : null}
            {enabledKey ? (
              <label
                className={`flex items-center gap-2 text-xs font-semibold ${
                  locked ? 'opacity-60' : 'text-slate-700 dark:text-slate-200'
                }`}
                onClick={(e) => e.stopPropagation()}
              >
                <input
                  type="checkbox"
                  disabled={!!locked}
                  checked={!!enabled}
                  onChange={(e) => setDraft((p) => ({ ...p, [enabledKey]: e.target.checked ? '1' : '0' }))}
                />
                {enabled ? 'Enabled' : 'Disabled'}
              </label>
            ) : null}
            <span className={`text-slate-400 transition ${selected ? 'rotate-180' : ''}`} aria-hidden>
              ▾
            </span>
          </div>
        </div>
      </button>
    );
  };

  const gatewaySettingsFields = (g: PaymentGatewayMeta): SettingsField[] => {
    const out: SettingsField[] = [];
    for (const k of g.setting_keys || []) {
      const f = fieldsByKey.get(k);
      if (f) out.push(f);
    }
    return out;
  };

  return (
    <div className="space-y-8">
      <SectionCard
        title="Payment gateways"
        description="Enable and configure payment gateways. Expand a gateway to edit its settings."
        icon="fas fa-credit-card"
      >
        <div className="grid gap-6 lg:grid-cols-2">
          {fPrimary ? renderField(fPrimary) : null}
          <div className="lg:col-span-2">{fTestMode ? renderField(fTestMode) : null}</div>
          {/* Keep this field present for persistence even if users don’t touch it directly. */}
          <div className="hidden">{fOrder ? renderField(fOrder) : null}</div>
        </div>

        <div className="mt-6 space-y-3">
          {orderedGateways.map((g, idx) => {
            const enabledKey = g.enabled_setting_key || undefined;
            const locked = !!g.locked;

            const badge =
              locked
                ? ('PRO' as const)
                : enabledKey && isTruthyCheckboxValue(draft[enabledKey])
                  ? ('ACTIVE' as const)
                  : undefined;

            const fields = gatewaySettingsFields(g);
            const ids = orderedGateways.map((x) => x.id);

            const canReorder = orderedGateways.length > 1;
            const onMoveUp =
              idx > 0
                ? () => {
                    const next = [...ids];
                    const [item] = next.splice(idx, 1);
                    next.splice(idx - 1, 0, item);
                    setGatewayOrder(next);
                  }
                : undefined;
            const onMoveDown =
              idx < orderedGateways.length - 1
                ? () => {
                    const next = [...ids];
                    const [item] = next.splice(idx, 1);
                    next.splice(idx + 1, 0, item);
                    setGatewayOrder(next);
                  }
                : undefined;

            return (
              <div key={g.id}>
                <GatewayRow
                  id={g.id}
                  title={g.label}
                  subtitle={g.description}
                  badge={badge}
                  enabledKey={enabledKey}
                  locked={locked}
                  canReorder={canReorder}
                  onMoveUp={onMoveUp}
                  onMoveDown={onMoveDown}
                />
                {open === g.id ? (
                  <div className="mt-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    {locked ? (
                      <div className="mb-3 text-xs text-slate-500 dark:text-slate-400">
                        This gateway is available in the Pro version.
                      </div>
                    ) : null}
                    {fields.length ? (
                      <div className={`grid gap-6 lg:grid-cols-2 ${locked ? 'opacity-60' : ''}`}>
                        {fields.filter((f) => fieldIsVisible(f, draft)).map(renderField)}
                      </div>
                    ) : (
                      <div className="text-sm text-slate-600 dark:text-slate-300">
                        {locked ? 'Upgrade to configure this gateway.' : 'No additional settings for this gateway.'}
                      </div>
                    )}
                  </div>
                ) : null}
              </div>
            );
          })}
        </div>
      </SectionCard>

      {otherSections.map((sec, i) => (
        <SectionCard
          key={i}
          title={sec.title}
          description={sec.description}
          icon={sec.icon}
          locked={!!sec.locked}
          lockedReason={sec.locked_reason}
        >
          <div className="grid gap-6 lg:grid-cols-2">{(sec.fields || []).filter((f) => fieldIsVisible(f, draft)).map((f) => renderField(f))}</div>
        </SectionCard>
      ))}
    </div>
  );
}

/** Sidebar order: store → join/pay → content types → people → polish → system. */
const TAB_META: SettingsTabMeta[] = [
  { id: 'general', label: 'General', description: 'Core plugin behavior and defaults.', icon: 'puzzle' },
  {
    id: 'courses',
    label: 'Courses',
    description: 'Course pages and discovery — reviews, categories, and search.',
    icon: 'course',
  },
  {
    id: 'enrollment',
    label: 'Enrollment',
    description: 'Joining courses, checkout, buttons, completion, limits, and policies.',
    icon: 'layers',
  },
  { id: 'payment', label: 'Payment', description: 'Gateways, currency, and checkout.', icon: 'chart' },
  { id: 'lessons', label: 'Lessons', description: 'Lesson player, previews, and lesson progress.', icon: 'bookOpen' },
  { id: 'quizzes', label: 'Quizzes', description: 'Scoring, timing, and quiz behavior.', icon: 'clipboard' },
  { id: 'assignments', label: 'Assignments', description: 'Submission and grading defaults.', icon: 'badge' },
  { id: 'progress', label: 'Progress', description: 'Quiz and assignment tracking.', icon: 'chart' },
  {
    id: 'certificates',
    label: 'Certificates',
    description: 'Issuance and automation. Edit layouts in Certificates → Templates (builder).',
    icon: 'badge',
  },
  { id: 'students', label: 'Students', description: 'Learner experience and access.', icon: 'users' },
  { id: 'instructors', label: 'Instructors', description: 'Instructor permissions and workflow.', icon: 'users' },
  { id: 'notifications', label: 'Notifications', description: 'In-app and email notifications.', icon: 'helpCircle' },
  {
    id: 'integrations',
    label: 'Marketing tags',
    description:
      'Google Analytics and Meta Pixel IDs on learner-facing pages. Webhooks, API keys, and outbound automation are under Integrations in the sidebar — not here.',
    icon: 'puzzle',
  },
  { id: 'permalinks', label: 'Permalinks', description: 'Cart, checkout, account, and content URL bases.', icon: 'tag' },
  { id: 'security', label: 'Security', description: 'Roles, access, and data safety.', icon: 'cog' },
  { id: 'advanced', label: 'Advanced', description: 'Developer and system options.', icon: 'cog' },
];

function normalizeForDirtyCompare(v: unknown): string {
  if (v === null || v === undefined) return '';
  if (typeof v === 'boolean') return v ? '1' : '0';
  if (typeof v === 'number') return String(v);
  return String(v);
}

function stableNormalizeRecord(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  const keys = Object.keys(obj).sort();
  for (const k of keys) {
    out[k] = normalizeForDirtyCompare(obj[k]);
  }
  return out;
}

function parseGatewayOrder(v: unknown): string[] {
  const s = typeof v === 'string' ? v.trim() : '';
  if (!s) return [];
  return s
    .split(',')
    .map((x) => x.trim())
    .filter(Boolean);
}

function serializeGatewayOrder(ids: string[]): string {
  return ids.map((x) => x.trim()).filter(Boolean).join(',');
}

function sectionIconName(raw?: string): string {
  // Settings schema icons come from PHP as FontAwesome classes (e.g. "fas fa-link").
  // React admin uses our own SVG icon set, so map common FA names to our icon keys.
  const s = (raw || '').trim();
  const fa = s.replace(/^fas\s+fa-/, '').replace(/^fa-/, '');
  switch (fa) {
    case 'link':
      return 'tag';
    case 'folder-open':
    case 'folder':
      return 'course';
    case 'tags':
      return 'tag';
    case 'route':
      return 'layers';
    case 'cog':
    case 'cogs':
      return 'cog';
    case 'info-circle':
      return 'helpCircle';
    case 'question-circle':
      return 'helpCircle';
    case 'bell':
      return 'helpCircle';
    case 'shield-alt':
      return 'cog';
    case 'tools':
      return 'cog';
    case 'eye':
      return 'bookOpen';
    default:
      return fa || 'cog';
  }
}

function SectionCard({
  children,
  title,
  description,
  icon,
  locked,
  lockedReason,
  onUpgrade,
}: {
  children: React.ReactNode;
  title?: string;
  description?: string;
  icon?: string;
  locked?: boolean;
  lockedReason?: string;
  onUpgrade?: () => void;
}) {
  return (
    <section
      className={`rounded-2xl border p-6 shadow-sm ${
        locked
          ? 'border-violet-200 bg-violet-50/50 dark:border-violet-900/50 dark:bg-violet-950/25'
          : 'border-slate-200/80 bg-slate-50 dark:border-slate-800 dark:bg-slate-950/30'
      }`}
    >
      {title ? (
        <div className="mb-5 flex items-start gap-3">
          <span
            className={`mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg ${
              locked
                ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
            }`}
          >
            <NavIcon name={locked ? 'badge' : sectionIconName(icon)} className="h-5 w-5" />
          </span>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <h3 className="text-sm font-semibold text-slate-900 dark:text-white">{title}</h3>
              {locked ? (
                <span className="inline-flex items-center gap-1 rounded-md bg-violet-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-violet-700 dark:bg-violet-900/50 dark:text-violet-200">
                  <span aria-hidden>★</span> Pro
                </span>
              ) : null}
            </div>
            {description ? (
              <p className="mt-1 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">{description}</p>
            ) : null}
            {locked ? (
              <p className="mt-2 text-xs leading-relaxed text-violet-700 dark:text-violet-200">
                {lockedReason || 'Turn on the matching addon to edit these settings.'}
                {onUpgrade ? (
                  <>
                    {' '}
                    <button
                      type="button"
                      onClick={onUpgrade}
                      className="font-semibold underline-offset-2 hover:underline"
                    >
                      Open Addons →
                    </button>
                  </>
                ) : null}
              </p>
            ) : null}
          </div>
        </div>
      ) : null}
      {children}
    </section>
  );
}

export function SettingsPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const { confirm } = useSikshyaDialog();
  const { navigateView } = useAdminRouting();
  const qTab = config.query?.tab;
  /** Email moved to its own admin screen; avoid flashing the wrong tab. */
  const initialTab =
    qTab === 'email' ? 'general' : qTab && qTab.length ? qTab : 'general';
  const [tab, setTab] = useState<string>(initialTab);

  useEffect(() => {
    if (qTab === 'email') {
      navigateView('email', {}, { replace: true });
    }
  }, [qTab, navigateView]);

  const schema = useAsyncData(async () => {
    const res = await getSikshyaApi().get<{ success: boolean; data?: { tabs?: SettingsSchema; meta?: SettingsSchemaMeta } }>(
      SIKSHYA_ENDPOINTS.settings.schema
    );
    if (!res.success) {
      throw new Error('Could not load settings schema.');
    }
    return { tabs: res.data?.tabs || {}, meta: res.data?.meta || {} };
  }, []);

  // Avoid flicker on tab switches by caching per-tab values and rendering cached values
  // while the next tab loads in the background.
  const valuesCacheRef = useRef<Record<string, Record<string, unknown>>>({});
  const [valuesLoading, setValuesLoading] = useState(false);
  const [valuesError, setValuesError] = useState<unknown>(null);

  const cachedValuesForTab = valuesCacheRef.current[tab] ?? null;

  useEffect(() => {
    let cancelled = false;
    setValuesLoading(true);
    setValuesError(null);
    (async () => {
      try {
        const res = await getSikshyaApi().get<{ success: boolean; data?: { values?: Record<string, unknown> } }>(
          SIKSHYA_ENDPOINTS.settings.values(tab)
        );
        if (!res.success) {
          throw new Error('Could not load settings values.');
        }
        const next = res.data?.values || {};
        if (cancelled) return;
        valuesCacheRef.current = { ...valuesCacheRef.current, [tab]: next };
        // If this tab is still active, hydrate draft from the fresh values.
        setDraft(next);
        setInitialValues(next);
        setSaveError(null);
        toast.clear();
      } catch (e) {
        if (!cancelled) {
          setValuesError(e);
        }
      } finally {
        if (!cancelled) {
          setValuesLoading(false);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab]);

  const [draft, setDraft] = useState<Record<string, unknown>>({});
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<unknown>(null);
  const toast = useTopRightToast(3800);

  const [initialValues, setInitialValues] = useState<Record<string, unknown>>({});

  // On first render, use cached values immediately (no skeleton), then background refresh updates it.
  useEffect(() => {
    if (!cachedValuesForTab) return;
    setDraft(cachedValuesForTab);
    setInitialValues(cachedValuesForTab);
  }, [tab]); // intentional: only on tab switches

  useEffect(() => {
    // Keep URL in sync for shareable / refresh-safe navigation.
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url.toString());
  }, [tab]);

  const tabMeta = useMemo(() => TAB_META.find((t) => t.id === tab) || TAB_META[0], [tab]);
  const tabSchema = normalizeTabSections((schema.data?.tabs || {})[tab]);
  const schemaMeta = schema.data?.meta || {};

  const dirty = useMemo(() => {
    try {
      return JSON.stringify(stableNormalizeRecord(draft)) !== JSON.stringify(stableNormalizeRecord(initialValues));
    } catch {
      return true;
    }
  }, [draft, initialValues]);

  const onSave = async () => {
    setSaving(true);
    setSaveError(null);
    toast.clear();
    try {
      const res = await getSikshyaApi().post<{ success: boolean; message?: string; data?: { values?: Record<string, unknown> } }>(
        SIKSHYA_ENDPOINTS.settings.save,
        { tab, values: draft }
      );
      if (!res.success) {
        throw new Error(res.message || 'Save failed.');
      }
      const next = res.data?.values || {};
      setDraft(next);
      setInitialValues(next);
      const msg = res.message || 'Settings saved.';
      toast.success('Saved', msg);
    } catch (e) {
      setSaveError(e);
      toast.error('Save failed', e instanceof Error ? e.message : 'Could not save settings. Please try again.');
    } finally {
      setSaving(false);
    }
  };

  const onReset = async () => {
    const ok = await confirm({
      title: 'Reset settings?',
      message: `Reset ${tabMeta.label} settings to their default values?`,
      variant: 'danger',
      confirmLabel: 'Reset',
    });
    if (!ok) {
      return;
    }
    setSaving(true);
    setSaveError(null);
    toast.clear();
    try {
      const res = await getSikshyaApi().post<{ success: boolean; message?: string }>(SIKSHYA_ENDPOINTS.settings.reset, {
        tab,
      });
      if (!res.success) {
        throw new Error(res.message || 'Reset failed.');
      }
      // Force-refresh the active tab after reset.
      delete valuesCacheRef.current[tab];
      setValuesLoading(false);
      setValuesError(null);
      // Trigger reload by cycling tab to itself (effect runs on dependency change only).
      // We do a direct fetch here to avoid UI flicker.
      const refreshed = await getSikshyaApi().get<{ success: boolean; data?: { values?: Record<string, unknown> } }>(
        SIKSHYA_ENDPOINTS.settings.values(tab)
      );
      if (refreshed.success) {
        const next = refreshed.data?.values || {};
        valuesCacheRef.current = { ...valuesCacheRef.current, [tab]: next };
        setDraft(next);
        setInitialValues(next);
      }
      const msg = res.message || 'Settings reset.';
      toast.success('Reset', msg);
    } catch (e) {
      setSaveError(e);
      toast.error('Reset failed', e instanceof Error ? e.message : 'Could not reset settings. Please try again.');
    } finally {
      setSaving(false);
    }
  };

  const renderField = (f: SettingsField) => renderSettingsField(draft, setDraft, f);

  const onSendUsageNow = async () => {
    setSaving(true);
    setSaveError(null);
    toast.clear();
    try {
      const res = await getSikshyaApi().post<{
        success: boolean;
        message?: string;
        data?: { last_sync?: number; last_error?: unknown };
      }>(SIKSHYA_ENDPOINTS.admin.usageTrackingSendNow);

      if (!res.success) {
        throw new Error(res.message || 'Send failed.');
      }
      toast.success('Sent', res.message || 'Usage data sent.');
    } catch (e) {
      toast.error('Send failed', e instanceof Error ? e.message : 'Could not send usage data.');
    } finally {
      setSaving(false);
    }
  };

  const showShellSkeleton = schema.loading || (valuesLoading && !cachedValuesForTab);
  const effectiveError = schema.error || valuesError;

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle="Site-wide defaults for every course"
    >
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />
      <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
        <aside className="min-w-0 md:sticky md:top-6 md:h-[calc(100vh-120px)]">
          <div className="flex max-h-full flex-col overflow-hidden rounded-2xl border border-slate-200/70 bg-slate-50/80 shadow-sm dark:border-slate-800 dark:bg-slate-950/40">
            <nav
              className="min-h-0 flex-1 overflow-y-auto p-2 [-ms-overflow-style:auto] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-300/80 [&::-webkit-scrollbar-track]:bg-transparent dark:[&::-webkit-scrollbar-thumb]:bg-slate-700/70"
              aria-label="Settings sections"
            >
              <ul className="space-y-1">
                {TAB_META.map((t) => {
                  const selected = tab === t.id;
                  return (
                    <li key={t.id}>
                      <button
                        type="button"
                        onClick={() => setTab(t.id)}
                        className={`w-full rounded-xl px-3 py-2 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 ${
                          selected
                            ? 'bg-brand-600 text-white'
                            : 'text-slate-700 hover:bg-white/70 dark:text-slate-200 dark:hover:bg-slate-900/50'
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          <NavIcon name={t.icon} className={`h-4 w-4 ${selected ? 'text-white/90' : 'text-slate-400'}`} />
                          <div className="text-sm font-semibold">{t.label}</div>
                        </div>
                        <div
                          className={`mt-0.5 text-xs leading-snug ${
                            selected ? 'text-white/80' : 'text-slate-400/90 dark:text-slate-500/80'
                          }`}
                        >
                          {t.description}
                        </div>
                      </button>
                    </li>
                  );
                })}
              </ul>
            </nav>
          </div>
        </aside>

        <section className="min-w-0">
          <AsyncBoundary
            loading={showShellSkeleton}
            error={effectiveError}
            onRetry={() => {
              schema.refetch();
              // Retry the active tab fetch by forcing the shell to show skeleton, then reselecting the tab.
              delete valuesCacheRef.current[tab];
              setValuesError(null);
              setValuesLoading(true);
              setTab((t) => t);
            }}
            skeleton={
              <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <SkeletonCard rows={8} />
              </div>
            }
          >
            <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950/35">
              <div className="border-b border-slate-200/60 px-6 py-5 dark:border-slate-800/70">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0">
                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      <NavIcon name={tabMeta.icon} className="h-4 w-4" />
                      {tabMeta.label}
                    </div>
                    <h2 className="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{tabMeta.label} settings</h2>
                    <p className="mt-1 text-sm text-slate-400/90 dark:text-slate-500/80">{tabMeta.description}</p>
                  </div>
                  <div className="flex shrink-0 flex-wrap items-center justify-end gap-2 sm:min-w-[200px]">
                    <button
                      type="button"
                      disabled={saving}
                      onClick={() => void onReset()}
                      className="inline-flex items-center justify-center rounded-lg border border-slate-200/70 bg-white/80 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-200 dark:hover:bg-slate-900"
                    >
                      Reset
                    </button>
                    <ButtonPrimary type="button" disabled={saving || !dirty} onClick={() => void onSave()}>
                      {saving ? 'Saving…' : 'Save changes'}
                    </ButtonPrimary>
                  </div>
                </div>
              </div>

              {saveError ? (
                <div className="px-6 pt-4">
                  <ApiErrorPanel error={saveError} title="Settings request failed" onRetry={() => setSaveError(null)} />
                </div>
              ) : null}

              {/* Add bottom padding so the sticky footer actions don't cover the last fields. */}
              <div className={tab === 'courses' || tab === 'enrollment' ? 'py-6 pb-24' : 'px-6 py-6 pb-24'}>
                {tabSchema.length ? (
                  tab === 'payment' ? (
                    <PaymentSettingsTab
                      tabSchema={tabSchema}
                      schemaMeta={schemaMeta}
                      draft={draft}
                      setDraft={setDraft}
                      renderField={renderField}
                    />
                  ) : tab === 'courses' ? (
                    <CourseSettingsTab tabSchema={tabSchema} renderField={renderField} />
                  ) : tab === 'enrollment' ? (
                    <EnrollmentSettingsTab tabSchema={tabSchema} renderField={renderField} draft={draft} />
                  ) : (
                    <div className="space-y-8">
                      {tabSchema.map((sec, i) => {
                        const fields = Array.isArray(sec.fields) ? sec.fields : [];
                        if (!fields.length) {
                          return null;
                        }
                        const isPrivacyUsageSection =
                          tab === 'advanced' &&
                          (String((sec as { section_key?: string }).section_key || '').toLowerCase().trim() === 'privacy_usage' ||
                            String(sec.title || '').toLowerCase().trim() === 'privacy & usage');
                        return (
                          <SectionCard
                            key={i}
                            title={sec.title}
                            description={sec.description}
                            icon={sec.icon}
                            locked={!!sec.locked}
                            lockedReason={sec.locked_reason}
                            onUpgrade={sec.locked ? () => navigateView('addons', {}) : undefined}
                          >
                            <div className="grid gap-6 lg:grid-cols-2">
                              {isPrivacyUsageSection
                                ? fields.flatMap((f) => {
                                    const out: React.ReactNode[] = [renderField(f)];
                                    if (f.key === 'allow_usage_tracking') {
                                      const enabled = isTruthyCheckboxValue(draft['allow_usage_tracking']);
                                      const collectUrl =
                                        'https://docs.mantrabrain.com/sikshya-wordpress-plugin/which-types-of-data-are-being-tracked/';
                                      out.push(
                                        <div key="usage-info" className="lg:col-span-2">
                                          <div className="rounded-xl border border-slate-200/70 bg-white px-4 py-3 text-xs text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                                            <span className="font-semibold text-slate-700 dark:text-slate-200">
                                              No personal or learner details—only technical signals.
                                            </span>{' '}
                                            <span>
                                              Read the full list (opens in a new tab):{' '}
                                              <a
                                                href={collectUrl}
                                                target="_blank"
                                                rel="noopener"
                                                className="font-semibold underline-offset-2 hover:underline"
                                              >
                                                What we collect
                                              </a>
                                            </span>
                                          </div>
                                        </div>
                                      );
                                      out.push(
                                        <div key="usage-send-now" className="lg:col-span-2">
                                          <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/70 bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                            <div className="min-w-0">
                                              <div className="text-sm font-semibold text-slate-900 dark:text-white">
                                                Send usage data now
                                              </div>
                                              <div className="mt-1 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">
                                                Triggers an immediate one-time send to validate connectivity.
                                              </div>
                                            </div>
                                            <button
                                              type="button"
                                              disabled={saving || !enabled}
                                              onClick={() => void onSendUsageNow()}
                                              className="inline-flex items-center justify-center rounded-lg border border-slate-200/70 bg-white/80 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-950/30 dark:text-slate-200 dark:hover:bg-slate-900"
                                            >
                                              {saving ? 'Sending…' : 'Send now'}
                                            </button>
                                          </div>
                                          {!enabled ? (
                                            <p className="mt-2 text-xs text-slate-400/90 dark:text-slate-500/80">
                                              Enable “Share anonymous usage data” to use Send now.
                                            </p>
                                          ) : null}
                                        </div>
                                      );
                                    }
                                    return out;
                                  })
                                : fields.map(renderField)}
                            </div>
                          </SectionCard>
                        );
                      })}
                    </div>
                  )
                ) : (
                  <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/30 px-6 py-12 text-center dark:border-slate-800 dark:bg-slate-950/20">
                    <p className="text-sm font-medium text-slate-700 dark:text-slate-200">No settings defined for this tab.</p>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Add fields in the SettingsManager config to show them here.
                    </p>
                  </div>
                )}
              </div>

              {/* Sticky footer actions (duplicate of header buttons). */}
              <div className="sticky bottom-0 z-10 border-t border-slate-200/60 bg-white/95 px-6 py-4 backdrop-blur dark:border-slate-800/70 dark:bg-slate-950/80">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div className="text-xs text-slate-500 dark:text-slate-400">
                    {dirty ? 'You have unsaved changes.' : 'All changes saved.'}
                  </div>
                  <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                    <button
                      type="button"
                      disabled={saving}
                      onClick={() => void onReset()}
                      className="inline-flex items-center justify-center rounded-lg border border-slate-200/70 bg-white/80 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-200 dark:hover:bg-slate-900"
                    >
                      Reset
                    </button>
                    <ButtonPrimary type="button" disabled={saving || !dirty} onClick={() => void onSave()}>
                      {saving ? 'Saving…' : 'Save changes'}
                    </ButtonPrimary>
                  </div>
                </div>
              </div>
            </div>
          </AsyncBoundary>
        </section>
      </div>
    </EmbeddableShell>
  );
}
