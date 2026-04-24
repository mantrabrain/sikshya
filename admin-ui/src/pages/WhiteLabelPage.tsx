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
import { WPMediaPickerField } from '../components/shared/WPMediaPickerField';
import type { SikshyaReactConfig } from '../types';

type Options = {
  plugin_name?: string;
  logo_url?: string;
  admin_menu_icon_url?: string;
  topbar_bg?: string;
  topbar_text?: string;
  sidebar_bg?: string;
  sidebar_text?: string;
  hide_sikshya_footer?: boolean;
  admin_footer_html?: string;
  login_accent_color?: string;
};

type Resp = { ok?: boolean; options?: Options };

export function WhiteLabelPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const featureOk = isFeatureEnabled(config, 'white_label');
  const addon = useAddonEnabled('white_label');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';

  const [opts, setOpts] = useState<Options>({});
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);

  const loader = useCallback(async () => {
    if (!enabled) return { ok: true, options: {} as Options };
    return getSikshyaApi().get<Resp>(SIKSHYA_ENDPOINTS.pro.whiteLabel);
  }, [enabled]);
  const { loading, data, error, refetch } = useAsyncData(loader, [enabled]);

  useEffect(() => {
    if (data?.options) setOpts({ ...data.options });
  }, [data]);

  const onSave = async (e: FormEvent) => {
    e.preventDefault();
    setMsg(null);
    setSaving(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.whiteLabel, opts);
      setMsg('Saved. Applying…');
      refetch();
      // The shell branding is injected server-side into the bootstrap config.
      // Reload so all pages pick up updated colors/menu branding immediately.
      if (!embedded) {
        window.setTimeout(() => window.location.reload(), 350);
      }
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
      subtitle="Match Sikshya to your school’s brand on login and admin screens."
    >
      <GatedFeatureWorkspace
        mode={mode}
        featureId="white_label"
        config={config}
        featureTitle="White label"
        featureDescription="Hide the Sikshya footer credit, replace it with your own HTML, and tint the login form to match your brand."
        previewVariant="form"
        addonEnableTitle="White label is not enabled"
        addonEnableDescription="Enable the White label add-on to apply branding overrides to admin and login screens."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => void addon.enable()}
        addonError={addon.error}
      >
        {error ? <ApiErrorPanel error={error} title="Could not load branding settings" onRetry={() => refetch()} /> : null}

        <ListPanel className="p-6">
          {loading ? (
            <p className="text-sm text-slate-500">Loading…</p>
          ) : (
            <form onSubmit={onSave} className="space-y-5">
              <label className="flex items-start gap-3 text-sm">
                <input
                  type="checkbox"
                  checked={Boolean(opts.hide_sikshya_footer)}
                  onChange={(e) => setOpts((p) => ({ ...p, hide_sikshya_footer: e.target.checked }))}
                  className="mt-1"
                />
                <span>
                  <span className="font-medium text-slate-900 dark:text-white">Hide Sikshya footer credit</span>
                  <span className="block text-xs text-slate-500 dark:text-slate-400">
                    Removes the “Powered by Sikshya” line that normally appears at the bottom of admin pages.
                  </span>
                </span>
              </label>

              <label className="block text-sm">
                <span className="font-medium text-slate-900 dark:text-white">Plugin name (wp-admin menu + sidebar)</span>
                <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                  Replaces “Sikshya LMS” with your own name in the WordPress admin menu and the Sikshya React sidebar.
                </span>
                <input
                  type="text"
                  value={opts.plugin_name || ''}
                  onChange={(e) => setOpts((p) => ({ ...p, plugin_name: e.target.value }))}
                  className="mt-2 w-full max-w-xl rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                  placeholder="Acme Academy LMS"
                />
              </label>

              <div className="grid gap-6 lg:grid-cols-2">
                <div>
                  <div className="text-sm">
                    <div className="font-medium text-slate-900 dark:text-white">Logo (React sidebar)</div>
                    <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Shown in the Sikshya admin sidebar header.
                    </div>
                  </div>
                  <WPMediaPickerField
                    id="sik-white-label-logo"
                    value={opts.logo_url || ''}
                    onChange={(url) => setOpts((p) => ({ ...p, logo_url: url }))}
                  />
                </div>

                <div>
                  <div className="text-sm">
                    <div className="font-medium text-slate-900 dark:text-white">Admin menu icon (WordPress sidebar)</div>
                    <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Replaces the icon next to the top-level menu item in wp-admin. Use a square image (20×20 works best).
                    </div>
                  </div>
                  <WPMediaPickerField
                    id="sik-white-label-admin-menu-icon"
                    value={opts.admin_menu_icon_url || ''}
                    onChange={(url) => setOpts((p) => ({ ...p, admin_menu_icon_url: url }))}
                  />
                </div>
              </div>

              <label className="block text-sm">
                <span className="font-medium text-slate-900 dark:text-white">Custom admin footer HTML</span>
                <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                  Replace the footer with your own HTML — useful for support links or copyright. Standard WordPress
                  sanitization applies (KSES post).
                </span>
                <textarea
                  rows={4}
                  value={opts.admin_footer_html || ''}
                  onChange={(e) => setOpts((p) => ({ ...p, admin_footer_html: e.target.value }))}
                  className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                  placeholder="<p>© Acme Academy</p>"
                />
              </label>

              <label className="block text-sm">
                <span className="font-medium text-slate-900 dark:text-white">Login accent colour</span>
                <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                  Hex value (e.g. <span className="font-mono">#0ea5e9</span>). Applied to the login submit button.
                </span>
                <div className="mt-2 flex items-center gap-3">
                  <input
                    type="color"
                    value={opts.login_accent_color || '#2563eb'}
                    onChange={(e) => setOpts((p) => ({ ...p, login_accent_color: e.target.value }))}
                    className="h-10 w-16 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                  />
                  <input
                    type="text"
                    value={opts.login_accent_color || ''}
                    onChange={(e) => setOpts((p) => ({ ...p, login_accent_color: e.target.value }))}
                    className="w-40 rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                    placeholder="#2563eb"
                  />
                </div>
              </label>

              <div className="grid gap-6 lg:grid-cols-2">
                <label className="block text-sm">
                  <span className="font-medium text-slate-900 dark:text-white">Top header background</span>
                  <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                    Applied to the Sikshya React top header.
                  </span>
                  <div className="mt-2 flex items-center gap-3">
                    <input
                      type="color"
                      value={opts.topbar_bg || '#ffffff'}
                      onChange={(e) => setOpts((p) => ({ ...p, topbar_bg: e.target.value }))}
                      className="h-10 w-16 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                    />
                    <input
                      type="text"
                      value={opts.topbar_bg || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, topbar_bg: e.target.value }))}
                      className="w-40 rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                      placeholder="#ffffff"
                    />
                  </div>
                </label>

                <label className="block text-sm">
                  <span className="font-medium text-slate-900 dark:text-white">Top header text</span>
                  <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                    Title/subtitle colour in the Sikshya React top header.
                  </span>
                  <div className="mt-2 flex items-center gap-3">
                    <input
                      type="color"
                      value={opts.topbar_text || '#0f172a'}
                      onChange={(e) => setOpts((p) => ({ ...p, topbar_text: e.target.value }))}
                      className="h-10 w-16 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                    />
                    <input
                      type="text"
                      value={opts.topbar_text || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, topbar_text: e.target.value }))}
                      className="w-40 rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                      placeholder="#0f172a"
                    />
                  </div>
                </label>

                <label className="block text-sm">
                  <span className="font-medium text-slate-900 dark:text-white">Left sidebar background</span>
                  <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                    Applied to the Sikshya React left navigation sidebar.
                  </span>
                  <div className="mt-2 flex items-center gap-3">
                    <input
                      type="color"
                      value={opts.sidebar_bg || '#ffffff'}
                      onChange={(e) => setOpts((p) => ({ ...p, sidebar_bg: e.target.value }))}
                      className="h-10 w-16 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                    />
                    <input
                      type="text"
                      value={opts.sidebar_bg || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, sidebar_bg: e.target.value }))}
                      className="w-40 rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                      placeholder="#ffffff"
                    />
                  </div>
                </label>

                <label className="block text-sm">
                  <span className="font-medium text-slate-900 dark:text-white">Left sidebar menu text</span>
                  <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                    Base colour for sidebar menu labels.
                  </span>
                  <div className="mt-2 flex items-center gap-3">
                    <input
                      type="color"
                      value={opts.sidebar_text || '#475569'}
                      onChange={(e) => setOpts((p) => ({ ...p, sidebar_text: e.target.value }))}
                      className="h-10 w-16 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                    />
                    <input
                      type="text"
                      value={opts.sidebar_text || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, sidebar_text: e.target.value }))}
                      className="w-40 rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                      placeholder="#475569"
                    />
                  </div>
                </label>
              </div>

              <div className="flex items-center gap-3">
                <ButtonPrimary type="submit" disabled={saving}>
                  {saving ? 'Saving…' : 'Save branding'}
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
