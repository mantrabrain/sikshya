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
  google_client_secret_set?: boolean;
  facebook_app_id?: string;
  facebook_app_secret?: string;
  facebook_app_secret_set?: boolean;
  show_login_notice?: boolean;
  show_wp_login_buttons?: boolean;
  show_on_course_page?: boolean;
  show_on_cart?: boolean;
  show_account_linking?: boolean;
  allow_social_registration?: boolean;
  link_matching_verified_email?: boolean;
  require_google_email_verified?: boolean;
  oauth_callback_url?: string;
  users_can_register?: boolean;
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
              <div className="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-950">
                <p className="font-semibold text-slate-900 dark:text-white">Redirect / callback URL</p>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Add this exact URL as the authorized redirect URI in your provider console.
                </p>
                <div className="mt-3 flex items-center gap-3">
                  <input
                    readOnly
                    value={opts.oauth_callback_url || ''}
                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                  />
                </div>
              </div>

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
                    <span className="text-slate-600 dark:text-slate-400">
                      Client secret{' '}
                      {opts.google_client_secret_set ? (
                        <span className="ml-2 rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200">
                          set
                        </span>
                      ) : null}
                    </span>
                    <input
                      type="password"
                      autoComplete="off"
                      value={opts.google_client_secret || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, google_client_secret: e.target.value }))}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                      placeholder={opts.google_client_secret_set ? '•••••••• (leave blank to keep unchanged)' : 'Paste client secret'}
                    />
                  </label>
                </div>
              </div>

              <div>
                <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Facebook</h2>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Create an app in Meta for Developers and paste the keys below.
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
                  <label className="block text-sm">
                    <span className="text-slate-600 dark:text-slate-400">
                      App secret{' '}
                      {opts.facebook_app_secret_set ? (
                        <span className="ml-2 rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200">
                          set
                        </span>
                      ) : null}
                    </span>
                    <input
                      type="password"
                      autoComplete="off"
                      value={opts.facebook_app_secret || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, facebook_app_secret: e.target.value }))}
                      className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                      placeholder={opts.facebook_app_secret_set ? '•••••••• (leave blank to keep unchanged)' : 'Paste app secret'}
                    />
                  </label>
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                <p className="text-sm font-semibold text-slate-900 dark:text-white">Placement & behavior</p>
                <div className="mt-3 grid gap-3">
                  <label className="flex items-start gap-3 text-sm">
                    <input
                      type="checkbox"
                      checked={Boolean(opts.show_wp_login_buttons)}
                      onChange={(e) => setOpts((p) => ({ ...p, show_wp_login_buttons: e.target.checked }))}
                      className="mt-1"
                    />
                    <span>
                      <span className="font-medium text-slate-900 dark:text-white">Show buttons on the WordPress login page</span>
                      <span className="block text-xs text-slate-500 dark:text-slate-400">Adds Google/Facebook buttons above the form.</span>
                    </span>
                  </label>

                  <label className="flex items-start gap-3 text-sm">
                    <input
                      type="checkbox"
                      checked={Boolean(opts.show_login_notice)}
                      onChange={(e) => setOpts((p) => ({ ...p, show_login_notice: e.target.checked }))}
                      className="mt-1"
                    />
                    <span>
                      <span className="font-medium text-slate-900 dark:text-white">Show a short login-page notice</span>
                      <span className="block text-xs text-slate-500 dark:text-slate-400">Helpful hint when buttons are disabled or incomplete.</span>
                    </span>
                  </label>

                  <label className="flex items-start gap-3 text-sm">
                    <input
                      type="checkbox"
                      checked={Boolean(opts.show_on_course_page)}
                      onChange={(e) => setOpts((p) => ({ ...p, show_on_course_page: e.target.checked }))}
                      className="mt-1"
                    />
                    <span>
                      <span className="font-medium text-slate-900 dark:text-white">Show on single course page</span>
                      <span className="block text-xs text-slate-500 dark:text-slate-400">Shows a “Continue with Google/Facebook” box for logged-out users.</span>
                    </span>
                  </label>

                  <label className="flex items-start gap-3 text-sm">
                    <input
                      type="checkbox"
                      checked={Boolean(opts.show_on_cart)}
                      onChange={(e) => setOpts((p) => ({ ...p, show_on_cart: e.target.checked }))}
                      className="mt-1"
                    />
                    <span>
                      <span className="font-medium text-slate-900 dark:text-white">Show on cart</span>
                      <span className="block text-xs text-slate-500 dark:text-slate-400">Encourages sign-in before checkout.</span>
                    </span>
                  </label>

                  <label className="flex items-start gap-3 text-sm">
                    <input
                      type="checkbox"
                      checked={Boolean(opts.show_account_linking)}
                      onChange={(e) => setOpts((p) => ({ ...p, show_account_linking: e.target.checked }))}
                      className="mt-1"
                    />
                    <span>
                      <span className="font-medium text-slate-900 dark:text-white">Allow account linking</span>
                      <span className="block text-xs text-slate-500 dark:text-slate-400">Lets logged-in learners link Google/Facebook to their account.</span>
                    </span>
                  </label>
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                <p className="text-sm font-semibold text-slate-900 dark:text-white">Security & registration</p>
                <div className="mt-3 grid gap-3">
                  <label className="flex items-start gap-3 text-sm">
                    <input
                      type="checkbox"
                      checked={Boolean(opts.allow_social_registration)}
                      onChange={(e) => setOpts((p) => ({ ...p, allow_social_registration: e.target.checked }))}
                      className="mt-1"
                    />
                    <span>
                      <span className="font-medium text-slate-900 dark:text-white">Allow social registration</span>
                      <span className="block text-xs text-slate-500 dark:text-slate-400">
                        Creates a new WordPress user when no match exists. {opts.users_can_register ? null : <strong>WordPress registration is currently disabled.</strong>}
                      </span>
                    </span>
                  </label>

                  <label className="flex items-start gap-3 text-sm">
                    <input
                      type="checkbox"
                      checked={Boolean(opts.link_matching_verified_email)}
                      onChange={(e) => setOpts((p) => ({ ...p, link_matching_verified_email: e.target.checked }))}
                      className="mt-1"
                    />
                    <span>
                      <span className="font-medium text-slate-900 dark:text-white">Link to existing users by verified email</span>
                      <span className="block text-xs text-slate-500 dark:text-slate-400">
                        If a provider returns a verified email that matches an existing user, Sikshya logs them into that account.
                      </span>
                    </span>
                  </label>

                  <label className="flex items-start gap-3 text-sm">
                    <input
                      type="checkbox"
                      checked={Boolean(opts.require_google_email_verified)}
                      onChange={(e) => setOpts((p) => ({ ...p, require_google_email_verified: e.target.checked }))}
                      className="mt-1"
                    />
                    <span>
                      <span className="font-medium text-slate-900 dark:text-white">Require verified Google email</span>
                      <span className="block text-xs text-slate-500 dark:text-slate-400">
                        Blocks sign-in if Google marks the email as unverified.
                      </span>
                    </span>
                  </label>
                </div>
              </div>

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
