import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { getSikshyaApi } from '../api';
import { appViewHref } from '../lib/appUrl';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { SkeletonCard } from '../components/shared/Skeleton';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import type { SikshyaReactConfig } from '../types';
import { __ } from '../lib/i18n';

type FieldType = 'string' | 'password' | 'textarea' | 'bool' | 'int' | 'select' | 'csv';

type FieldDef = {
  type: FieldType;
  label: string;
  default: unknown;
  help?: string;
  choices?: Record<string, string> | null;
  min?: number | null;
  max?: number | null;
};

type Schema = Record<string, FieldDef>;

type Resp = {
  ok?: boolean;
  addon?: string;
  options?: Record<string, unknown>;
  schema?: Schema;
};

export type AddonSettingsPageProps = {
  config: SikshyaReactConfig;
  /** Page title rendered in the AppShell header. */
  title: string;
  /** Addon id, e.g. "live_classes". */
  addonId: string;
  /** Short subtitle under the title. */
  subtitle: string;
  /** Headline shown in the gated workspace. */
  featureTitle: string;
  /** One-paragraph "what is this" description. */
  featureDescription: string;
  /** Optional list of "next steps" rendered below the form to guide the noob. */
  nextSteps?: { label: string; href?: string; description?: string }[];
  /** Render extra section above the schema form (e.g. provider hints). */
  preformSection?: React.ReactNode;
  /** When true, omit the AppShell wrapper (the parent owns the shell). */
  embedded?: boolean;
  /** When false, hides the “what you are editing” callout (parent already explained scope). */
  showSettingsScopeCallout?: boolean;
  /**
   * If set, the callout links to Settings with this tab for overlapping core behaviour
   * (e.g. `quizzes` when this add-on extends random pools).
   */
  relatedCoreSettingsTab?: string;
  /** Label for the Settings deep link; defaults to a title-cased `relatedCoreSettingsTab`. */
  relatedCoreSettingsLabel?: string;
};

/**
 * Generic schema-driven settings page for addons that previously had no React UI.
 * Backed by `/sikshya/v1/pro/addons/<id>/settings`.
 */
function humanizeSettingsTab(tab: string): string {
  const t = tab.replace(/_/g, ' ').trim();
  if (!t) return 'Settings';
  return t.replace(/\b\w/g, (c) => c.toUpperCase());
}

export function AddonSettingsPage(props: AddonSettingsPageProps) {
  const {
    config,
    title,
    addonId,
    subtitle,
    featureTitle,
    featureDescription,
    nextSteps,
    preformSection,
    embedded,
    showSettingsScopeCallout = true,
    relatedCoreSettingsTab,
    relatedCoreSettingsLabel,
  } = props;
  const featureOk = isFeatureEnabled(config, addonId);
  const addon = useAddonEnabled(addonId);
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [opts, setOpts] = useState<Record<string, unknown>>({});
  const [schema, setSchema] = useState<Schema>({});
  const [saving, setSaving] = useState(false);
  const toast = useTopRightToast();

  const loader = useCallback(async () => {
    if (!enabled) return { ok: true, options: {}, schema: {} } as Resp;
    return getSikshyaApi().get<Resp>(`/pro/addons/${encodeURIComponent(addonId)}/settings`);
  }, [enabled, addonId]);
  const { loading, data, error, refetch } = useAsyncData(loader, [enabled, addonId]);

  useEffect(() => {
    if (data?.options) setOpts({ ...data.options });
    if (data?.schema) setSchema(data.schema);
  }, [data]);

  const onSave = async (e: FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await getSikshyaApi().post(`/pro/addons/${encodeURIComponent(addonId)}/settings`, opts);
      toast.success(__('Saved', 'sikshya'), 'Settings saved.');
      void refetch();
    } catch (err) {
      toast.error(__('Save failed', 'sikshya'), err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const setField = (name: string, value: unknown) => setOpts((prev) => ({ ...prev, [name]: value }));

  const renderField = (name: string, def: FieldDef) => {
    const value = opts[name];
    // Canonical form-control class: matches shared/form/FormInput so this page
    // gets the same focus ring, dark-mode background, and disabled state as
    // the rest of the admin. Kept as a string here (vs. <FormInput>) because
    // the renderField switch is small and the constant version is the
    // least-invasive migration.
    const inputClass =
      'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 ' +
      'placeholder:text-slate-400 ' +
      'focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 ' +
      'disabled:cursor-not-allowed disabled:opacity-50 ' +
      'dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500';

    switch (def.type) {
      case 'bool':
        return (
          <label key={name} className="flex items-start gap-3 text-sm">
            <input
              type="checkbox"
              checked={Boolean(value)}
              onChange={(e) => setField(name, e.target.checked)}
              className="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-700 dark:focus:ring-offset-slate-900"
            />
            <span>
              <span className="font-medium text-slate-900 dark:text-white">{def.label}</span>
              {def.help ? <span className="block text-xs text-slate-500 dark:text-slate-400">{def.help}</span> : null}
            </span>
          </label>
        );
      case 'select':
        return (
          <label key={name} className="block text-sm">
            <span className="font-medium text-slate-900 dark:text-white">{def.label}</span>
            <select
              value={typeof value === 'string' ? value : ''}
              onChange={(e) => setField(name, e.target.value)}
              className={inputClass}
            >
              {Object.entries(def.choices || {}).map(([k, label]) => (
                <option key={k} value={k}>
                  {label}
                </option>
              ))}
            </select>
            {def.help ? <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">{def.help}</span> : null}
          </label>
        );
      case 'int':
        return (
          <label key={name} className="block text-sm">
            <span className="font-medium text-slate-900 dark:text-white">{def.label}</span>
            <input
              type="number"
              min={def.min ?? undefined}
              max={def.max ?? undefined}
              value={typeof value === 'number' ? value : Number(value || 0)}
              onChange={(e) => setField(name, parseInt(e.target.value, 10) || 0)}
              className={`${inputClass} w-40`}
            />
            {def.help ? <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">{def.help}</span> : null}
          </label>
        );
      case 'textarea':
        return (
          <label key={name} className="block text-sm">
            <span className="font-medium text-slate-900 dark:text-white">{def.label}</span>
            <textarea
              rows={6}
              value={typeof value === 'string' ? value : ''}
              onChange={(e) => setField(name, e.target.value)}
              className={`${inputClass} font-mono text-xs`}
            />
            {def.help ? <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">{def.help}</span> : null}
          </label>
        );
      case 'password':
        return (
          <label key={name} className="block text-sm">
            <span className="font-medium text-slate-900 dark:text-white">{def.label}</span>
            <input
              type="password"
              autoComplete="new-password"
              value={typeof value === 'string' ? value : ''}
              onChange={(e) => setField(name, e.target.value)}
              className={inputClass}
            />
            {def.help ? <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">{def.help}</span> : null}
          </label>
        );
      case 'csv':
      case 'string':
      default:
        return (
          <label key={name} className="block text-sm">
            <span className="font-medium text-slate-900 dark:text-white">{def.label}</span>
            <input
              type="text"
              value={typeof value === 'string' ? value : ''}
              onChange={(e) => setField(name, e.target.value)}
              className={inputClass}
            />
            {def.help ? <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">{def.help}</span> : null}
          </label>
        );
    }
  };

  // Group bool fields together at the bottom for visual scan; keep schema order otherwise.
  const fieldEntries = Object.entries(schema);
  const inputFields = fieldEntries.filter(([, d]) => d.type !== 'bool');
  const boolFields = fieldEntries.filter(([, d]) => d.type === 'bool');

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={subtitle}
    >
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />
      <GatedFeatureWorkspace
        mode={mode}
        featureId={addonId}
        config={config}
        featureTitle={featureTitle}
        featureDescription={featureDescription}
        previewVariant="form"
        addonEnableTitle={`${featureTitle} is not enabled`}
        addonEnableDescription={`Turn on the ${featureTitle} add-on to surface configuration and start using its features.`}
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => addon.enable()}
        addonError={addon.error}
      >
        <div className="space-y-6">
          {error ? <ApiErrorPanel error={error} title={__('Could not load settings', 'sikshya')} onRetry={() => refetch()} /> : null}

          {showSettingsScopeCallout ? (
            <div className="rounded-xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-xs leading-relaxed text-slate-600 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300">
              <p className="font-semibold text-slate-800 dark:text-slate-100">{__('What you are editing', 'sikshya')}</p>
              <p className="mt-1">
                This form saves{' '}
                <span className="font-medium text-slate-900 dark:text-white">{featureTitle}</span> add-on options for
                the whole site. Core LMS defaults (catalog, checkout, core quiz rules, email, security) stay under{' '}
                <a
                  href={appViewHref(config, 'settings')}
                  className="font-medium text-brand-600 underline-offset-2 hover:underline dark:text-brand-400"
                >
                  Settings
                </a>
                .
              </p>
              {relatedCoreSettingsTab ? (
                <p className="mt-2">
                  Related overlap:{' '}
                  <a
                    href={appViewHref(config, 'settings', { tab: relatedCoreSettingsTab })}
                    className="font-medium text-brand-600 underline-offset-2 hover:underline dark:text-brand-400"
                  >
                    Settings → {relatedCoreSettingsLabel || humanizeSettingsTab(relatedCoreSettingsTab)}
                  </a>
                  .
                </p>
              ) : null}
            </div>
          ) : null}

          {preformSection ? (
            <div className="rounded-xl border border-brand-100 bg-brand-50/60 p-4 text-xs text-brand-900 dark:border-brand-900/40 dark:bg-brand-950/40 dark:text-brand-200">
              {preformSection}
            </div>
          ) : null}

          <ListPanel className="p-6">
            {loading ? (
              <SkeletonCard rows={5} />
            ) : (
              <form onSubmit={onSave} className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2">{inputFields.map(([n, d]) => renderField(n, d))}</div>

                {boolFields.length > 0 ? (
                  <div className="space-y-3 rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                    {boolFields.map(([n, d]) => renderField(n, d))}
                  </div>
                ) : null}

                <div className="flex items-center gap-3">
                  <ButtonPrimary type="submit" disabled={saving}>
                    {saving ? __('Saving…', 'sikshya') : __('Save settings', 'sikshya')}
                  </ButtonPrimary>
                </div>
              </form>
            )}
          </ListPanel>

          {nextSteps && nextSteps.length > 0 ? (
            <ListPanel className="p-6">
              <h2 className="text-sm font-semibold text-slate-900 dark:text-white">{__('Next steps', 'sikshya')}</h2>
              <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Where to actually use this add-on once it's configured.
              </p>
              <ul className="mt-3 space-y-2 text-sm">
                {nextSteps.map((step, i) => (
                  <li key={i} className="flex items-start gap-2">
                    <span className="mt-1 inline-flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700 dark:bg-brand-900/60 dark:text-brand-200">
                      {i + 1}
                    </span>
                    <span className="flex-1">
                      {step.href ? (
                        <a
                          href={step.href}
                          className="font-medium text-brand-600 hover:underline dark:text-brand-300"
                        >
                          {step.label}
                        </a>
                      ) : (
                        <span className="font-medium text-slate-900 dark:text-white">{step.label}</span>
                      )}
                      {step.description ? (
                        <span className="block text-xs text-slate-500 dark:text-slate-400">{step.description}</span>
                      ) : null}
                    </span>
                  </li>
                ))}
              </ul>
            </ListPanel>
          ) : null}
        </div>
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}
