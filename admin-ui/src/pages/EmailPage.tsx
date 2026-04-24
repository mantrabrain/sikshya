import { useEffect, useMemo, useState } from 'react';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { EmailDeliverySettings } from '../components/EmailDeliverySettings';
import { ButtonPrimary } from '../components/shared/buttons';
import { NavIcon } from '../components/NavIcon';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AsyncBoundary } from '../components/shared/AsyncBoundary';
import { SkeletonCard } from '../components/shared/Skeleton';
import { useAsyncData } from '../hooks/useAsyncData';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { useSikshyaDialog } from '../components/shared/SikshyaDialogContext';
import { SIKSHYA_ADMIN_PAGE_FULL_WIDTH } from '../constants/shellLayout';
import type { SikshyaReactConfig } from '../types';
import type { SettingsField, SettingsSection } from '../types/settingsSchema';
import { renderSettingsField } from './settingsRenderField';
import { normalizeTabSections } from './settingsTabUtils';

type SettingsSchema = Record<string, SettingsSection[]>;

type ToastState = {
  open: boolean;
  kind: 'success' | 'error' | 'info';
  title: string;
  message?: string;
};

function normalizeForDirtyCompare(v: unknown): string {
  if (v === null || v === undefined) return '';
  if (typeof v === 'boolean') return v ? '1' : '0';
  if (typeof v === 'number') return String(v);
  return String(v);
}

function stableNormalizeRecord(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  const keys = Object.keys(obj).sort();
  for (const k of keys) {
    out[k] = normalizeForDirtyCompare(obj[k]);
  }
  return out;
}

/**
 * Full-width transactional email settings (moved out of Settings for breathing room).
 */
export function EmailPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const { confirm } = useSikshyaDialog();
  const tab = 'email';

  const schema = useAsyncData(async () => {
    const res = await getSikshyaApi().get<{ success: boolean; data?: { tabs?: SettingsSchema } }>(
      SIKSHYA_ENDPOINTS.settings.schema
    );
    if (!res.success) {
      throw new Error('Could not load settings schema.');
    }
    return { tabs: res.data?.tabs || {} };
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
  const [toast, setToast] = useState<ToastState | null>(null);
  const [initialValues, setInitialValues] = useState<Record<string, unknown>>({});

  useEffect(() => {
    if (!values.data) return;
    setDraft(values.data);
    setInitialValues(values.data);
    setSaveMsg(null);
    setSaveError(null);
    setToast(null);
  }, [values.data]);

  const tabSchema = normalizeTabSections((schema.data?.tabs || {})[tab]);

  const dirty = useMemo(() => {
    try {
      return JSON.stringify(stableNormalizeRecord(draft)) !== JSON.stringify(stableNormalizeRecord(initialValues));
    } catch {
      return true;
    }
  }, [draft, initialValues]);

  useEffect(() => {
    if (!toast?.open) return;
    const t = window.setTimeout(() => setToast(null), 3800);
    return () => window.clearTimeout(t);
  }, [toast]);

  const onSave = async () => {
    setSaving(true);
    setSaveError(null);
    setSaveMsg(null);
    setToast(null);
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
      const msg = res.message || 'Settings saved.';
      setSaveMsg(msg);
      setToast({ open: true, kind: 'success', title: 'Saved', message: msg });
    } catch (e) {
      setSaveError(e);
      setToast({
        open: true,
        kind: 'error',
        title: 'Save failed',
        message: e instanceof Error ? e.message : 'Could not save settings. Please try again.',
      });
    } finally {
      setSaving(false);
    }
  };

  const onReset = async () => {
    const ok = await confirm({
      title: 'Reset email settings?',
      message: 'Reset email settings to their default values?',
      variant: 'danger',
      confirmLabel: 'Reset',
    });
    if (!ok) {
      return;
    }
    setSaving(true);
    setSaveError(null);
    setSaveMsg(null);
    setToast(null);
    try {
      const res = await getSikshyaApi().post<{ success: boolean; message?: string }>(SIKSHYA_ENDPOINTS.settings.reset, {
        tab,
      });
      if (!res.success) {
        throw new Error(res.message || 'Reset failed.');
      }
      await values.refetch();
      const msg = res.message || 'Settings reset.';
      setSaveMsg(msg);
      setToast({ open: true, kind: 'success', title: 'Reset', message: msg });
    } catch (e) {
      setSaveError(e);
      setToast({
        open: true,
        kind: 'error',
        title: 'Reset failed',
        message: e instanceof Error ? e.message : 'Could not reset settings. Please try again.',
      });
    } finally {
      setSaving(false);
    }
  };

  const renderField = (f: SettingsField) => renderSettingsField(draft, setDraft, f);

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Sender identity, SMTP, and global HTML wrappers — template copy lives under Email templates"
    >
      {toast?.open ? (
        <div className="fixed right-6 top-6 z-[9999] w-[360px] max-w-[calc(100vw-48px)]">
          <div
            className={`rounded-2xl border px-4 py-3 shadow-lg backdrop-blur dark:backdrop-blur ${
              toast.kind === 'success'
                ? 'border-emerald-200 bg-emerald-50/95 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/60 dark:text-emerald-100'
                : toast.kind === 'error'
                  ? 'border-rose-200 bg-rose-50/95 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/60 dark:text-rose-100'
                  : 'border-slate-200 bg-white/95 text-slate-900 dark:border-slate-800 dark:bg-slate-900/90 dark:text-slate-100'
            }`}
            role="status"
            aria-live="polite"
          >
            <div className="flex items-start gap-3">
              <span
                className={`mt-0.5 flex h-9 w-9 items-center justify-center rounded-xl ${
                  toast.kind === 'success'
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200'
                    : toast.kind === 'error'
                      ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200'
                      : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
                }`}
              >
                <NavIcon name={toast.kind === 'success' ? 'badge' : 'helpCircle'} className="h-5 w-5" />
              </span>
              <div className="min-w-0 flex-1">
                <div className="text-sm font-semibold">{toast.title}</div>
                {toast.message ? <div className="mt-0.5 text-xs leading-snug opacity-90">{toast.message}</div> : null}
              </div>
              <button
                type="button"
                onClick={() => setToast(null)}
                className="rounded-lg px-2 py-1 text-xs font-semibold opacity-70 hover:opacity-100"
                aria-label="Dismiss"
              >
                ✕
              </button>
            </div>
          </div>
        </div>
      ) : null}

      <div className={SIKSHYA_ADMIN_PAGE_FULL_WIDTH}>
        <AsyncBoundary
          loading={schema.loading || values.loading}
          error={schema.error || values.error}
          onRetry={() => {
            schema.refetch();
            values.refetch();
          }}
          skeleton={
            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
              <SkeletonCard rows={10} />
            </div>
          }
        >
          <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950/35">
            <div className="border-b border-slate-200/60 px-6 py-5 dark:border-slate-800/70">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                  <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <NavIcon name="plusDocument" className="h-4 w-4" />
                    Email
                  </div>
                  <h2 className="mt-1 text-lg font-semibold text-slate-900 dark:text-white">Email delivery</h2>
                  <p className="mt-1 text-sm text-slate-400/90 dark:text-slate-500/80">
                    Configure how mail is sent and wrapped. Enable or edit individual messages on the Email templates screen.
                  </p>
                  {saveMsg ? <p className="mt-2 text-xs font-medium text-emerald-700 dark:text-emerald-300">{saveMsg}</p> : null}
                </div>
                <div className="flex shrink-0 flex-wrap items-center justify-end gap-2 sm:min-w-[200px]">
                  <button
                    type="button"
                    disabled={saving}
                    onClick={() => void onReset()}
                    className="inline-flex items-center justify-center rounded-lg border border-slate-200/70 bg-white/80 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-white disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-200 dark:hover:bg-slate-900"
                  >
                    Reset
                  </button>
                  <ButtonPrimary type="button" disabled={saving || !dirty} onClick={() => void onSave()}>
                    {saving ? 'Saving…' : 'Save email settings'}
                  </ButtonPrimary>
                </div>
              </div>
            </div>

            {saveError ? (
              <div className="px-6 pt-4">
                <ApiErrorPanel error={saveError} title="Settings request failed" onRetry={() => setSaveError(null)} />
              </div>
            ) : null}

            <div className="py-6">
              {tabSchema.length ? (
                <EmailDeliverySettings config={config} tabSchema={tabSchema} renderField={renderField} />
              ) : (
                <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/30 px-6 py-12 text-center dark:border-slate-800 dark:bg-slate-950/20">
                  <p className="text-sm font-medium text-slate-700 dark:text-slate-200">No email settings defined.</p>
                </div>
              )}
            </div>
          </div>
        </AsyncBoundary>
      </div>
    </EmbeddableShell>
  );
}
