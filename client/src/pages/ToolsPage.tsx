import { useCallback, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { TopRightToast, useTopRightToast } from '../components/shared/TopRightToast';
import type { SikshyaReactConfig } from '../types';
import { __ } from '../lib/i18n';

type ToolsTab = 'status' | 'export' | 'maintenance';

function downloadJson(filename: string, data: unknown) {
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

const TAB_BTN =
  'rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors border border-transparent';
const TAB_ACTIVE =
  'bg-brand-600 text-white shadow-sm dark:bg-brand-500';
const TAB_IDLE =
  'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700';

export function ToolsPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const { confirm } = useSikshyaDialog();
  const [tab, setTab] = useState<ToolsTab>('status');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>(null);
  const toast = useTopRightToast();
  const [systemRows, setSystemRows] = useState<Record<string, string | number | boolean> | null>(null);
  const [exportKind, setExportKind] = useState<
    'settings' | 'courses' | 'certificates' | 'lessons' | 'quizzes' | 'assignments' | 'questions' | 'chapters'
  >('settings');
  const [importText, setImportText] = useState('');
  const [importOverwrite, setImportOverwrite] = useState(false);

  const runTool = useCallback(async (body: Record<string, unknown>) => {
    setBusy(true);
    setError(null);
    toast.clear();
    try {
      const res = await getSikshyaApi().post<{
        success?: boolean;
        message?: string;
        data?: unknown;
      }>(SIKSHYA_ENDPOINTS.admin.tools, body);
      if (!res?.success) {
        throw new Error(res?.message || 'Request failed');
      }
      return res;
    } catch (e) {
      setError(e);
      throw e;
    } finally {
      setBusy(false);
    }
  }, []);

  const loadSystemInfo = () => {
    void runTool({ action_type: 'system_info' })
      .then((res) => {
        const data = res.data as Record<string, string | number | boolean> | undefined;
        if (data && typeof data === 'object') {
          setSystemRows(data);
        }
        toast.success(__('Done', 'sikshya'), res.message ?? 'System information loaded.');
      })
      .catch(() => void 0);
  };

  const clearCache = () => {
    void runTool({ action_type: 'clear_cache' })
      .then((res) => toast.success(__('Done', 'sikshya'), res.message ?? 'Done.'))
      .catch(() => void 0);
  };

  const importSampleLms = () => {
    void (async () => {
      const ok = await confirm({
        title: __('Import sample courses?', 'sikshya'),
        message:
          __('This creates published sample courses, chapters, lessons, quizzes, and questions from the bundled JSON pack. Safe to run on a staging site; avoid duplicates on production if you already imported once.', 'sikshya'),
        variant: 'default',
        confirmLabel: __('Import sample data', 'sikshya'),
      });
      if (!ok) {
        return;
      }
      void runTool({ action_type: 'import_sample_data', pack: 'default' })
        .then((res) => {
          const data = res.data as { counts?: Record<string, number> } | undefined;
          const c = data?.counts;
          const bits = c
            ? Object.entries(c)
                .map(([k, v]) => `${k}: ${v}`)
                .join(', ')
            : '';
          toast.success(__('Done', 'sikshya'), bits ? `${res.message ?? 'Done.'} (${bits})` : (res.message ?? 'Done.'));
        })
        .catch(() => void 0);
    })();
  };

  const resetPluginSettings = () => {
    void (async () => {
      const ok = await confirm({
        title: __('Reset all settings?', 'sikshya'),
        message: __('Reset every Sikshya setting to its default? This cannot be undone.', 'sikshya'),
        variant: 'danger',
        confirmLabel: __('Reset everything', 'sikshya'),
      });
      if (!ok) {
        return;
      }
      void runTool({ action_type: 'reset_settings' })
        .then((res) => toast.success(__('Done', 'sikshya'), res.message ?? 'Done.'))
        .catch(() => void 0);
    })();
  };

  const exportPayload = () => {
    if (exportKind === 'settings') {
      void runTool({ action_type: 'export_settings' })
        .then((res) => {
          downloadJson(`sikshya-settings-${new Date().toISOString().slice(0, 10)}.json`, res.data ?? {});
          toast.success(__('Download started', 'sikshya'), res.message ?? 'Download started.');
        })
        .catch(() => void 0);
      return;
    }
    void runTool({ action_type: 'export_data', export_type: exportKind })
      .then((res) => {
        downloadJson(`sikshya-export-${exportKind}-${new Date().toISOString().slice(0, 10)}.json`, {
          export_type: exportKind,
          exported_at: new Date().toISOString(),
          items: res.data ?? [],
        });
        toast.success(__('Download started', 'sikshya'), res.message ?? 'Download started.');
      })
      .catch(() => void 0);
  };

  const importSettings = () => {
    let parsed: unknown;
    try {
      parsed = JSON.parse(importText.trim());
    } catch {
      setError(new Error('Invalid JSON. Paste a settings export file.'));
      return;
    }
    if (!parsed || typeof parsed !== 'object') {
      setError(new Error('Settings JSON must be an object.'));
      return;
    }
    void runTool({
      action_type: 'import_settings',
      settings: parsed,
      overwrite: importOverwrite,
    })
      .then((res) => toast.success(__('Import finished', 'sikshya'), res.message ?? 'Import finished.'))
      .catch(() => void 0);
  };

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle={__('Diagnostics, exports, and maintenance for administrators', 'sikshya')}
    >
      <TopRightToast toast={toast.toast} onDismiss={toast.clear} />
      {config.setupWizardUrl ? (
        <div className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <h2 className="text-base font-semibold text-slate-900 dark:text-white">{__('Setup wizard', 'sikshya')}</h2>
          <p className="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
            Configure storefront permalinks and learning URL style. You can return here anytime from
            Tools.
          </p>
          <div className="mt-4">
            <a
              className="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600"
              href={config.setupWizardUrl}
            >
              Open setup wizard
            </a>
          </div>
        </div>
      ) : null}

      <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-4 dark:border-slate-800">
        {(
          [
            ['status', 'System status'],
            ['export', 'Export / import'],
            ['maintenance', 'Maintenance'],
          ] as const
        ).map(([id, label]) => (
          <button
            key={id}
            type="button"
            className={`${TAB_BTN} ${tab === id ? TAB_ACTIVE : TAB_IDLE}`}
            onClick={() => setTab(id)}
          >
            {label}
          </button>
        ))}
      </div>

      {error ? (
        <div className="mt-4">
          <ApiErrorPanel error={error} title={__('Something went wrong', 'sikshya')} onRetry={() => setError(null)} />
        </div>
      ) : null}

      {tab === 'status' ? (
        <div className="mt-6 space-y-6">
          <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 className="text-base font-semibold text-slate-900 dark:text-white">{__('Environment', 'sikshya')}</h2>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Load WordPress, PHP, database, and theme summary. Nothing is changed until you run a maintenance action.
            </p>
            <div className="mt-4">
              <ButtonPrimary type="button" disabled={busy} onClick={() => loadSystemInfo()}>
                {busy ? __('Loading…', 'sikshya') : __('Load system info', 'sikshya')}
              </ButtonPrimary>
            </div>
            {systemRows ? (
              <dl className="mt-6 grid gap-3 sm:grid-cols-2">
                {Object.entries(systemRows).map(([k, v]) => (
                  <div key={k} className="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/40">
                    <dt className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      {k.replace(/_/g, ' ')}
                    </dt>
                    <dd className="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">{String(v)}</dd>
                  </div>
                ))}
              </dl>
            ) : null}
          </div>
        </div>
      ) : null}

      {tab === 'export' ? (
        <div className="mt-6 grid gap-6 lg:grid-cols-2">
          <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 className="text-base font-semibold text-slate-900 dark:text-white">{__('Export', 'sikshya')}</h2>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Download JSON for backups or staging. Content exports include titles, HTML bodies, and Sikshya meta keys.
            </p>
            <label className="mt-4 block text-sm font-medium text-slate-800 dark:text-slate-200" htmlFor="sik-exp-kind">
              Dataset
            </label>
            <select
              id="sik-exp-kind"
              className="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              value={exportKind}
              onChange={(e) => setExportKind(e.target.value as typeof exportKind)}
            >
              <option value="settings">{__('All Sikshya settings (tabs)', 'sikshya')}</option>
              <option value="courses">{__('Courses', 'sikshya')}</option>
              <option value="lessons">{__('Lessons', 'sikshya')}</option>
              <option value="quizzes">{__('Quizzes', 'sikshya')}</option>
              <option value="assignments">{__('Assignments', 'sikshya')}</option>
              <option value="questions">{__('Questions', 'sikshya')}</option>
              <option value="chapters">{__('Chapters', 'sikshya')}</option>
              <option value="certificates">{__('Certificates', 'sikshya')}</option>
            </select>
            <div className="mt-4">
              <ButtonPrimary type="button" disabled={busy} onClick={() => exportPayload()}>
                Download JSON
              </ButtonPrimary>
            </div>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 className="text-base font-semibold text-slate-900 dark:text-white">{__('Import settings', 'sikshya')}</h2>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Paste JSON from a settings export. Empty values are only overwritten when you enable the option below.
            </p>
            <label className="mt-4 block text-sm font-medium text-slate-800 dark:text-slate-200" htmlFor="sik-imp-json">
              Settings JSON
            </label>
            <textarea
              id="sik-imp-json"
              rows={10}
              className="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
              placeholder={__('Paste JSON from a settings export', 'sikshya')}
              value={importText}
              onChange={(e) => setImportText(e.target.value)}
            />
            <label className="mt-3 flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
              <input
                type="checkbox"
                checked={importOverwrite}
                onChange={(e) => setImportOverwrite(e.target.checked)}
                className="h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-700 dark:focus:ring-offset-slate-900"
              />
              Overwrite existing non-empty values
            </label>
            <div className="mt-4">
              <ButtonPrimary type="button" disabled={busy || !importText.trim()} onClick={() => importSettings()}>
                Import settings
              </ButtonPrimary>
            </div>
          </div>
        </div>
      ) : null}

      {tab === 'maintenance' ? (
        <div className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <h2 className="text-base font-semibold text-slate-900 dark:text-white">{__('Maintenance', 'sikshya')}</h2>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Clear Sikshya-related transients and object cache. Import demo content from the plugin sample pack. Reset
            removes the main Sikshya settings option.
          </p>
          <div className="mt-6 flex flex-wrap gap-3">
            <ButtonPrimary type="button" disabled={busy} onClick={() => importSampleLms()}>
              Import sample LMS data
            </ButtonPrimary>
            <ButtonPrimary type="button" disabled={busy} onClick={() => clearCache()}>
              Clear cache
            </ButtonPrimary>
            <button
              type="button"
              disabled={busy}
              className="rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50 dark:border-red-900/50 dark:bg-slate-900 dark:text-red-300 dark:hover:bg-red-950/40"
              onClick={() => resetPluginSettings()}
            >
              Reset all settings
            </button>
          </div>
        </div>
      ) : null}
    </EmbeddableShell>
  );
}
