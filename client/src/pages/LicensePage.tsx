import { useCallback, useEffect, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { NavIcon } from '../components/NavIcon';
import { ApiError, getErrorSummary, getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { ButtonPrimary } from '../components/shared/buttons';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { useShellState } from '../context/ShellStateContext';
import type { NavItem, SikshyaReactConfig } from '../types';

type LicenseInfo = {
  key: string;
  status: string;
  last_checked: number;
  server_response: Record<string, unknown>;
};

type LicenseBootstrap = {
  is_license_active: boolean;
  pro_plugin_active: boolean;
  upgrade_url: string;
  site_tier: string;
  site_tier_label?: string;
  /** Paid plan line from server tier + EDD price_id (when license active). */
  commercial_plan_summary?: string;
  license_info: LicenseInfo | null;
};

type LicenseMutationResponse = LicenseBootstrap & {
  status?: string;
  notice?: string;
  server_response?: unknown;
  license_info?: LicenseInfo | null;
  edd_api_request?: unknown;
  edd_api_response?: unknown;
};

type DebugPayload = {
  type: string;
  at: string;
  response?: unknown;
  error?: unknown;
};

type LicenseToastState = {
  open: boolean;
  kind: 'success' | 'error';
  title: string;
  message: string;
};

function noticeFromBody(body: unknown): string | undefined {
  if (!body || typeof body !== 'object') return undefined;
  const n = (body as { notice?: unknown }).notice;
  return typeof n === 'string' && n.length ? n : undefined;
}

function maskKey(key: string): string {
  if (!key || key.length < 8) return key;
  return `${key.slice(0, 4)}••••••••••••${key.slice(-4)}`;
}

function statusBadge(status: string): { label: string; className: string } {
  const s = status.toLowerCase();
  if (s === 'active' || s === 'valid') {
    return {
      label: 'Active',
      className:
        'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900/40',
    };
  }
  if (s === 'expired') {
    return {
      label: 'Expired',
      className:
        'bg-rose-50 text-rose-800 ring-1 ring-rose-200 dark:bg-rose-950/40 dark:text-rose-200 dark:ring-rose-900/40',
    };
  }
  if (s === 'disabled' || s === 'invalid') {
    return {
      label: s === 'invalid' ? 'Invalid' : 'Disabled',
      className:
        'bg-amber-50 text-amber-900 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-100 dark:ring-amber-900/40',
    };
  }
  return {
    label: 'Inactive',
    className:
      'bg-slate-100 text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700',
  };
}

function readStr(obj: Record<string, unknown>, key: string): string | undefined {
  const v = obj[key];
  return typeof v === 'string' ? v : undefined;
}

function readNum(obj: Record<string, unknown>, key: string): number | undefined {
  const v = obj[key];
  return typeof v === 'number' && Number.isFinite(v) ? v : undefined;
}

function LicenseToast({
  toast,
  onDismiss,
}: {
  toast: LicenseToastState | null;
  onDismiss: () => void;
}) {
  if (!toast?.open) return null;

  return (
    <div className="fixed right-6 top-6 z-[9999] w-[360px] max-w-[calc(100vw-48px)]">
      <div
        className={`rounded-2xl border px-4 py-3 shadow-lg backdrop-blur dark:backdrop-blur ${
          toast.kind === 'success'
            ? 'border-emerald-200 bg-emerald-50/95 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/60 dark:text-emerald-100'
            : 'border-rose-200 bg-rose-50/95 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/60 dark:text-rose-100'
        }`}
        role="status"
        aria-live="polite"
      >
        <div className="flex items-start gap-3">
          <span
            className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${
              toast.kind === 'success'
                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200'
                : 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200'
            }`}
          >
            <NavIcon name={toast.kind === 'success' ? 'badge' : 'helpCircle'} className="h-5 w-5" />
          </span>
          <div className="min-w-0 flex-1">
            <div className="text-sm font-semibold">{toast.title}</div>
            <div className="mt-0.5 text-xs leading-snug opacity-90">{toast.message}</div>
          </div>
          <button
            type="button"
            onClick={onDismiss}
            className="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold opacity-70 hover:opacity-100"
            aria-label="Dismiss"
          >
            ✕
          </button>
        </div>
      </div>
    </div>
  );
}

export function LicensePage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const { confirm } = useSikshyaDialog();
  const { refreshShell } = useShellState();
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<unknown>(null);
  const [data, setData] = useState<LicenseBootstrap | null>(null);
  const [licenseKey, setLicenseKey] = useState('');
  const [showKey, setShowKey] = useState(false);
  const [busy, setBusy] = useState<'activate' | 'save' | 'check' | 'deactivate' | null>(null);
  const [toast, setToast] = useState<LicenseToastState | null>(null);
  const [debugOpen, setDebugOpen] = useState(false);
  const [debugData, setDebugData] = useState<DebugPayload | null>(null);
  const upgradeUrl = config.licensing?.upgradeUrl || 'https://store.mantrabrain.com/downloads/sikshya-pro/';

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const res = await getSikshyaApi().get<LicenseBootstrap>(SIKSHYA_ENDPOINTS.admin.license);
      setData(res);
      if (res.license_info?.key) {
        setLicenseKey(res.license_info.key);
      }
    } catch (e) {
      setLoadError(e);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (!toast?.open) return;
    const t = window.setTimeout(() => setToast(null), 4000);
    return () => window.clearTimeout(t);
  }, [toast]);

  const pushDebug = (type: string, response: unknown, error?: unknown) => {
    setDebugData({ type, at: new Date().toISOString(), response, error });
  };

  const showToast = (kind: 'success' | 'error', text: string) => {
    setToast({
      open: true,
      kind,
      title: kind === 'success' ? 'Success' : 'Error',
      message: text,
    });
  };

  const proInstalled = Boolean(data?.pro_plugin_active);

  const onActivate = async (e: React.FormEvent) => {
    e.preventDefault();
    const key = licenseKey.trim();
    if (!key) {
      showToast('error', 'Please enter a license key.');
      return;
    }
    setBusy('activate');
    try {
      const res = await getSikshyaApi().post<LicenseMutationResponse>(SIKSHYA_ENDPOINTS.admin.licenseActivate, {
        license_key: key,
      });
      pushDebug('activate', res);
      if (res.status === 'valid' || res.status === 'active') {
        setData((prev) => ({ ...(prev || ({} as LicenseBootstrap)), ...res }));
        if (res.license_info?.key) setLicenseKey(res.license_info.key);
        await load();
        await refreshShell();
        showToast('success', res.notice || 'License activated.');
      } else {
        showToast('error', res.notice || 'Activation did not complete.');
      }
    } catch (err) {
      const body = err instanceof ApiError ? err.body : null;
      pushDebug('activate', body, err);
      showToast('error', noticeFromBody(body) || getErrorSummary(err));
    } finally {
      setBusy(null);
    }
  };

  const onSave = async () => {
    const key = licenseKey.trim();
    if (!key) {
      showToast('error', 'Please enter a license key.');
      return;
    }
    setBusy('save');
    try {
      const res = await getSikshyaApi().post<LicenseMutationResponse>(SIKSHYA_ENDPOINTS.admin.licenseSave, {
        license_key: key,
      });
      pushDebug('save', res);
      await load();
      await refreshShell();
      showToast('success', res.notice || 'License key saved.');
    } catch (err) {
      const body = err instanceof ApiError ? err.body : null;
      pushDebug('save', body, err);
      showToast('error', noticeFromBody(body) || getErrorSummary(err));
    } finally {
      setBusy(null);
    }
  };

  const onCheck = async () => {
    setBusy('check');
    try {
      const res = await getSikshyaApi().post<LicenseMutationResponse>(SIKSHYA_ENDPOINTS.admin.licenseCheck, {});
      pushDebug('check', res);
      await load();
      await refreshShell();
      showToast('success', res.notice || 'Status refreshed.');
    } catch (err) {
      const body = err instanceof ApiError ? err.body : null;
      pushDebug('check', body, err);
      showToast('error', noticeFromBody(body) || getErrorSummary(err));
    } finally {
      setBusy(null);
    }
  };

  const onDeactivate = async () => {
    const ok = await confirm({
      title: 'Deactivate license',
      message:
        'This frees an activation on your account and turns off Pro features on this site until you activate again. Continue?',
      confirmLabel: 'Deactivate',
      variant: 'danger',
    });
    if (!ok) return;
    setBusy('deactivate');
    try {
      const res = await getSikshyaApi().post<LicenseMutationResponse>(SIKSHYA_ENDPOINTS.admin.licenseDeactivate, {});
      pushDebug('deactivate', res);
      if (res.status === 'deactivated') {
        setLicenseKey('');
        await load();
        await refreshShell();
        showToast('success', res.notice || 'License deactivated.');
      } else {
        showToast('error', res.notice || 'Deactivation did not complete.');
      }
    } catch (err) {
      const body = err instanceof ApiError ? err.body : null;
      pushDebug('deactivate', body, err);
      showToast('error', noticeFromBody(body) || getErrorSummary(err));
    } finally {
      setBusy(null);
    }
  };

  const shell = (inner: React.ReactNode) => (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      branding={config.branding}
      title={title}
      subtitle="Sikshya Pro licensing and updates"
    >
      <LicenseToast toast={toast} onDismiss={() => setToast(null)} />
      {inner}
    </AppShell>
  );

  if (loading && !data) {
    return shell(
      <div className="mx-auto max-w-4xl px-4 py-6">
        <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <div className="h-14 animate-pulse border-b border-slate-100 bg-slate-100 dark:border-slate-800 dark:bg-slate-800" />
          <div className="space-y-4 p-6">
            <div className="h-28 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />
            <div className="h-40 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />
          </div>
        </div>
      </div>
    );
  }

  if (!loading && loadError && !data) {
    return shell(
      <div className="mx-auto max-w-4xl px-4 py-6">
        <ApiErrorPanel error={loadError} onRetry={() => void load()} />
      </div>
    );
  }

  if (data && !proInstalled) {
    return shell(
      <div className="mx-auto max-w-4xl px-4 py-6">
        <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <div className="flex items-center gap-3 border-b border-slate-200/80 px-6 py-4 dark:border-slate-800">
            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-950/50 dark:text-brand-300">
              <NavIcon name="licenseKey" className="h-5 w-5" />
            </span>
            <h1 className="text-lg font-semibold text-slate-900 dark:text-white">License</h1>
          </div>
          <div className="p-6 text-center">
            <p className="text-sm text-slate-600 dark:text-slate-400">
              You are running <span className="font-semibold text-slate-900 dark:text-white">Sikshya</span> without the
              Pro add-on. Install and activate <span className="font-semibold">Sikshya Pro</span> to enter a license key
              and unlock premium features.
            </p>
            <div className="mx-auto mt-8 max-w-xl rounded-2xl border border-brand-100 bg-brand-50/60 p-6 text-left dark:border-brand-900/40 dark:bg-brand-950/30">
              <h2 className="text-base font-semibold text-slate-900 dark:text-white">Upgrade to Sikshya Pro</h2>
              <ul className="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                <li className="flex gap-2">
                  <span className="text-emerald-600 dark:text-emerald-400">✓</span>
                  Content drip, prerequisites, and gradebook
                </li>
                <li className="flex gap-2">
                  <span className="text-emerald-600 dark:text-emerald-400">✓</span>
                  Subscriptions, multi-instructor revenue, and advanced certificates
                </li>
                <li className="flex gap-2">
                  <span className="text-emerald-600 dark:text-emerald-400">✓</span>
                  Priority updates and commercial support channels
                </li>
              </ul>
              <div className="mt-6">
                <ButtonPrimary
                  className="w-full sm:w-auto"
                  onClick={() => {
                    window.open(upgradeUrl, '_blank', 'noopener,noreferrer');
                  }}
                >
                  View plans
                </ButtonPrimary>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (!data) {
    return shell(
      <div className="mx-auto max-w-4xl px-4 py-6 text-sm text-slate-600 dark:text-slate-400">
        Unable to load license state.
      </div>
    );
  }

  const lic = data.license_info;
  const keyActive = lic?.status === 'active' || lic?.status === 'valid';
  const planLine =
    (data.commercial_plan_summary && data.commercial_plan_summary.trim()) ||
    (data.site_tier && data.site_tier !== 'free' ? String(data.site_tier_label || data.site_tier).trim() : '');

  const sr = (lic?.server_response || {}) as Record<string, unknown>;
  const customerName = readStr(sr, 'customer_name');
  const customerEmail = readStr(sr, 'customer_email');
  const expires = readStr(sr, 'expires');
  const siteCount = readNum(sr, 'site_count');
  const licenseLimit = readNum(sr, 'license_limit');

  return shell(
    <div className="mx-auto max-w-4xl px-4 py-6">
      <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-6 py-4 dark:border-slate-800">
          <div className="flex items-center gap-3">
            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-950/50 dark:text-brand-300">
              <NavIcon name="licenseKey" className="h-5 w-5" />
            </span>
            <div>
              <h1 className="text-lg font-semibold text-slate-900 dark:text-white">License management</h1>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                Activate your Sikshya Pro license for updates and premium modules.
              </p>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {lic?.status ? (
              <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusBadge(lic.status).className}`}>
                {statusBadge(lic.status).label}
              </span>
            ) : null}
            <button
              type="button"
              onClick={() => setDebugOpen((v) => !v)}
              className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                debugOpen
                  ? 'bg-purple-100 text-purple-800 dark:bg-purple-950/50 dark:text-purple-200'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
              }`}
            >
              Debug
            </button>
          </div>
        </div>

        <div className="space-y-6 p-6">
          {keyActive && planLine ? (
            <div className="rounded-2xl border border-emerald-200/80 bg-emerald-50/60 px-4 py-3 dark:border-emerald-900/40 dark:bg-emerald-950/35">
              <div className="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">
                Your plan
              </div>
              <p className="mt-1 text-sm font-semibold text-emerald-950 dark:text-emerald-50">{planLine}</p>
            </div>
          ) : null}
          {!keyActive ? (
            <div className="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-6 dark:border-slate-700 dark:bg-slate-950/40">
              <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Activate your license</h2>
              <form className="mt-4 space-y-4" onSubmit={onActivate}>
                <div>
                  <label htmlFor="sikshya-license-key" className={Oe}>
                    License key
                  </label>
                  <input
                    id="sikshya-license-key"
                    className={Inp}
                    value={licenseKey}
                    onChange={(ev) => setLicenseKey(ev.target.value)}
                    placeholder="Paste your license key"
                    disabled={busy === 'activate'}
                    autoComplete="off"
                  />
                </div>
                <div className="flex flex-wrap gap-3">
                  <button
                    type="button"
                    onClick={() => void onSave()}
                    disabled={busy !== null}
                    className="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50 dark:bg-slate-700 dark:hover:bg-slate-600"
                  >
                    {busy === 'save' ? <SpinnerLight /> : null}
                    Save key
                  </button>
                  <ButtonPrimary type="submit" disabled={busy !== null}>
                    {busy === 'activate' ? <Spinner /> : null}
                    Save &amp; activate
                  </ButtonPrimary>
                </div>
              </form>
              <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                Purchase or retrieve your key from your Sikshya account, then paste it here. This site must be allowed on
                your license.
              </p>
            </div>
          ) : null}

          {keyActive && lic ? (
            <div className="space-y-6">
              <div className="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-6 dark:border-slate-700 dark:bg-slate-950/40">
                <div className="flex items-center justify-between gap-3">
                  <h3 className="text-sm font-semibold text-slate-900 dark:text-white">License key</h3>
                  <button
                    type="button"
                    className="text-xs font-semibold text-brand-700 hover:underline dark:text-brand-300"
                    onClick={() => setShowKey((v) => !v)}
                  >
                    {showKey ? 'Hide' : 'Show'}
                  </button>
                </div>
                <div className="mt-2 rounded-xl border border-slate-200 bg-white px-4 py-3 font-mono text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                  {showKey ? lic.key : maskKey(lic.key)}
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {customerName ? (
                  <div className="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                    <div className="text-xs font-medium text-slate-500 dark:text-slate-400">Licensed to</div>
                    <div className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{customerName}</div>
                  </div>
                ) : null}
                {customerEmail ? (
                  <div className="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                    <div className="text-xs font-medium text-slate-500 dark:text-slate-400">Email</div>
                    <div className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{customerEmail}</div>
                  </div>
                ) : null}
                {expires && expires !== 'lifetime' ? (
                  <div className="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                    <div className="text-xs font-medium text-slate-500 dark:text-slate-400">Expires</div>
                    <div className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                      {Number.isNaN(Date.parse(expires)) ? expires : new Date(expires).toLocaleDateString()}
                    </div>
                  </div>
                ) : null}
                {expires === 'lifetime' ? (
                  <div className="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                    <div className="text-xs font-medium text-slate-500 dark:text-slate-400">Expires</div>
                    <div className="mt-1 text-sm font-semibold text-emerald-700 dark:text-emerald-300">Lifetime</div>
                  </div>
                ) : null}
                {siteCount !== undefined ? (
                  <div className="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-950/30">
                    <div className="text-xs font-medium text-slate-500 dark:text-slate-400">Activations</div>
                    <div className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                      {siteCount}
                      {licenseLimit !== undefined ? ` / ${licenseLimit}` : ''}
                    </div>
                  </div>
                ) : null}
              </div>

              <div className="flex flex-wrap gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => void onCheck()}
                  disabled={busy !== null}
                  className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                >
                  {busy === 'check' ? <SpinnerNeutral /> : null}
                  Check status
                </button>
                <button
                  type="button"
                  onClick={() => void onDeactivate()}
                  disabled={busy !== null}
                  className="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
                >
                  {busy === 'deactivate' ? <Spinner /> : null}
                  Deactivate
                </button>
              </div>
            </div>
          ) : null}

          <div className="rounded-2xl border border-brand-100 bg-brand-50/50 p-4 text-sm text-slate-700 dark:border-brand-900/40 dark:bg-brand-950/25 dark:text-slate-200">
            <div className="font-semibold text-slate-900 dark:text-white">Need help?</div>
            <p className="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
              If activation fails, confirm this site URL matches your license and that you still have activations
              available.
            </p>
            <div className="mt-3 flex flex-wrap gap-3 text-xs font-semibold">
              <a className="text-brand-700 hover:underline dark:text-brand-300" href={upgradeUrl} target="_blank" rel="noreferrer">
                Manage subscription
              </a>
            </div>
          </div>

          {debugOpen ? (
            <div className="rounded-2xl border border-purple-200/80 bg-purple-50/50 p-4 dark:border-purple-900/40 dark:bg-purple-950/30">
              <div className="flex items-center justify-between gap-2">
                <div className="text-xs font-semibold text-purple-900 dark:text-purple-100">Debug</div>
                {debugData ? (
                  <button
                    type="button"
                    className="text-xs font-semibold text-purple-700 hover:underline dark:text-purple-300"
                    onClick={() => setDebugData(null)}
                  >
                    Clear
                  </button>
                ) : null}
              </div>
              {debugData ? (
                <pre className="mt-3 max-h-80 overflow-auto rounded-xl border border-purple-100 bg-white p-3 text-[11px] leading-relaxed text-slate-800 dark:border-purple-900/50 dark:bg-slate-950 dark:text-slate-100">
                  {JSON.stringify(debugData, null, 2)}
                </pre>
              ) : (
                <p className="mt-2 text-xs text-purple-800/90 dark:text-purple-200/90">
                  Run save, activate, check, or deactivate to capture the last response here.
                </p>
              )}
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}

const Oe = 'block text-sm font-medium text-slate-800 dark:text-slate-200';
const Inp =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white';

function Spinner() {
  return (
    <span className="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white align-[-2px]" />
  );
}

function SpinnerLight() {
  return (
    <span className="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-500/30 border-t-white align-[-2px]" />
  );
}

/** Visible on light / outline buttons (white spinner is invisible on white backgrounds). */
function SpinnerNeutral() {
  return (
    <span className="mr-2 inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-slate-300 border-t-brand-600 align-[-2px] dark:border-slate-600 dark:border-t-brand-400" />
  );
}
