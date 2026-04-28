import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ListPanel } from '../components/shared/list/ListPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { WPMediaPickerField } from '../components/shared/WPMediaPickerField';
import type { SikshyaReactConfig } from '../types';

type Options = {
  brand_name?: string;
  brand_short_name?: string;
  plugin_name?: string;
  logo_url?: string;
  admin_menu_icon_url?: string;
  topbar_bg?: string;
  topbar_text?: string;
  sidebar_bg?: string;
  sidebar_text?: string;
  frontend_accent?: string;
  hide_sikshya_footer?: boolean;
  admin_footer_html?: string;
  login_accent_color?: string;
  login_logo_url?: string;
  admin_enabled?: boolean;
  login_enabled?: boolean;
  frontend_enabled?: boolean;
  email_enabled?: boolean;
  documentation_url?: string;
  support_url?: string;
  upgrade_url?: string;
  terminology?: Partial<
    Record<
      | 'course'
      | 'courses'
      | 'lesson'
      | 'lessons'
      | 'quiz'
      | 'quizzes'
      | 'assignment'
      | 'assignments'
      | 'chapter'
      | 'chapters'
      | 'student'
      | 'students'
      | 'instructor'
      | 'instructors'
      | 'enrollment'
      | 'enrollments',
      string
    >
  >;
};

type Resp = { ok?: boolean; options?: Options };

export function WhiteLabelPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const featureOk = isFeatureEnabled(config, 'white_label');
  const addon = useAddonEnabled('white_label');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);
  const enabled = mode === 'full';
  const platformName = config.branding?.pluginName?.trim() || 'Sikshya';

  const [opts, setOpts] = useState<Options>({});
  const [courseId, setCourseId] = useState<number>(0);
  const [courseOverrides, setCourseOverrides] = useState<Record<string, unknown>>({});
  const [courseLoading, setCourseLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const toast = useTopRightToast();

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
    setSaving(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.whiteLabel, opts);
      toast.success('Saved', embedded ? 'Changes saved.' : 'Saved. Applying…');
      refetch();
      // The shell branding is injected server-side into the bootstrap config.
      // Reload so all pages pick up updated colors/menu branding immediately.
      if (!embedded) {
        window.setTimeout(() => window.location.reload(), 350);
      }
    } catch (err) {
      toast.error('Save failed', err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const loadCourseOverrides = useCallback(
    async (id: number) => {
      if (!enabled || !id) return;
      setCourseLoading(true);
      try {
        const r = await getSikshyaApi().get<{ ok?: boolean; overrides?: Record<string, unknown> }>(
          SIKSHYA_ENDPOINTS.pro.whiteLabelCourse(id),
        );
        setCourseOverrides(r.overrides || {});
      } catch (err) {
        toast.error('Could not load', err instanceof Error ? err.message : 'Could not load course overrides');
      } finally {
        setCourseLoading(false);
      }
    },
    [enabled],
  );

  const saveCourseOverrides = async () => {
    if (!enabled || !courseId) return;
    setSaving(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.pro.whiteLabelCourse(courseId), courseOverrides);
      toast.success('Saved', 'Course overrides saved.');
    } catch (err) {
      toast.error('Save failed', err instanceof Error ? err.message : 'Course override save failed');
    } finally {
      setSaving(false);
    }
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={`Match ${platformName} to your brand on login and admin screens.`}
    >
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />
      <GatedFeatureWorkspace
        mode={mode}
        featureId="white_label"
        config={config}
        featureTitle="White label"
        featureDescription={`Hide the ${platformName} footer credit, replace it with your own HTML, and tint the login form to match your brand.`}
        previewVariant="form"
        addonEnableTitle="White label is not enabled"
        addonEnableDescription="Enable the White label add-on to apply branding overrides to admin and login screens."
        canEnable={Boolean(addon.licenseOk)}
        enableBusy={addon.loading}
        onEnable={() => addon.enable()}
        addonError={addon.error}
      >
        {error ? <ApiErrorPanel error={error} title="Could not load branding settings" onRetry={() => refetch()} /> : null}

        <ListPanel className="p-6">
          {loading ? (
            <p className="text-sm text-slate-500">Loading…</p>
          ) : (
            <form onSubmit={onSave} className="space-y-5">
              <div className="grid gap-6 lg:grid-cols-2">
                <label className="block text-sm">
                  <span className="font-medium text-slate-900 dark:text-white">Brand name</span>
                  <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                    Used across admin, frontend, and emails when Sikshya would normally show its product name.
                  </span>
                  <input
                    type="text"
                    value={opts.brand_name || ''}
                    onChange={(e) => setOpts((p) => ({ ...p, brand_name: e.target.value }))}
                    className="mt-2 w-full max-w-xl rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                    placeholder="Acme Academy LMS"
                  />
                </label>

                <label className="block text-sm">
                  <span className="font-medium text-slate-900 dark:text-white">Short name</span>
                  <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                    Optional shorter label for breadcrumbs and compact UI areas.
                  </span>
                  <input
                    type="text"
                    value={opts.brand_short_name || ''}
                    onChange={(e) => setOpts((p) => ({ ...p, brand_short_name: e.target.value }))}
                    className="mt-2 w-full max-w-xl rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                    placeholder="Acme"
                  />
                </label>
              </div>

              <div className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-800 dark:bg-slate-900/30">
                <div className="font-semibold text-slate-900 dark:text-white">Where should branding apply?</div>
                <label className="flex items-center justify-between gap-3">
                  <span className="text-slate-700 dark:text-slate-200">Admin (wp-admin + React shell)</span>
                  <input
                    type="checkbox"
                    checked={opts.admin_enabled ?? true}
                    onChange={(e) => setOpts((p) => ({ ...p, admin_enabled: e.target.checked }))}
                  />
                </label>
                <label className="flex items-center justify-between gap-3">
                  <span className="text-slate-700 dark:text-slate-200">Login screen</span>
                  <input
                    type="checkbox"
                    checked={opts.login_enabled ?? true}
                    onChange={(e) => setOpts((p) => ({ ...p, login_enabled: e.target.checked }))}
                  />
                </label>
                <label className="flex items-center justify-between gap-3">
                  <span className="text-slate-700 dark:text-slate-200">Frontend pages</span>
                  <input
                    type="checkbox"
                    checked={opts.frontend_enabled ?? true}
                    onChange={(e) => setOpts((p) => ({ ...p, frontend_enabled: e.target.checked }))}
                  />
                </label>
                <label className="flex items-center justify-between gap-3">
                  <span className="text-slate-700 dark:text-slate-200">Emails</span>
                  <input
                    type="checkbox"
                    checked={opts.email_enabled ?? true}
                    onChange={(e) => setOpts((p) => ({ ...p, email_enabled: e.target.checked }))}
                  />
                </label>
              </div>

              <label className="flex items-start gap-3 text-sm">
                <input
                  type="checkbox"
                  checked={Boolean(opts.hide_sikshya_footer)}
                  onChange={(e) => setOpts((p) => ({ ...p, hide_sikshya_footer: e.target.checked }))}
                  className="mt-1"
                />
                <span>
                  <span className="font-medium text-slate-900 dark:text-white">Hide platform footer credit</span>
                  <span className="block text-xs text-slate-500 dark:text-slate-400">
                    Removes the “Powered by …” line that normally appears at the bottom of admin pages.
                  </span>
                </span>
              </label>

              <label className="block text-sm">
                <span className="font-medium text-slate-900 dark:text-white">Plugin name (wp-admin menu + sidebar)</span>
                <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                  Replaces the default product name with your own name in the WordPress admin menu and the admin sidebar.
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

              <div className="grid gap-6 lg:grid-cols-2">
                <div>
                  <div className="text-sm">
                    <div className="font-medium text-slate-900 dark:text-white">Login logo (optional)</div>
                    <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                      Replaces the WordPress login logo when login branding is enabled.
                    </div>
                  </div>
                  <WPMediaPickerField
                    id="sik-white-label-login-logo"
                    value={opts.login_logo_url || ''}
                    onChange={(url) => setOpts((p) => ({ ...p, login_logo_url: url }))}
                  />
                </div>

                <label className="block text-sm">
                  <span className="font-medium text-slate-900 dark:text-white">Frontend accent colour</span>
                  <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                    Updates public Sikshya pages (course listing, course page, cart, checkout, learn, account).
                  </span>
                  <div className="mt-2 flex items-center gap-3">
                    <input
                      type="color"
                      value={opts.frontend_accent || '#6366f1'}
                      onChange={(e) => setOpts((p) => ({ ...p, frontend_accent: e.target.value }))}
                      className="h-10 w-16 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                    />
                    <input
                      type="text"
                      value={opts.frontend_accent || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, frontend_accent: e.target.value }))}
                      className="w-40 rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                      placeholder="#6366f1"
                    />
                  </div>
                </label>
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
                  <span className="font-medium text-slate-900 dark:text-white">Admin accent colour</span>
                  <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                    Drives link, primary button, and focus colours in the React admin (the content header stays
                    neutral). Sidebar colour is used first when it is a strong tone; otherwise this value fills in.
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
                  <span className="font-medium text-slate-900 dark:text-white">Legacy accent text colour</span>
                  <span className="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                    Kept for saved profiles; not applied to the React top header (default title colours).
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

              <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <div className="text-sm font-semibold text-slate-900 dark:text-white">Terminology</div>
                <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Rename common LMS nouns across Sikshya UI. Leave blank to keep defaults.
                </div>
                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                  {(
                    [
                      'course',
                      'courses',
                      'lesson',
                      'lessons',
                      'quiz',
                      'quizzes',
                      'assignment',
                      'assignments',
                      'chapter',
                      'chapters',
                      'student',
                      'students',
                      'instructor',
                      'instructors',
                      'enrollment',
                      'enrollments',
                    ] as const
                  ).map((k) => (
                    <label key={k} className="block text-sm">
                      <span className="font-medium capitalize text-slate-900 dark:text-white">{k}</span>
                      <input
                        type="text"
                        value={opts.terminology?.[k] || ''}
                        onChange={(e) =>
                          setOpts((p) => ({
                            ...p,
                            terminology: { ...(p.terminology || {}), [k]: e.target.value },
                          }))
                        }
                        className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        placeholder=""
                      />
                    </label>
                  ))}
                </div>
              </div>

              <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <div className="text-sm font-semibold text-slate-900 dark:text-white">Links</div>
                <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Used in admin footer, upsell prompts, and help surfaces.
                </div>
                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                  <label className="block text-sm">
                    <span className="font-medium text-slate-900 dark:text-white">Documentation URL</span>
                    <input
                      type="url"
                      value={opts.documentation_url || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, documentation_url: e.target.value }))}
                      className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                      placeholder="https://docs.yoursite.com"
                    />
                  </label>
                  <label className="block text-sm">
                    <span className="font-medium text-slate-900 dark:text-white">Support URL</span>
                    <input
                      type="url"
                      value={opts.support_url || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, support_url: e.target.value }))}
                      className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                      placeholder="https://support.yoursite.com"
                    />
                  </label>
                  <label className="block text-sm lg:col-span-2">
                    <span className="font-medium text-slate-900 dark:text-white">Upgrade / pricing URL</span>
                    <input
                      type="url"
                      value={opts.upgrade_url || ''}
                      onChange={(e) => setOpts((p) => ({ ...p, upgrade_url: e.target.value }))}
                      className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                      placeholder="https://yoursite.com/pricing"
                    />
                  </label>
                </div>
              </div>

              <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <div className="text-sm font-semibold text-slate-900 dark:text-white">Per-course overrides (optional)</div>
                <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Override brand name/logo/accent for a single course page and learn experience. Admins only.
                </div>

                <div className="mt-4 flex flex-wrap items-end gap-3">
                  <label className="block text-sm">
                    <span className="font-medium text-slate-900 dark:text-white">Course ID</span>
                    <input
                      type="number"
                      min={0}
                      value={courseId ? String(courseId) : ''}
                      onChange={(e) => setCourseId(Number(e.target.value || 0))}
                      className="mt-2 w-40 rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                      placeholder="123"
                    />
                  </label>
                  <button
                    type="button"
                    onClick={() => void loadCourseOverrides(courseId)}
                    disabled={!courseId || courseLoading}
                    className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                  >
                    {courseLoading ? 'Loading…' : 'Load'}
                  </button>
                </div>

                {courseId ? (
                  <div className="mt-4 grid gap-4 lg:grid-cols-2">
                    <label className="block text-sm">
                      <span className="font-medium text-slate-900 dark:text-white">Course brand name</span>
                      <input
                        type="text"
                        value={(courseOverrides.brand_name as string) || ''}
                        onChange={(e) => setCourseOverrides((p) => ({ ...p, brand_name: e.target.value }))}
                        className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                      />
                    </label>
                    <label className="block text-sm">
                      <span className="font-medium text-slate-900 dark:text-white">Course accent</span>
                      <div className="mt-2 flex items-center gap-3">
                        <input
                          type="color"
                          value={(courseOverrides.frontend_accent as string) || '#6366f1'}
                          onChange={(e) => setCourseOverrides((p) => ({ ...p, frontend_accent: e.target.value }))}
                          className="h-10 w-16 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                        />
                        <input
                          type="text"
                          value={(courseOverrides.frontend_accent as string) || ''}
                          onChange={(e) => setCourseOverrides((p) => ({ ...p, frontend_accent: e.target.value }))}
                          className="w-40 rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"
                        />
                      </div>
                    </label>
                    <div className="lg:col-span-2">
                      <div className="text-sm">
                        <div className="font-medium text-slate-900 dark:text-white">Course logo (optional)</div>
                        <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">Overrides the admin/sidebar logo for this course context.</div>
                      </div>
                      <WPMediaPickerField
                        id="sik-white-label-course-logo"
                        value={(courseOverrides.logo_url as string) || ''}
                        onChange={(url) => setCourseOverrides((p) => ({ ...p, logo_url: url }))}
                      />
                    </div>
                    <div className="lg:col-span-2 flex items-center gap-3">
                      <button
                        type="button"
                        onClick={() => void saveCourseOverrides()}
                        disabled={saving}
                        className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 dark:bg-white dark:text-slate-900"
                      >
                        {saving ? 'Saving…' : 'Save course overrides'}
                      </button>
                    </div>
                  </div>
                ) : null}
              </div>

              <div className="flex items-center gap-3">
                <ButtonPrimary type="submit" disabled={saving}>
                  {saving ? 'Saving…' : 'Save branding'}
                </ButtonPrimary>
              </div>
            </form>
          )}
        </ListPanel>
      </GatedFeatureWorkspace>
    </EmbeddableShell>
  );
}
