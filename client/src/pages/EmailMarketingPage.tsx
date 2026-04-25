import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { getSikshyaApi } from '../api';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary, ButtonSecondary } from '../components/shared/buttons';
import { ListPanel } from '../components/shared/list/ListPanel';
import { SkeletonCard } from '../components/shared/Skeleton';
import { useAddonEnabled } from '../hooks/useAddons';
import { useAsyncData } from '../hooks/useAsyncData';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import type { SikshyaReactConfig } from '../types';

type ProviderId = 'mailchimp' | 'mailerlite' | 'brevo' | 'kit';
type FieldType = 'string' | 'password' | 'textarea' | 'bool' | 'int' | 'select' | 'csv' | 'mapping';
type MappingRow = {
  provider: ProviderId;
  remote_field: string;
  source: string;
};
type FieldDef = {
  type: FieldType;
  label: string;
  default: unknown;
  help?: string;
  choices?: Record<string, string> | null;
  sources?: Record<string, string> | null;
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

const BASE_FIELDS = ['api_key', 'audience_id'] as const;
const TOGGLE_FIELDS = ['double_opt_in', 'sync_on_enrollment', 'sync_on_completion'] as const;
const PROVIDER_ORDER: ProviderId[] = ['mailchimp', 'mailerlite', 'brevo', 'kit'];

const PROVIDER_COPY: Record<
  ProviderId,
  {
    name: string;
    badge: string;
    description: string;
    credentialLabel: string;
    credentialPlaceholder: string;
    listLabel: string;
    destinationPlaceholder: string;
    destinationHelp: string;
    mappingNoun: string;
    fieldLabel: string;
    fieldPlaceholder: string;
    fieldHint: string;
    beginnerTip: string;
    presets: Array<{ remote_field: string; source: string }>;
  }
> = {
  mailchimp: {
    name: 'Mailchimp',
    badge: 'Audience-based',
    description: 'Best when you already use Mailchimp audiences and merge tags for campaigns and automations.',
    credentialLabel: 'Mailchimp API key',
    credentialPlaceholder: 'abcd1234-us21',
    listLabel: 'Audience',
    destinationPlaceholder: 'Audience ID',
    destinationHelp: 'Find this in Audience -> Settings -> Audience name and defaults.',
    mappingNoun: 'merge fields',
    fieldLabel: 'Merge field tag',
    fieldPlaceholder: 'FNAME',
    fieldHint: 'Examples: `FNAME`, `LNAME`, `COURSE_NAME`, or any merge tag that already exists in your Mailchimp audience.',
    beginnerTip: 'Start simple: connect one audience, sync on enrollment, then map only FNAME and LNAME first.',
    presets: [
      { remote_field: 'FNAME', source: 'first_name' },
      { remote_field: 'LNAME', source: 'last_name' },
      { remote_field: 'COURSE', source: 'course_name' },
      { remote_field: 'EVENT', source: 'event' },
    ],
  },
  mailerlite: {
    name: 'MailerLite',
    badge: 'Simple groups',
    description: 'A clean beginner-friendly setup using one group plus optional custom fields.',
    credentialLabel: 'MailerLite API token',
    credentialPlaceholder: 'Paste your MailerLite API token',
    listLabel: 'Group',
    destinationPlaceholder: 'Group ID',
    destinationHelp: 'Open Subscribers -> Groups and copy the group ID you want learners added to.',
    mappingNoun: 'custom fields',
    fieldLabel: 'Custom field key',
    fieldPlaceholder: 'course_name',
    fieldHint: 'Examples: `name`, `course_name`, `course_id`, or your existing MailerLite custom field keys.',
    beginnerTip: 'MailerLite is usually the easiest first setup for noob users: one group, basic fields, done.',
    presets: [
      { remote_field: 'name', source: 'full_name' },
      { remote_field: 'course_name', source: 'course_name' },
      { remote_field: 'course_id', source: 'course_id' },
      { remote_field: 'event', source: 'event' },
    ],
  },
  brevo: {
    name: 'Brevo',
    badge: 'List + attributes',
    description: 'Great if your team already uses Brevo lists and created contact attributes inside Brevo.',
    credentialLabel: 'Brevo API key',
    credentialPlaceholder: 'Paste your Brevo API key',
    listLabel: 'List',
    destinationPlaceholder: 'List ID',
    destinationHelp: 'Use the numeric Brevo list ID. Mapped attributes must already exist in your Brevo account.',
    mappingNoun: 'contact attributes',
    fieldLabel: 'Brevo attribute key',
    fieldPlaceholder: 'FIRSTNAME',
    fieldHint: 'Examples: `FIRSTNAME`, `LASTNAME`, `COURSE_NAME`, `EVENT`. These attributes must already exist in Brevo.',
    beginnerTip: 'Before adding mappings in Brevo, create the matching contact attributes inside Brevo first.',
    presets: [
      { remote_field: 'FIRSTNAME', source: 'first_name' },
      { remote_field: 'LASTNAME', source: 'last_name' },
      { remote_field: 'COURSE_NAME', source: 'course_name' },
      { remote_field: 'EVENT', source: 'event' },
    ],
  },
  kit: {
    name: 'Kit (ConvertKit)',
    badge: 'Form-based',
    description: 'Use this when you want learners added to a Kit form and optionally enriched with existing custom fields.',
    credentialLabel: 'Kit API key',
    credentialPlaceholder: 'Paste your Kit API key',
    listLabel: 'Form',
    destinationPlaceholder: 'Form ID',
    destinationHelp: 'Use the Kit form ID you want new learners added to after subscriber upsert.',
    mappingNoun: 'custom fields',
    fieldLabel: 'Kit field name',
    fieldPlaceholder: 'first_name',
    fieldHint: 'Examples: `first_name`, `Last name`, `Course Name`, `Event`. Kit ignores custom fields that do not already exist.',
    beginnerTip: 'For Kit, confirm the basic form subscription first, then add custom fields only after that works.',
    presets: [
      { remote_field: 'first_name', source: 'first_name' },
      { remote_field: 'Last name', source: 'last_name' },
      { remote_field: 'Course Name', source: 'course_name' },
      { remote_field: 'Event', source: 'event' },
    ],
  },
};

function asProvider(value: unknown): ProviderId {
  if (value === 'mailerlite' || value === 'brevo' || value === 'kit') {
    return value;
  }
  return 'mailchimp';
}

function parseMappings(value: unknown): MappingRow[] {
  if (!Array.isArray(value)) {
    return [];
  }
  return value
    .filter((row): row is Record<string, unknown> => Boolean(row && typeof row === 'object'))
    .map((row) => ({
      provider: asProvider(row.provider),
      remote_field: typeof row.remote_field === 'string' ? row.remote_field : '',
      source: typeof row.source === 'string' ? row.source : '',
    }));
}

export function EmailMarketingPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const addonId = 'email_marketing';
  const featureOk = isFeatureEnabled(config, addonId);
  const addon = useAddonEnabled(addonId);
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';
  const [opts, setOpts] = useState<Record<string, unknown>>({});
  const [schema, setSchema] = useState<Schema>({});
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);

  const loader = useCallback(async () => {
    if (!enabled) return { ok: true, options: {}, schema: {} } as Resp;
    return getSikshyaApi().get<Resp>(`/pro/addons/${encodeURIComponent(addonId)}/settings`);
  }, [enabled, addonId]);
  const { loading, data, error, refetch } = useAsyncData(loader, [enabled, addonId]);

  useEffect(() => {
    if (data?.options) setOpts({ ...data.options });
    if (data?.schema) setSchema(data.schema);
  }, [data]);

  const provider = asProvider(opts.provider);
  const providerCopy = PROVIDER_COPY[provider];
  const sourceChoices = schema.field_mappings?.sources || {};
  const mappings = useMemo(() => parseMappings(opts.field_mappings), [opts.field_mappings]);
  const providerMappings = useMemo(() => mappings.filter((row) => row.provider === provider), [mappings, provider]);

  const setField = (name: string, value: unknown) => setOpts((prev) => ({ ...prev, [name]: value }));
  const setMappingsForProvider = (nextRows: MappingRow[]) =>
    setOpts((prev) => {
      const otherRows = parseMappings(prev.field_mappings).filter((row) => row.provider !== provider);
      return { ...prev, field_mappings: [...otherRows, ...nextRows] };
    });

  const updateProviderMapping = (index: number, patch: Partial<MappingRow>) => {
    const next = providerMappings.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch, provider } : row));
    setMappingsForProvider(next);
  };

  const addMapping = (preset?: Partial<MappingRow>) => {
    setMappingsForProvider([
      ...providerMappings,
      {
        provider,
        remote_field: preset?.remote_field || '',
        source: preset?.source || 'first_name',
      },
    ]);
  };

  const removeMapping = (index: number) => {
    setMappingsForProvider(providerMappings.filter((_, rowIndex) => rowIndex !== index));
  };

  const hasPreset = (remoteField: string) =>
    providerMappings.some((row) => row.remote_field.trim().toLowerCase() === remoteField.trim().toLowerCase());

  const onSave = async (e: FormEvent) => {
    e.preventDefault();
    setMsg(null);
    setSaving(true);
    try {
      await getSikshyaApi().post(`/pro/addons/${encodeURIComponent(addonId)}/settings`, opts);
      setMsg({ kind: 'success', text: 'Settings saved.' });
      void refetch();
    } catch (err) {
      setMsg({ kind: 'error', text: err instanceof Error ? err.message : 'Save failed' });
    } finally {
      setSaving(false);
    }
  };

  const renderInputField = (name: (typeof BASE_FIELDS)[number]) => {
    const def = schema[name];
    if (!def) {
      return null;
    }
    const value = opts[name];
    const inputClass =
      'mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950';

    return (
      <label key={name} className="block text-sm">
        <span className="font-medium text-slate-900 dark:text-white">
          {name === 'api_key' ? providerCopy.credentialLabel : `${providerCopy.listLabel} ID`}
        </span>
        <input
          type={def.type === 'password' ? 'password' : 'text'}
          autoComplete={def.type === 'password' ? 'new-password' : undefined}
          value={typeof value === 'string' ? value : ''}
          onChange={(e) => setField(name, e.target.value)}
          className={inputClass}
          placeholder={name === 'api_key' ? providerCopy.credentialPlaceholder : providerCopy.destinationPlaceholder}
        />
        <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
          {name === 'api_key' ? def.help : providerCopy.destinationHelp}
        </span>
      </label>
    );
  };

  const renderToggleField = (name: (typeof TOGGLE_FIELDS)[number]) => {
    const def = schema[name];
    if (!def) {
      return null;
    }
    return (
      <label key={name} className="flex items-start gap-3 text-sm">
        <input
          type="checkbox"
          checked={Boolean(opts[name])}
          onChange={(e) => setField(name, e.target.checked)}
          className="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
        />
        <span>
          <span className="font-medium text-slate-900 dark:text-white">{def.label}</span>
          {def.help ? <span className="block text-xs text-slate-500 dark:text-slate-400">{def.help}</span> : null}
        </span>
      </label>
    );
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Sync learners into your email marketing provider when they enroll or complete courses."
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId={addonId}
        config={config}
        featureTitle="Email marketing"
        featureDescription="Native Mailchimp, MailerLite, Brevo, and Kit sync. Sikshya will upsert subscribers, attach list membership, and map learner/course data into provider fields."
        previewVariant="form"
        addonEnableTitle="Email marketing is not enabled"
        addonEnableDescription="Turn on the Email marketing add-on to configure provider sync and beginner-friendly setup."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
        <div className="space-y-6">
          {error ? <ApiErrorPanel error={error} title="Could not load settings" onRetry={() => refetch()} /> : null}

          <ListPanel className="p-6">
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.65fr)]">
              <div>
                <h2 className="text-base font-semibold text-slate-900 dark:text-white">Beginner-friendly setup</h2>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                  Choose the provider your team already uses, paste the two values it asks for, enable sync, then add field mappings only if you need extra data.
                </p>
                <div className="mt-4 grid gap-3 md:grid-cols-2">
                  {PROVIDER_ORDER.map((id) => {
                    const info = PROVIDER_COPY[id];
                    const active = provider === id;
                    return (
                      <button
                        key={id}
                        type="button"
                        onClick={() => setField('provider', id)}
                        className={`rounded-2xl border p-4 text-left transition ${
                          active
                            ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/20 dark:bg-brand-950/30'
                            : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900'
                        }`}
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div>
                            <div className="text-sm font-semibold text-slate-900 dark:text-white">{info.name}</div>
                            <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">{info.description}</div>
                          </div>
                          <span className="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {info.badge}
                          </span>
                        </div>
                      </button>
                    );
                  })}
                </div>
              </div>

              <div className="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-5 text-sm text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-950/40 dark:text-indigo-200">
                <div className="text-sm font-semibold">Recommended flow</div>
                <ol className="mt-3 space-y-2 text-xs leading-relaxed">
                  <li>1. Pick one provider only.</li>
                  <li>2. Paste the API key and destination ID.</li>
                  <li>3. Turn on enrollment sync first.</li>
                  <li>4. Save, test with one learner, then add mappings.</li>
                </ol>
                <p className="mt-3 rounded-xl bg-white/70 px-3 py-2 text-xs dark:bg-slate-900/40">{providerCopy.beginnerTip}</p>
              </div>
            </div>
          </ListPanel>

          <ListPanel className="p-6">
            {loading ? (
              <SkeletonCard rows={7} />
            ) : (
              <form onSubmit={onSave} className="space-y-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-950/40">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <h2 className="text-sm font-semibold text-slate-900 dark:text-white">1. Connect {providerCopy.name}</h2>
                      <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Paste the credentials and the exact audience, group, list, or form destination where learners should be added.
                      </p>
                    </div>
                    <span className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                      {providerCopy.listLabel}-based sync
                    </span>
                  </div>
                  <div className="mt-4 grid gap-4 sm:grid-cols-2">{BASE_FIELDS.map((name) => renderInputField(name))}</div>
                </div>

                <div className="space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900/60">
                  <div>
                    <h2 className="text-sm font-semibold text-slate-900 dark:text-white">2. Choose when to sync</h2>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Keep this simple at first. Most noob users start with enrollment sync and only add completion sync later.
                    </p>
                  </div>
                  {TOGGLE_FIELDS.map((name) => renderToggleField(name))}
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-950/40">
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                      <h2 className="text-sm font-semibold text-slate-900 dark:text-white">3. {schema.field_mappings?.label || 'List field mappings'}</h2>
                      <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Map Sikshya fields into your {providerCopy.listLabel.toLowerCase()} {providerCopy.mappingNoun}. Skip this until after your first successful sync if you want the easiest setup.
                      </p>
                    </div>
                    <ButtonSecondary onClick={() => addMapping()}>Add mapping</ButtonSecondary>
                  </div>

                  <div className="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-xs text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-950/40 dark:text-indigo-200">
                    <p className="font-medium">
                      {providerCopy.name} {providerCopy.mappingNoun}
                    </p>
                    <p className="mt-1">{providerCopy.fieldHint}</p>
                  </div>

                  <div className="mt-4 flex flex-wrap gap-2">
                    {providerCopy.presets.map((preset) => (
                      <button
                        key={`${provider}-${preset.remote_field}`}
                        type="button"
                        className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        disabled={hasPreset(preset.remote_field)}
                        onClick={() => addMapping(preset)}
                      >
                        Add {preset.remote_field}
                      </button>
                    ))}
                  </div>

                  <div className="mt-5 space-y-3">
                    {providerMappings.length ? (
                      providerMappings.map((row, index) => (
                        <div
                          key={`${provider}-${index}`}
                          className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/60 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto]"
                        >
                          <label className="block text-sm">
                            <span className="font-medium text-slate-900 dark:text-white">{providerCopy.fieldLabel}</span>
                            <input
                              type="text"
                              value={row.remote_field}
                              onChange={(e) => updateProviderMapping(index, { remote_field: e.target.value })}
                              className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                              placeholder={providerCopy.fieldPlaceholder}
                            />
                          </label>

                          <label className="block text-sm">
                            <span className="font-medium text-slate-900 dark:text-white">Sikshya source</span>
                            <select
                              value={row.source}
                              onChange={(e) => updateProviderMapping(index, { source: e.target.value })}
                              className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                            >
                              {Object.entries(sourceChoices).map(([source, label]) => (
                                <option key={source} value={source}>
                                  {label}
                                </option>
                              ))}
                            </select>
                          </label>

                          <div className="flex items-end">
                            <button
                              type="button"
                              className="rounded-lg px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/30"
                              onClick={() => removeMapping(index)}
                            >
                              Remove
                            </button>
                          </div>
                        </div>
                      ))
                    ) : (
                      <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
                        No {providerCopy.mappingNoun} mapped yet. Add mappings only if your destination expects extra fields like course name, event, or learner names.
                      </div>
                    )}
                  </div>
                </div>

                <div className="flex items-center gap-3">
                  <ButtonPrimary type="submit" disabled={saving}>
                    {saving ? 'Saving…' : '4. Save settings'}
                  </ButtonPrimary>
                  {msg ? (
                    <span
                      className={
                        msg.kind === 'success'
                          ? 'text-sm text-emerald-700 dark:text-emerald-300'
                          : 'text-sm text-rose-700 dark:text-rose-300'
                      }
                    >
                      {msg.text}
                    </span>
                  ) : null}
                </div>
              </form>
            )}
          </ListPanel>

          <ListPanel className="p-6">
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Next steps</h2>
            <ul className="mt-3 space-y-2 text-sm">
              <li className="text-slate-700 dark:text-slate-200">Mailchimp: find your Audience ID in <span className="font-medium">Audience {'->'} Settings {'->'} Audience name and defaults</span>.</li>
              <li className="text-slate-700 dark:text-slate-200">MailerLite: find your Group ID in <span className="font-medium">Subscribers {'->'} Groups</span>.</li>
              <li className="text-slate-700 dark:text-slate-200">Brevo: use the numeric List ID and create any mapped contact attributes in Brevo first.</li>
              <li className="text-slate-700 dark:text-slate-200">Kit: use a Form ID and remember Kit ignores custom fields that do not already exist.</li>
              <li className="text-slate-700 dark:text-slate-200">After saving, enroll one test learner and confirm the record lands in the right destination before adding more mappings.</li>
            </ul>
          </ListPanel>
        </div>
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}

