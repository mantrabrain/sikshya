import { useEffect, useMemo, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { NavIcon } from '../components/NavIcon';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AsyncBoundary } from '../components/shared/AsyncBoundary';
import { SkeletonCard } from '../components/shared/Skeleton';
import { useAsyncData } from '../hooks/useAsyncData';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import type { NavItem, SikshyaReactConfig } from '../types';

type SettingsField = {
  key: string;
  type?: string;
  label?: string;
  description?: string;
  default?: string | number;
  placeholder?: string;
  options?: Record<string, string>;
  min?: number;
  max?: number;
};

type SettingsSection = {
  title?: string;
  icon?: string;
  description?: string;
  fields?: SettingsField[];
};

type SettingsSchema = Record<string, SettingsSection[]>;

type SettingsTabMeta = { id: string; label: string; description: string; icon: string };

const TAB_META: SettingsTabMeta[] = [
  { id: 'general', label: 'General', description: 'Core plugin behavior and defaults.', icon: 'puzzle' },
  { id: 'courses', label: 'Courses', description: 'Catalog and course-level defaults.', icon: 'course' },
  { id: 'lessons', label: 'Lessons', description: 'Lesson display and learning flow.', icon: 'bookOpen' },
  { id: 'quizzes', label: 'Quizzes', description: 'Scoring, timing, and quiz behavior.', icon: 'clipboard' },
  { id: 'assignments', label: 'Assignments', description: 'Submission and grading defaults.', icon: 'badge' },
  { id: 'students', label: 'Students', description: 'Learner experience and access.', icon: 'users' },
  { id: 'instructors', label: 'Instructors', description: 'Instructor permissions and workflow.', icon: 'users' },
  { id: 'enrollment', label: 'Enrollment', description: 'Access rules and enrollment rules.', icon: 'layers' },
  { id: 'progress', label: 'Progress', description: 'Completion, tracking, and certificates.', icon: 'chart' },
  { id: 'certificates', label: 'Certificates', description: 'Templates and issuance rules.', icon: 'badge' },
  { id: 'payment', label: 'Payment', description: 'Monetization and checkout settings.', icon: 'chart' },
  { id: 'email', label: 'Email', description: 'Email sender and templates.', icon: 'plusDocument' },
  { id: 'notifications', label: 'Notifications', description: 'In-app and email notifications.', icon: 'helpCircle' },
  { id: 'integrations', label: 'Integrations', description: 'Third-party connections.', icon: 'puzzle' },
  { id: 'permalinks', label: 'Permalinks', description: 'Cart, checkout, account, and content URL bases.', icon: 'tag' },
  { id: 'security', label: 'Security', description: 'Roles, access, and data safety.', icon: 'cog' },
  { id: 'advanced', label: 'Advanced', description: 'Developer and system options.', icon: 'cog' },
];

function fieldToStringValue(v: unknown): string {
  if (v === null || v === undefined) return '';
  return String(v);
}

function isTruthyCheckboxValue(v: unknown): boolean {
  return v === true || v === 1 || v === '1' || v === 'yes' || v === 'on';
}

export function SettingsPage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const { confirm } = useSikshyaDialog();
  const qTab = config.query?.tab;
  const initialTab = qTab && qTab.length ? qTab : 'general';
  const [tab, setTab] = useState<string>(initialTab);

  const schema = useAsyncData(async () => {
    const res = await getSikshyaApi().get<{ success: boolean; data?: { tabs?: SettingsSchema } }>(SIKSHYA_ENDPOINTS.settings.schema);
    if (!res.success) {
      throw new Error('Could not load settings schema.');
    }
    return res.data?.tabs || {};
  }, []);

  const values = useAsyncData(async () => {
    const res = await getSikshyaApi().get<{ success: boolean; data?: { values?: Record<string, unknown> } }>(
      SIKSHYA_ENDPOINTS.settings.values(tab)
    );
    if (!res.success) {
      throw new Error('Could not load settings values.');
    }
    return res.data?.values || {};
  }, [tab]);

  const [draft, setDraft] = useState<Record<string, unknown>>({});
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<unknown>(null);
  const [saveMsg, setSaveMsg] = useState<string | null>(null);

  const [initialValues, setInitialValues] = useState<Record<string, unknown>>({});

  useEffect(() => {
    if (!values.data) return;
    setDraft(values.data);
    setInitialValues(values.data);
    setSaveMsg(null);
    setSaveError(null);
  }, [values.data, tab]);

  useEffect(() => {
    if (qTab && qTab.length && qTab !== tab) {
      setTab(qTab);
    }
  }, [qTab, tab]);

  useEffect(() => {
    // Keep URL in sync for shareable / refresh-safe navigation.
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url.toString());
  }, [tab]);

  const tabMeta = useMemo(() => TAB_META.find((t) => t.id === tab) || TAB_META[0], [tab]);
  const tabSchema = (schema.data || {})[tab] || [];

  const dirty = useMemo(() => {
    try {
      return JSON.stringify(draft) !== JSON.stringify(initialValues);
    } catch {
      return true;
    }
  }, [draft, initialValues]);

  const onSave = async () => {
    setSaving(true);
    setSaveError(null);
    setSaveMsg(null);
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
      setSaveMsg(res.message || 'Settings saved.');
    } catch (e) {
      setSaveError(e);
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
    setSaveMsg(null);
    try {
      const res = await getSikshyaApi().post<{ success: boolean; message?: string }>(SIKSHYA_ENDPOINTS.settings.reset, {
        tab,
      });
      if (!res.success) {
        throw new Error(res.message || 'Reset failed.');
      }
      await values.refetch();
      setSaveMsg(res.message || 'Settings reset.');
    } catch (e) {
      setSaveError(e);
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
      subtitle="Global settings"
    >
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
        <aside className="min-w-0">
          <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="border-b border-slate-100 px-4 py-4 dark:border-slate-800">
              <div className="flex items-start gap-3">
                <span className="mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-950/60 dark:text-brand-300">
                  <NavIcon name="puzzle" className="h-5 w-5" />
                </span>
                <div className="min-w-0 flex-1">
                  <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Settings
                  </div>
                  <p className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">Sikshya configuration</p>
                  <p className="mt-1 text-xs leading-snug text-slate-500 dark:text-slate-400">
                    Manage global defaults and behavior across the LMS.
                  </p>
                </div>
              </div>
            </div>
            {/* No inner scrollbar: let the page scroll naturally. */}
            <nav className="p-2" aria-label="Settings sections">
              <ul className="space-y-1">
                {TAB_META.map((t) => {
                  const selected = tab === t.id;
                  return (
                    <li key={t.id}>
                      <button
                        type="button"
                        onClick={() => setTab(t.id)}
                        className={`w-full rounded-xl px-3 py-2 text-left transition ${
                          selected
                            ? 'bg-brand-600 text-white'
                            : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/60'
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          <NavIcon name={t.icon} className={`h-4 w-4 ${selected ? 'text-white/90' : 'text-slate-400'}`} />
                          <div className="text-sm font-semibold">{t.label}</div>
                        </div>
                        <div className={`mt-0.5 text-xs leading-snug ${selected ? 'text-white/85' : 'text-slate-500 dark:text-slate-400'}`}>
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
            loading={schema.loading || values.loading}
            error={schema.error || values.error}
            onRetry={() => {
              schema.refetch();
              values.refetch();
            }}
            skeleton={
              <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <SkeletonCard rows={8} />
              </div>
            }
          >
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
              <div className="border-b border-slate-100 px-6 py-5 dark:border-slate-800">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                  <div className="min-w-0">
                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      <NavIcon name={tabMeta.icon} className="h-4 w-4" />
                      {tabMeta.label}
                    </div>
                    <h2 className="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{tabMeta.label} settings</h2>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{tabMeta.description}</p>
                    {saveMsg ? (
                      <p className="mt-2 text-xs font-medium text-emerald-700 dark:text-emerald-300">{saveMsg}</p>
                    ) : null}
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      disabled={saving}
                      onClick={() => void onReset()}
                      className="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
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

              <div className="px-6 py-6">
                {tabSchema.length ? (
                  <div className="space-y-8">
                    {tabSchema.map((sec, i) => {
                      const fields = Array.isArray(sec.fields) ? sec.fields : [];
                      if (!fields.length) {
                        return null;
                      }
                      return (
                        <section key={i} className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/50">
                          {sec.title ? (
                            <div className="mb-5 flex items-start gap-3">
                              <span className="mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                <NavIcon name={(sec.icon || 'cog').replace(/^fas fa-/, '')} className="h-5 w-5" />
                              </span>
                              <div className="min-w-0">
                                <h3 className="text-sm font-semibold text-slate-900 dark:text-white">{sec.title}</h3>
                                {sec.description ? (
                                  <p className="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{sec.description}</p>
                                ) : null}
                              </div>
                            </div>
                          ) : null}

                          <div className="grid gap-6 lg:grid-cols-2">
                            {fields.map((f) => {
                              const k = f.key;
                              const type = f.type || 'text';
                              const cur = draft[k];

                              const label = f.label || k;
                              const desc = f.description || '';

                              if (type === 'checkbox') {
                                const checked = isTruthyCheckboxValue(cur);
                                return (
                                  <div key={k} className="lg:col-span-2">
                                    <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
                                      <input
                                        type="checkbox"
                                        className="mt-1 h-4 w-4"
                                        checked={checked}
                                        onChange={(e) =>
                                          setDraft((p) => ({ ...p, [k]: e.target.checked ? '1' : '0' }))
                                        }
                                      />
                                      <span className="min-w-0">
                                        <span className="block text-sm font-semibold text-slate-900 dark:text-white">{label}</span>
                                        {desc ? (
                                          <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">{desc}</span>
                                        ) : null}
                                      </span>
                                    </label>
                                  </div>
                                );
                              }

                              if (type === 'select') {
                                const opts = f.options || {};
                                return (
                                  <div key={k}>
                                    <label className="block text-sm font-medium text-slate-800 dark:text-slate-200" htmlFor={k}>
                                      {label}
                                    </label>
                                    {desc ? <p className="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{desc}</p> : null}
                                    <select
                                      id={k}
                                      className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                      value={fieldToStringValue(cur ?? f.default ?? '')}
                                      onChange={(e) => setDraft((p) => ({ ...p, [k]: e.target.value }))}
                                    >
                                      {Object.entries(opts).map(([ov, ol]) => (
                                        <option key={ov} value={ov}>
                                          {ol}
                                        </option>
                                      ))}
                                    </select>
                                  </div>
                                );
                              }

                              if (type === 'textarea') {
                                return (
                                  <div key={k} className="lg:col-span-2">
                                    <label className="block text-sm font-medium text-slate-800 dark:text-slate-200" htmlFor={k}>
                                      {label}
                                    </label>
                                    {desc ? <p className="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{desc}</p> : null}
                                    <textarea
                                      id={k}
                                      rows={4}
                                      className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                      value={fieldToStringValue(cur ?? f.default ?? '')}
                                      onChange={(e) => setDraft((p) => ({ ...p, [k]: e.target.value }))}
                                      placeholder={f.placeholder || ''}
                                    />
                                  </div>
                                );
                              }

                              const inputType = type === 'number' ? 'number' : type === 'email' ? 'email' : 'text';
                              return (
                                <div key={k}>
                                  <label className="block text-sm font-medium text-slate-800 dark:text-slate-200" htmlFor={k}>
                                    {label}
                                  </label>
                                  {desc ? <p className="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{desc}</p> : null}
                                  <input
                                    id={k}
                                    type={inputType}
                                    className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                    value={fieldToStringValue(cur ?? f.default ?? '')}
                                    onChange={(e) => setDraft((p) => ({ ...p, [k]: e.target.value }))}
                                    placeholder={f.placeholder || ''}
                                    min={typeof f.min === 'number' ? f.min : undefined}
                                    max={typeof f.max === 'number' ? f.max : undefined}
                                  />
                                </div>
                              );
                            })}
                          </div>
                        </section>
                      );
                    })}
                  </div>
                ) : (
                  <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/30 px-6 py-12 text-center dark:border-slate-800 dark:bg-slate-950/20">
                    <p className="text-sm font-medium text-slate-700 dark:text-slate-200">No settings defined for this tab.</p>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Add fields in the SettingsManager config to show them here.
                    </p>
                  </div>
                )}
              </div>
            </div>
          </AsyncBoundary>
        </section>
      </div>
    </AppShell>
  );
}
