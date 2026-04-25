import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import type { SikshyaReactConfig } from '../types';

type Options = {
  google_client_id?: string;
  google_client_secret?: string;
  facebook_app_id?: string;
  show_login_notice?: boolean;
};

type Resp = { ok?: boolean; options?: Options };

export function SocialLoginPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const featureOk = isFeatureEnabled(config, 'social_login');
  const addon = useAddonEnabled('social_login');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [opts, setOpts] = useState<Options>({});
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);

  const loader = useCallback(async () => {
    if (!enabled) return { ok: true, options: {} as Options };
    return getSikshyaApi().get<Resp>(SIKSHYA_ENDPOINTS.pro.socialLogin);
  }, [enabled]);
  const { loading, data, error, refetch } = useAsyncData(loader, [enabled]);

  useEffect(() => {
    if (data?.options) {
      setOpts({ ...data.options });
    }
  }, [data]);

  const onSave = async (e: FormEvent) => {
    e.preventDefault();
    setMsg(null);
    setSaving(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.socialLogin, opts);
      setMsg('Saved.');
      refetch();
    } catch (err) {
      setMsg(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Let learners sign up using Google or Facebook in addition to email."
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="social_login"
        config={config}
        featureTitle="Social login"
        featureDescription="Add one-click Google / Facebook sign-in. Sikshya stores your provider keys and shows a notice on the login form."
        previewVariant="form"
        addonEnableTitle="Social login is not enabled"
        addonEnableDescription="Enable the Social login add-on to register login bridge endpoints and surface configuration."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
        {error ? <ApiErrorPanel error={error} title="Could not load social login config" onRetry={() => refetch()} /> : null}

        <ListPanel className="p-6">
          {loading ? (
            <p className="text-sm text-slate-500">Loading…</p>
          ) : (
            <form onSubmit={onSave} className="space-y-5">
              <div>
                <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Google</h2>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Create OAuth credentials in the Google Cloud Console and paste them here. Use the WordPress login URL as
                  the authorized redirect URI.
                </p>
                <div className="mt-3 grid gap-4 sm:grid-cols-2">
                  <label className="block text-sm">
                    <span className="text-slate-600 dark:text-slate-400">Client ID</span>
                    <input
                      value={opts.google_client_id || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, google_client_id: e.target.value }))}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                      placeholder="xxxxxxxxxxxx.apps.googleusercontent.com"
                    />
                  </label>
                  <label className="block text-sm">
                    <span className="text-slate-600 dark:text-slate-400">Client secret</span>
                    <input
                      type="password"
                      autoComplete="off"
                      value={opts.google_client_secret || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, google_client_secret: e.target.value }))}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                    />
                  </label>
                </div>
              </div>

              <div>
                <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Facebook</h2>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Add the Facebook App ID created in Meta for Developers.
                </p>
                <div className="mt-3 grid gap-4 sm:grid-cols-2">
                  <label className="block text-sm">
                    <span className="text-slate-600 dark:text-slate-400">App ID</span>
                    <input
                      value={opts.facebook_app_id || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, facebook_app_id: e.target.value }))}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                    />
                  </label>
                </div>
              </div>

              <label className="flex items-start gap-3 text-sm">
                <input
                  type="checkbox"
                  checked={Boolean(opts.show_login_notice)}
                  onChange={(e) => setOpts((p) => ({ ...p, show_login_notice: e.target.checked }))}
                  className="mt-1"
                />
                <span>
                  <span className="font-medium text-slate-900 dark:text-white">Show notice on the login screen</span>
                  <span className="block text-xs text-slate-500 dark:text-slate-400">
                    Displays a small message reminding visitors that social login is available.
                  </span>
                </span>
              </label>

              <div className="flex items-center gap-3">
                <ButtonPrimary type="submit" disabled={saving}>
                  {saving ? 'Saving…' : 'Save settings'}
                </ButtonPrimary>
                {msg ? <span className="text-sm text-slate-600 dark:text-slate-400">{msg}</span> : null}
              </div>
            </form>
          )}
        </ListPanel>
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}
