import { useCallback, useMemo, useState, type FormEvent } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { AddonEnablePanel } from '../components/AddonEnablePanel';
import { FeaturePreviewSkeleton } from '../components/FeaturePreviewSkeleton';
import { PlanUpgradeOverlay } from '../components/PlanUpgradeOverlay';
import { PREMIUM_GATE_VIEWPORT_MIN_H, PremiumGatedSurface } from '../components/PremiumGatedSurface';
import { SIKSHYA_ADMIN_PAGE_FULL_WIDTH } from '../constants/shellLayout';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { ListEmptyState } from '../components/shared/list/ListEmptyState';
import { ListPanel } from '../components/shared/list/ListPanel';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { appViewHref } from '../lib/appUrl';
import { getLicensing, isFeatureEnabled } from '../lib/licensing';
import type { SikshyaReactConfig } from '../types';

type WebhookEndpointRowV2 = {
  id: number;
  label?: string;
  event_key?: string;
  delivery_url: string;
  is_active: boolean;
  has_secret?: boolean;
  failure_streak?: number;
  last_status_code?: number | null;
  last_error?: string | null;
  last_success_at?: string | null;
  last_failure_at?: string | null;
  created_at?: string | null;
};

type ApiKeyRow = {
  id: number;
  owner_user_id?: number;
  label: string;
  key_prefix: string;
  scopes_json?: string | null;
  expires_at?: string | null;
  revoked: number;
  last_used_at?: string | null;
  created_at: string;
};

type OAuthAppRow = {
  id: number;
  owner_user_id: number;
  name: string;
  client_id: string;
  redirect_uris_json?: string | null;
  scopes_json?: string | null;
  revoked: number;
  created_at: string;
};

const API_SCOPES: Array<{ value: string; title: string; hint: string }> = [
  { value: 'catalog:read', title: 'Catalog (read)', hint: 'Read courses and course pages.' },
  { value: 'users:read', title: 'Users (read)', hint: 'Read learner profiles (PII). Use carefully.' },
  { value: 'enrollments:read', title: 'Enrollments (read)', hint: 'Read enrollment and progress status.' },
  { value: 'enrollments:write', title: 'Enrollments (write)', hint: 'Enroll/unenroll users. High impact.' },
  { value: 'commerce:read', title: 'Commerce (read)', hint: 'Read order data.' },
  { value: 'commerce:write', title: 'Commerce (write)', hint: 'Create/modify orders and payments. Highest risk.' },
  { value: 'learning:read', title: 'Learning (read)', hint: 'Read learning progress.' },
  { value: 'learning:write', title: 'Learning (write)', hint: 'Write progress/completions. High impact.' },
];

const WEBHOOK_EVENTS: Array<{ value: string; title: string; hint: string }> = [
  {
    value: 'enrollment.created',
    title: 'Learner enrolled',
    hint: 'Runs the moment a learner is enrolled (paid checkout, manual add, or free signup). Perfect for adding contacts to a CRM list.',
  },
  {
    value: 'enrollment.deleted',
    title: 'Learner unenrolled',
    hint: 'Runs when a learner is removed from a course (manual removal or access revoked). Useful for subscription cancellations and cleanup.',
  },
  {
    value: 'order.fulfilled',
    title: 'Order completed',
    hint: 'Runs after a learner’s payment is confirmed and they are enrolled. Use this to notify your CRM or accounting tool.',
  },
  {
    value: 'lesson.completed',
    title: 'Lesson finished',
    hint: 'Runs when a learner completes a lesson. Great for progress-based nudges or unlocking external content.',
  },
  {
    value: 'quiz.completed',
    title: 'Quiz finished',
    hint: 'Runs when a learner completes a quiz. Use the payload score to trigger remediation or congratulations.',
  },
  {
    value: 'assignment.submitted',
    title: 'Assignment submitted',
    hint: 'Runs when a learner submits an assignment. Perfect for Slack/Email notifications or helpdesk tickets.',
  },
  {
    value: 'course.completed',
    title: 'Course finished',
    hint: 'Runs when a learner completes every required item in a course. Great for celebration emails or loyalty workflows.',
  },
  {
    value: 'certificate.issued',
    title: 'Certificate created',
    hint: 'Runs when Sikshya saves a new certificate row. Ideal for badges, LinkedIn automations, or secure archives.',
  },
  {
    value: 'drip.lesson_unlocked',
    title: 'Drip: lesson unlocked',
    hint: 'Runs when a lesson becomes available due to drip rules. Useful for “new lesson available” notifications.',
  },
  {
    value: 'drip.course_unlocked',
    title: 'Drip: course unlocked',
    hint: 'Runs when a full course becomes available due to drip rules.',
  },
  {
    value: 'review.submitted',
    title: 'Review submitted',
    hint: 'Runs when a learner submits a course review (before or after approval depending on your workflow).',
  },
  {
    value: 'review.approved',
    title: 'Review approved',
    hint: 'Runs when an admin approves a review.',
  },
  {
    value: 'review.rejected',
    title: 'Review rejected',
    hint: 'Runs when an admin rejects a review.',
  },
  {
    value: 'course.rating_updated',
    title: 'Course rating updated',
    hint: 'Runs when course rating aggregates change (after review changes).',
  },
  {
    value: '*',
    title: 'Everything (advanced)',
    hint: 'Sends all supported event types to the same URL. Only pick this if you understand how to separate events in Zapier/Make.',
  },
  {
    value: 'webhook.test',
    title: 'Test event (manual)',
    hint: 'A manual test payload you can send from Sikshya to validate your Zap or receiver URL.',
  },
];

async function copyText(label: string, text: string, onFail: (m: string) => void) {
  try {
    await navigator.clipboard.writeText(text);
  } catch {
    try {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    } catch {
      onFail(`Could not copy automatically. Please copy ${label} manually.`);
    }
  }
}

export function IntegrationsPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const lic = getLicensing(config);
  const dialog = useSikshyaDialog();

  const whFeature = isFeatureEnabled(config, 'webhooks') || isFeatureEnabled(config, 'zapier');
  const keyFeature = isFeatureEnabled(config, 'public_api_keys');
  const whAddon = useAddonEnabled('webhooks');
  const zapierAddon = useAddonEnabled('zapier');
  const keyAddon = useAddonEnabled('public_api_keys');

  const webhooksOn = whFeature && (Boolean(whAddon.enabled) || Boolean(zapierAddon.enabled));
  const keysOn = keyFeature && Boolean(keyAddon.enabled);

  const [whEvent, setWhEvent] = useState('order.fulfilled');
  const [whUrl, setWhUrl] = useState('');
  const [whSecret, setWhSecret] = useState('');
  const [whBusy, setWhBusy] = useState(false);
  const toast = useTopRightToast();

  const [keyLabel, setKeyLabel] = useState('');
  const [keyScopes, setKeyScopes] = useState<string[]>(['catalog:read']);
  const [keyExpiryDays, setKeyExpiryDays] = useState(90);
  const [keyBusy, setKeyBusy] = useState(false);
  const [freshKey, setFreshKey] = useState<string | null>(null);
  const [pingToken, setPingToken] = useState('');
  const [pingBusy, setPingBusy] = useState(false);
  const [pingResult, setPingResult] = useState<string | null>(null);

  const [appName, setAppName] = useState('');
  const [appRedirect, setAppRedirect] = useState('');
  const [appScopes, setAppScopes] = useState<string[]>(['catalog:read']);
  const [appBusy, setAppBusy] = useState(false);
  const [freshAppSecret, setFreshAppSecret] = useState<{ clientId: string; clientSecret: string } | null>(null);

  const whLoader = useCallback(async () => {
    if (!webhooksOn) {
      return { ok: true, items: [] as WebhookEndpointRowV2[] };
    }
    return getSikshyaApi().get<{ ok?: boolean; items?: WebhookEndpointRowV2[] }>(SIKSHYA_ENDPOINTS.scale.webhooksV2Endpoints);
  }, [webhooksOn]);

  const deliveriesLoader = useCallback(async () => {
    if (!webhooksOn) {
      return { ok: true, items: [] as Array<Record<string, unknown>> };
    }
    return getSikshyaApi().get<{ ok?: boolean; items?: Array<Record<string, unknown>> }>(SIKSHYA_ENDPOINTS.scale.webhooksV2Deliveries);
  }, [webhooksOn]);

  const keysLoader = useCallback(async () => {
    if (!keysOn) {
      return { ok: true, rows: [] as ApiKeyRow[] };
    }
    return getSikshyaApi().get<{ ok?: boolean; rows?: ApiKeyRow[] }>(SIKSHYA_ENDPOINTS.scale.publicApiKeys);
  }, [keysOn]);

  const wh = useAsyncData(whLoader, [webhooksOn]);
  const deliveries = useAsyncData(deliveriesLoader, [webhooksOn]);
  const keys = useAsyncData(keysLoader, [keysOn]);

  const appsLoader = useCallback(async () => {
    if (!keysOn) {
      return { ok: true, rows: [] as OAuthAppRow[] };
    }
    return getSikshyaApi().get<{ ok?: boolean; rows?: OAuthAppRow[] }>(SIKSHYA_ENDPOINTS.scale.publicApiApps);
  }, [keysOn]);

  const apps = useAsyncData(appsLoader, [keysOn]);

  const addonsHref = useMemo(() => appViewHref(config, 'addons'), [config]);

  const selectedEventHelp = useMemo(
    () => WEBHOOK_EVENTS.find((e) => e.value === whEvent)?.hint ?? '',
    [whEvent]
  );

  const addWebhook = async (e: FormEvent) => {
    e.preventDefault();
    toast.clear();
    if (!whUrl.trim()) {
      toast.error('Missing URL', 'Please paste the URL Zapier, Make, or your developer gave you.');
      return;
    }
    setWhBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.scale.webhooksV2Endpoints, {
        label: '',
        events: [whEvent],
        delivery_url: whUrl.trim(),
        secret: whSecret.trim() || undefined,
      });
      setWhUrl('');
      setWhSecret('');
      toast.success('Saved', 'Webhook saved. We will POST JSON to that address when the event happens.');
      wh.refetch();
    } catch (err) {
      toast.error('Save failed', err instanceof Error ? err.message : 'Could not save webhook.');
    } finally {
      setWhBusy(false);
    }
  };

  const removeWebhook = async (row: WebhookEndpointRowV2) => {
    const ok = await dialog.confirm({
      title: 'Remove this webhook?',
      message: (
        <p>
          Stops sending <span className="font-mono text-xs">{row.event_key || '*'}</span> events to{' '}
          <span className="break-all font-medium">{row.delivery_url}</span>. You can add it again later.
        </p>
      ),
      confirmLabel: 'Remove webhook',
      variant: 'danger',
    });
    if (!ok) {
      return;
    }
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.scale.webhooksV2Endpoint(row.id), { is_active: false });
      wh.refetch();
    } catch {
      await dialog.alert({ title: 'Something went wrong', message: 'We could not remove that webhook. Try again.' });
    }
  };

  const testWebhook = async (row: WebhookEndpointRowV2) => {
    toast.clear();
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.scale.webhooksV2EndpointTest(row.id), {});
      toast.success('Queued', 'Queued a test delivery. Check your receiver and the delivery log.');
    } catch (err) {
      toast.error('Queue failed', err instanceof Error ? err.message : 'Could not queue test delivery.');
    }
  };

  const createKey = async (e: FormEvent) => {
    e.preventDefault();
    toast.clear();
    setFreshKey(null);
    if (!keyLabel.trim()) {
      toast.error('Missing label', 'Give this key a short name so you remember what uses it (for example “Mobile app”).');
      return;
    }
    setKeyBusy(true);
    try {
      const res = await getSikshyaApi().post<{ ok?: boolean; api_key?: string; message?: string }>(
        SIKSHYA_ENDPOINTS.scale.publicApiKeys,
        { label: keyLabel.trim(), scopes: keyScopes, expires_at: new Date(Date.now() + keyExpiryDays * 86400000).toISOString() }
      );
      if (res.api_key) {
        setFreshKey(res.api_key);
        setKeyLabel('');
        setKeyScopes(['catalog:read']);
        setKeyExpiryDays(90);
        toast.success('Created', res.message || 'Key created.');
        keys.refetch();
      } else {
        toast.error('Create failed', res.message || 'Unexpected response from server.');
      }
    } catch (err) {
      toast.error('Create failed', err instanceof Error ? err.message : 'Could not create key.');
    } finally {
      setKeyBusy(false);
    }
  };

  const revokeKey = async (row: ApiKeyRow) => {
    const ok = await dialog.confirm({
      title: 'Revoke this API key?',
      message: (
        <p>
          Anything still using <span className="font-mono text-xs">{row.key_prefix}…</span> will stop working
          immediately. This cannot be undone.
        </p>
      ),
      confirmLabel: 'Revoke key',
      variant: 'danger',
    });
    if (!ok) {
      return;
    }
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.scale.publicApiKey(row.id));
      keys.refetch();
    } catch {
      await dialog.alert({ title: 'Something went wrong', message: 'We could not revoke that key. Try again.' });
    }
  };

  const runPing = async () => {
    setPingResult(null);
    const token = pingToken.trim();
    if (!token) {
      setPingResult('Paste an API key first, then tap “Test key”.');
      return;
    }
    setPingBusy(true);
    try {
      const body = await getSikshyaApi().get<{ ok?: boolean; site?: string; message?: string }>(
        SIKSHYA_ENDPOINTS.scale.publicApiPing,
        {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        }
      );
      if (body && body.ok) {
        setPingResult(`Success — key works. Site name: ${body.site || '—'}`);
      } else {
        setPingResult(`Did not work: ${body?.message || 'Unknown error'}`);
      }
    } catch (err) {
      setPingResult(err instanceof Error ? err.message : 'Network error. Check your connection and try again.');
    } finally {
      setPingBusy(false);
    }
  };

  const createApp = async (e: FormEvent) => {
    e.preventDefault();
    toast.clear();
    setFreshAppSecret(null);
    if (!appName.trim()) {
      toast.error('Missing name', 'Give the app a name (for example “Mobile app”).');
      return;
    }
    if (!appRedirect.trim()) {
      toast.error('Missing redirect URL', 'Add a redirect URL (must start with https://).');
      return;
    }
    setAppBusy(true);
    try {
      const res = await getSikshyaApi().post<{ ok?: boolean; client_id?: string; client_secret?: string; message?: string }>(
        SIKSHYA_ENDPOINTS.scale.publicApiApps,
        { name: appName.trim(), redirect_uris: [appRedirect.trim()], scopes: appScopes }
      );
      if (res.client_id && res.client_secret) {
        setFreshAppSecret({ clientId: res.client_id, clientSecret: res.client_secret });
        setAppName('');
        setAppRedirect('');
        setAppScopes(['catalog:read']);
        toast.success('Created', res.message || 'App created.');
        apps.refetch();
      } else {
        toast.error('Create failed', res.message || 'Unexpected response from server.');
      }
    } catch (err) {
      toast.error('Create failed', err instanceof Error ? err.message : 'Could not create app.');
    } finally {
      setAppBusy(false);
    }
  };

  const revokeApp = async (row: OAuthAppRow) => {
    const ok = await dialog.confirm({
      title: 'Revoke this OAuth app?',
      message: (
        <p>
          Any tokens issued to <span className="font-mono text-xs">{row.client_id}</span> may stop working. You can create a
          new app later.
        </p>
      ),
      confirmLabel: 'Revoke app',
      variant: 'danger',
    });
    if (!ok) return;
    try {
      await getSikshyaApi().delete(SIKSHYA_ENDPOINTS.scale.publicApiApp(row.id));
      apps.refetch();
    } catch {
      await dialog.alert({ title: 'Something went wrong', message: 'We could not revoke that app. Try again.' });
    }
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Connect Sikshya to Zapier, custom scripts, or mobile apps — without touching code unless you want to."
    >
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />
      <div className={`${SIKSHYA_ADMIN_PAGE_FULL_WIDTH} space-y-8`}>
        <section className="rounded-2xl border border-sky-200 bg-sky-50/90 p-5 text-sm leading-relaxed text-sky-950 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
          <h2 className="text-base font-semibold text-sky-950 dark:text-sky-50">Start here</h2>
          <ol className="mt-3 list-decimal space-y-2 pl-5">
            <li>
              <strong>Webhooks</strong> let other websites react when something happens in Sikshya (for example “new
              order”). You only paste a URL your automation tool gives you.
            </li>
            <li>
              <strong>API keys</strong> let trusted apps read a small “ping” endpoint today; you can expand usage later
              with your developer.
            </li>
            <li>
              Both features live under{' '}
              <a href={addonsHref} className="font-semibold text-brand-700 underline underline-offset-2 dark:text-brand-300">
                Addons
              </a>
              — turn them on first, then come back to this page.
            </li>
          </ol>
        </section>

        {/* Webhooks — full width so premium overlays span the content column */}
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Webhooks</h2>
                <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                  Send a tiny JSON message to another service when important learning events happen.
                </p>
              </div>
            </div>

            {!whFeature ? (
              <div className={`relative isolate mt-4 w-full ${PREMIUM_GATE_VIEWPORT_MIN_H}`}>
                <div className={`pointer-events-none opacity-70 ${PREMIUM_GATE_VIEWPORT_MIN_H}`} aria-hidden>
                  <FeaturePreviewSkeleton variant="generic" />
                </div>
                <PlanUpgradeOverlay
                  config={config}
                  featureId="webhooks"
                  featureTitle="Webhooks"
                  description="Send JSON to Zapier, Make, or custom HTTPS endpoints when orders complete, courses finish, or certificates are issued."
                />
              </div>
            ) : !webhooksOn ? (
              <div className={`relative isolate mt-4 w-full ${PREMIUM_GATE_VIEWPORT_MIN_H}`}>
                <div className={`pointer-events-none select-none opacity-[0.72] ${PREMIUM_GATE_VIEWPORT_MIN_H}`} aria-hidden>
                  <FeaturePreviewSkeleton variant="generic" />
                </div>
                <PremiumGatedSurface>
                  <AddonEnablePanel
                    variant="premium"
                    title="Turn on webhooks"
                    description="Enable the Webhooks addon. Nothing is sent until you add a URL below."
                    canEnable={Boolean(whAddon.licenseOk) || Boolean(zapierAddon.licenseOk)}
                    enableBusy={whAddon.loading || zapierAddon.loading}
                    onEnable={() => {
                      // Prefer enabling Webhooks (generic) but allow Zapier-only customers to proceed.
                      if (whAddon.licenseOk) void whAddon.enable();
                      else void zapierAddon.enable();
                    }}
                    upgradeUrl={lic.upgradeUrl}
                    error={whAddon.error || zapierAddon.error}
                  />
                </PremiumGatedSurface>
              </div>
            ) : (
              <>
                <form onSubmit={addWebhook} className="mt-5 space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">
                      When should we notify your URL?
                    </label>
                    <select
                      value={whEvent}
                      onChange={(e) => setWhEvent(e.target.value)}
                      className="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                    >
                      {WEBHOOK_EVENTS.map((ev) => (
                        <option key={ev.value} value={ev.value}>
                          {ev.title}
                        </option>
                      ))}
                    </select>
                    <p className="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{selectedEventHelp}</p>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">
                      Webhook URL (HTTPS recommended)
                    </label>
                    <input
                      type="url"
                      value={whUrl}
                      onChange={(e) => setWhUrl(e.target.value)}
                      placeholder="https://hooks.zapier.com/…"
                      className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                    />
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Paste the exact URL from Zapier, Make.com, or your developer. We only accept HTTPS in production
                      sites.
                    </p>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">
                      Signing secret <span className="font-normal text-slate-500">(optional)</span>
                    </label>
                    <input
                      type="password"
                      autoComplete="new-password"
                      value={whSecret}
                      onChange={(e) => setWhSecret(e.target.value)}
                      placeholder="Same secret you configure in Zapier"
                      className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                    />
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      If you set this, we add an <span className="font-mono">X-Sikshya-Signature</span> header so the
                      receiver can trust the payload.
                    </p>
                  </div>
                  <ButtonPrimary type="submit" disabled={whBusy}>
                    {whBusy ? 'Saving…' : 'Save webhook'}
                  </ButtonPrimary>
                </form>

                <div className="mt-8">
                  <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Active webhooks</h3>
                  {wh.error ? (
                    <div className="mt-2">
                      <ApiErrorPanel error={wh.error} title="Webhooks" onRetry={() => wh.refetch()} />
                    </div>
                  ) : (
                    <ListPanel className="mt-3">
                      {wh.loading ? (
                        <div className="p-6 text-center text-sm text-slate-500">Loading…</div>
                      ) : (wh.data?.items?.length ?? 0) === 0 ? (
                        <ListEmptyState
                          title="No webhooks yet"
                          description="When you save your first URL it will appear here. You can add more than one — for example separate Zapier Zaps per event."
                        />
                      ) : (
                        <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                          {(wh.data?.items ?? []).map((r) => (
                            <li key={r.id} className="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                              <div className="min-w-0">
                                <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                  {r.event_key || '*'}
                                </div>
                                <div className="mt-0.5 break-all font-mono text-xs text-slate-800 dark:text-slate-200">
                                  {r.delivery_url}
                                </div>
                                {r.last_error ? (
                                  <div className="mt-1 text-xs text-red-700 dark:text-red-300">Last error: {r.last_error}</div>
                                ) : null}
                              </div>
                              <div className="flex shrink-0 flex-wrap items-center gap-2">
                                <button
                                  type="button"
                                  className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800"
                                  onClick={() => void testWebhook(r)}
                                >
                                  Test
                                </button>
                                <button
                                  type="button"
                                  className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40"
                                  onClick={() => void removeWebhook(r)}
                                >
                                  Disable
                                </button>
                              </div>
                            </li>
                          ))}
                        </ul>
                      )}
                    </ListPanel>
                  )}
                </div>

                <div className="mt-8">
                  <div className="flex items-center justify-between gap-3">
                    <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Recent deliveries</h3>
                    <button
                      type="button"
                      onClick={() => deliveries.refetch()}
                      className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800"
                    >
                      Refresh
                    </button>
                  </div>
                  {deliveries.error ? (
                    <div className="mt-2">
                      <ApiErrorPanel error={deliveries.error} title="Deliveries" onRetry={() => deliveries.refetch()} />
                    </div>
                  ) : (
                    <ListPanel className="mt-3">
                      {deliveries.loading ? (
                        <div className="p-6 text-center text-sm text-slate-500">Loading…</div>
                      ) : (deliveries.data?.items?.length ?? 0) === 0 ? (
                        <ListEmptyState title="No deliveries yet" description="Once events trigger, deliveries will appear here." />
                      ) : (
                        <div className="overflow-x-auto">
                          <table className="min-w-full text-left text-xs">
                            <thead className="border-b border-slate-200 text-slate-500 dark:border-slate-800 dark:text-slate-400">
                              <tr>
                                <th className="px-4 py-3 font-semibold">Status</th>
                                <th className="px-4 py-3 font-semibold">Event</th>
                                <th className="px-4 py-3 font-semibold">Endpoint</th>
                                <th className="px-4 py-3 font-semibold">Attempts</th>
                                <th className="px-4 py-3 font-semibold">Created</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                              {(deliveries.data?.items ?? []).map((r: any) => (
                                <tr key={String(r.id)}>
                                  <td className="px-4 py-3 font-semibold">{String(r.status || '')}</td>
                                  <td className="px-4 py-3 font-mono">{String(r.event_key || '')}</td>
                                  <td className="px-4 py-3">{String(r.endpoint_id || '')}</td>
                                  <td className="px-4 py-3">{String(r.attempt_count || 0) + '/' + String(r.max_attempts || 0)}</td>
                                  <td className="px-4 py-3">{String(r.created_at || '')}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      )}
                    </ListPanel>
                  )}
                </div>
              </>
            )}
        </section>

        {/* API keys — full width */}
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">API keys</h2>
            <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
              Keys let external apps prove they are allowed to talk to your site. Treat them like passwords.
            </p>

            {!keyFeature ? (
              <div className={`relative isolate mt-4 w-full ${PREMIUM_GATE_VIEWPORT_MIN_H}`}>
                <div className={`pointer-events-none opacity-70 ${PREMIUM_GATE_VIEWPORT_MIN_H}`} aria-hidden>
                  <FeaturePreviewSkeleton variant="generic" />
                </div>
                <PlanUpgradeOverlay
                  config={config}
                  featureId="public_api_keys"
                  featureTitle="API keys"
                  description="Let trusted apps authenticate to your site with revocable keys — start with a safe ping endpoint, expand with your developer."
                />
              </div>
            ) : !keyAddon.enabled ? (
              <div className={`relative isolate mt-4 w-full ${PREMIUM_GATE_VIEWPORT_MIN_H}`}>
                <div className={`pointer-events-none select-none opacity-[0.72] ${PREMIUM_GATE_VIEWPORT_MIN_H}`} aria-hidden>
                  <FeaturePreviewSkeleton variant="generic" />
                </div>
                <PremiumGatedSurface>
                  <AddonEnablePanel
                    variant="premium"
                    title="Turn on API keys"
                    description="Enable the “Public API & API keys” addon. You can create keys right after."
                    canEnable={Boolean(keyAddon.licenseOk)}
                    enableBusy={keyAddon.loading}
                    onEnable={() => keyAddon.enable()}
                    upgradeUrl={lic.upgradeUrl}
                    error={keyAddon.error}
                  />
                </PremiumGatedSurface>
              </div>
            ) : (
              <>
                {freshKey ? (
                  <div className="mt-5 rounded-2xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
                    <p className="text-sm font-semibold text-amber-950 dark:text-amber-100">Copy this key now</p>
                    <p className="mt-1 text-xs leading-relaxed text-amber-900/90 dark:text-amber-200/90">
                      For your security we never show the full key again. If you lose it, revoke the old key and create a
                      new one.
                    </p>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                      <code className="max-w-full flex-1 overflow-x-auto rounded-lg bg-white px-2 py-1.5 font-mono text-xs text-slate-900 dark:bg-slate-900 dark:text-slate-100">
                        {freshKey}
                      </code>
                      <ButtonPrimary
                        type="button"
                        onClick={() =>
                          void copyText('the API key', freshKey, async (m) => {
                            await dialog.alert({ title: 'Copy manually', message: m });
                          })
                        }
                      >
                        Copy key
                      </ButtonPrimary>
                      <button
                        type="button"
                        className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                        onClick={() => setFreshKey(null)}
                      >
                        I’ve stored it safely
                      </button>
                    </div>
                  </div>
                ) : null}

                <form onSubmit={createKey} className="mt-5 space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">
                      Friendly name
                    </label>
                    <input
                      value={keyLabel}
                      onChange={(e) => setKeyLabel(e.target.value)}
                      placeholder="e.g. Zapier read-only"
                      className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">Scopes</label>
                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                      {API_SCOPES.map((s) => {
                        const checked = keyScopes.includes(s.value);
                        return (
                          <label key={s.value} className="flex items-start gap-2 rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <input
                              type="checkbox"
                              checked={checked}
                              onChange={(e) => {
                                const on = e.target.checked;
                                setKeyScopes((prev) => (on ? [...prev, s.value] : prev.filter((x) => x !== s.value)));
                              }}
                              className="mt-0.5"
                            />
                            <span className="min-w-0">
                              <span className="block font-semibold text-slate-900 dark:text-white">{s.title}</span>
                              <span className="mt-0.5 block text-xs text-slate-600 dark:text-slate-400">{s.hint}</span>
                            </span>
                          </label>
                        );
                      })}
                    </div>
                    <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                      Tip: start with read-only scopes. You can always revoke and create a stricter key later.
                    </p>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">Expiry (days)</label>
                    <input
                      type="number"
                      min={1}
                      max={3650}
                      value={keyExpiryDays}
                      onChange={(e) => setKeyExpiryDays(Math.max(1, Math.min(3650, Number(e.target.value) || 90)))}
                      className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                    />
                  </div>
                  <ButtonPrimary type="submit" disabled={keyBusy}>
                    {keyBusy ? 'Creating…' : 'Create new key'}
                  </ButtonPrimary>
                </form>

                <div className="mt-6 rounded-xl border border-slate-100 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/50">
                  <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Test a key (optional)</h3>
                  <p className="mt-1 text-xs text-slate-600 dark:text-slate-400">
                    Paste a key and tap test — we call a safe “ping” endpoint so you know the format is correct before
                    you plug it into another system.
                  </p>
                  <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                    <input
                      value={pingToken}
                      onChange={(e) => setPingToken(e.target.value)}
                      placeholder="sk_live_…"
                      className="w-full flex-1 rounded-xl border border-slate-200 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                    />
                    <ButtonPrimary type="button" disabled={pingBusy} onClick={() => void runPing()}>
                      {pingBusy ? 'Testing…' : 'Test key'}
                    </ButtonPrimary>
                  </div>
                  {pingResult ? <p className="mt-2 text-xs text-slate-700 dark:text-slate-300">{pingResult}</p> : null}
                </div>

                <div className="mt-8">
                  <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Existing keys</h3>
                  {keys.error ? (
                    <div className="mt-2">
                      <ApiErrorPanel error={keys.error} title="API keys" onRetry={() => keys.refetch()} />
                    </div>
                  ) : (
                    <ListPanel className="mt-3">
                      {keys.loading ? (
                        <div className="p-6 text-center text-sm text-slate-500">Loading…</div>
                      ) : (keys.data?.rows?.length ?? 0) === 0 ? (
                        <ListEmptyState
                          title="No keys yet"
                          description="Create a key for each integration (never reuse the same key everywhere). Revoke a key if you think it leaked."
                        />
                      ) : (
                        <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                          {(keys.data?.rows ?? []).map((r) => (
                            <li
                              key={r.id}
                              className="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                              <div>
                                <div className="text-sm font-medium text-slate-900 dark:text-white">{r.label}</div>
                                <div className="font-mono text-xs text-slate-500">
                                  {r.key_prefix}… · {r.revoked ? 'revoked' : 'active'}
                                </div>
                              </div>
                              {!r.revoked ? (
                                <button
                                  type="button"
                                  className="shrink-0 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40"
                                  onClick={() => void revokeKey(r)}
                                >
                                  Revoke
                                </button>
                              ) : null}
                            </li>
                          ))}
                        </ul>
                      )}
                    </ListPanel>
                  )}
                </div>

                <div className="mt-10">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <h3 className="text-sm font-semibold text-slate-900 dark:text-white">OAuth apps</h3>
                      <p className="mt-1 text-xs text-slate-600 dark:text-slate-400">
                        Use OAuth when you need user consent and per-user access tokens (recommended for third-party apps).
                      </p>
                    </div>
                  </div>

                  {apps.error ? (
                    <div className="mt-2">
                      <ApiErrorPanel error={apps.error} title="OAuth apps" onRetry={() => apps.refetch()} />
                    </div>
                  ) : (
                    <ListPanel className="mt-3">
                      {freshAppSecret ? (
                        <div className="m-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
                          <p className="text-sm font-semibold text-amber-950 dark:text-amber-100">Store these credentials now</p>
                          <p className="mt-1 text-xs text-amber-900/90 dark:text-amber-200/90">
                            We only show the client secret once.
                          </p>
                          <div className="mt-3 space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                              <span className="text-xs font-semibold text-slate-700 dark:text-slate-200">Client ID</span>
                              <code className="flex-1 overflow-x-auto rounded-lg bg-white px-2 py-1.5 font-mono text-xs text-slate-900 dark:bg-slate-900 dark:text-slate-100">
                                {freshAppSecret.clientId}
                              </code>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                              <span className="text-xs font-semibold text-slate-700 dark:text-slate-200">Client secret</span>
                              <code className="flex-1 overflow-x-auto rounded-lg bg-white px-2 py-1.5 font-mono text-xs text-slate-900 dark:bg-slate-900 dark:text-slate-100">
                                {freshAppSecret.clientSecret}
                              </code>
                            </div>
                            <ButtonPrimary
                              type="button"
                              onClick={() =>
                                void copyText('the client secret', freshAppSecret.clientSecret, async (m) => {
                                  await dialog.alert({ title: 'Copy manually', message: m });
                                })
                              }
                            >
                              Copy client secret
                            </ButtonPrimary>
                          </div>
                        </div>
                      ) : null}

                      <form onSubmit={createApp} className="p-4 space-y-3">
                        <div>
                          <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">App name</label>
                          <input
                            value={appName}
                            onChange={(e) => setAppName(e.target.value)}
                            placeholder="e.g. Mobile app"
                            className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">Redirect URL</label>
                          <input
                            value={appRedirect}
                            onChange={(e) => setAppRedirect(e.target.value)}
                            placeholder="https://yourapp.com/oauth/callback"
                            className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-slate-800 dark:text-slate-200">Scopes</label>
                          <div className="mt-2 grid gap-2 sm:grid-cols-2">
                            {API_SCOPES.map((s) => {
                              const checked = appScopes.includes(s.value);
                              return (
                                <label key={s.value} className="flex items-start gap-2 rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                                  <input
                                    type="checkbox"
                                    checked={checked}
                                    onChange={(e) => {
                                      const on = e.target.checked;
                                      setAppScopes((prev) => (on ? [...prev, s.value] : prev.filter((x) => x !== s.value)));
                                    }}
                                    className="mt-0.5"
                                  />
                                  <span className="min-w-0">
                                    <span className="block font-semibold text-slate-900 dark:text-white">{s.title}</span>
                                    <span className="mt-0.5 block text-xs text-slate-600 dark:text-slate-400">{s.hint}</span>
                                  </span>
                                </label>
                              );
                            })}
                          </div>
                        </div>
                        <ButtonPrimary type="submit" disabled={appBusy}>
                          {appBusy ? 'Creating…' : 'Create OAuth app'}
                        </ButtonPrimary>
                      </form>

                      {apps.loading ? (
                        <div className="p-6 text-center text-sm text-slate-500">Loading…</div>
                      ) : (apps.data?.rows?.length ?? 0) === 0 ? (
                        <ListEmptyState
                          title="No OAuth apps yet"
                          description="Create an OAuth app if you want external products to connect using user consent."
                        />
                      ) : (
                        <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                          {(apps.data?.rows ?? []).map((r) => (
                            <li key={r.id} className="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                              <div className="min-w-0">
                                <div className="text-sm font-medium text-slate-900 dark:text-white">{r.name}</div>
                                <div className="mt-1 font-mono text-xs text-slate-500">{r.client_id}</div>
                                <div className="mt-1 text-xs text-slate-500">{r.revoked ? 'revoked' : 'active'}</div>
                              </div>
                              {!r.revoked ? (
                                <button
                                  type="button"
                                  className="shrink-0 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40"
                                  onClick={() => void revokeApp(r)}
                                >
                                  Revoke
                                </button>
                              ) : null}
                            </li>
                          ))}
                        </ul>
                      )}
                    </ListPanel>
                  )}
                </div>
              </>
            )}
        </section>
      </div>
    </EmbeddableShell>
  );
}
