import { useCallback, useEffect, useMemo, useState } from 'react';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { NavIcon } from '../components/NavIcon';
import { ButtonPrimary } from '../components/shared/buttons';
import { LinkButtonSecondary } from '../components/shared/buttons';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { appViewHref } from '../lib/appUrl';
import { useAdminRouting } from '../lib/adminRouting';
import type { SikshyaReactConfig } from '../types';
import type { EmailTemplateApi } from '../types/emailTemplate';
import { ToggleSwitch } from '../components/email/EmailTemplateForms';
import { TableSkeleton } from '../components/shared/Skeleton';

function categoryStyle(cat: string): string {
  const c = cat.toLowerCase();
  if (c.includes('enroll')) {
    return 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-200';
  }
  if (c.includes('complet')) {
    return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200';
  }
  if (c.includes('cert')) {
    return 'bg-violet-100 text-violet-900 dark:bg-violet-950/50 dark:text-violet-200';
  }
  if (c.includes('auto') || c.includes('account')) {
    return 'bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-200';
  }
  return 'bg-slate-200/90 text-slate-800 dark:bg-slate-800 dark:text-slate-200';
}

/**
 * Transactional email templates — list + bulk actions (matches Courses-style admin patterns).
 */
export function EmailTemplatesListPage(props: { config: SikshyaReactConfig; title: string; embedded?: boolean }) {
  const { config, title, embedded } = props;
  const { navigateHref } = useAdminRouting();
  const [rows, setRows] = useState<EmailTemplateApi[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<unknown>(null);
  const [actionBusy, setActionBusy] = useState<string | null>(null);
  const [selected, setSelected] = useState<Set<string>>(() => new Set());
  const [bulkBusy, setBulkBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getSikshyaApi().get<{ templates: EmailTemplateApi[] }>(SIKSHYA_ENDPOINTS.admin.emailTemplates);
      setRows(Array.isArray(res.templates) ? res.templates : []);
      setSelected(new Set());
    } catch (e) {
      setError(e);
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const allIds = useMemo(() => rows.map((r) => r.id), [rows]);
  const allSelected = rows.length > 0 && selected.size === rows.length;
  const someSelected = selected.size > 0;

  const toggleSelectAll = () => {
    if (allSelected) {
      setSelected(new Set());
    } else {
      setSelected(new Set(allIds));
    }
  };

  const toggleRow = (id: string) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const patchTemplate = async (id: string, body: Record<string, unknown>) => {
    setActionBusy(id);
    try {
      const updated = await getSikshyaApi().patch<EmailTemplateApi>(SIKSHYA_ENDPOINTS.admin.emailTemplate(id), body);
      setRows((prev) => prev.map((t) => (t.id === id ? { ...t, ...updated } : t)));
      return updated;
    } finally {
      setActionBusy(null);
    }
  };

  const onToggleEnabled = async (row: EmailTemplateApi) => {
    if (row.locked) {
      return;
    }
    try {
      await patchTemplate(row.id, { enabled: !row.enabled });
    } catch {
      /* ignore */
    }
  };

  const runBulk = async (action: 'enable' | 'disable' | 'delete') => {
    const ids = Array.from(selected);
    if (ids.length === 0) {
      return;
    }
    if (action === 'delete') {
      const ok = window.confirm(`Delete ${ids.length} custom template(s)? System templates stay selected only if you mixed — those will be skipped.`);
      if (!ok) {
        return;
      }
    }
    setBulkBusy(true);
    try {
      await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.emailTemplateBulk, { action, ids });
      await load();
    } catch (e) {
      setError(e);
    } finally {
      setBulkBusy(false);
    }
  };

  const editHref = (id: string) => appViewHref(config, 'email-template-edit', { template_id: id });
  const newHref = appViewHref(config, 'email-template-edit', { template_id: 'new' });
  const addonsHref = appViewHref(config, 'addons');

  return (
    <EmbeddableShell
      embedded={embedded}
      config={config}
      title={title}
      subtitle="Enable, edit, and manage transactional emails — including drip unlock (“Drip: lesson unlocked”, “Drip: course schedule unlocked”). Disabling a template stops only that email, not Content drip. From / reply / SMTP live under Email → Delivery."
      pageActions={
        <div className="flex flex-wrap items-center gap-2">
          <LinkButtonSecondary href={appViewHref(config, 'email-hub', { tab: 'delivery' })}>Email delivery</LinkButtonSecondary>
          <ButtonPrimary type="button" disabled={loading} onClick={() => void load()}>
            Refresh
          </ButtonPrimary>
          <ButtonPrimary type="button" onClick={() => navigateHref(newHref)}>
            Add template
          </ButtonPrimary>
        </div>
      }
    >
      {error ? (
        <div className="mb-4">
          <ApiErrorPanel error={error} title="Could not load templates" onRetry={() => void load()} />
        </div>
      ) : null}

      {someSelected ? (
        <div className="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/50">
          <span className="text-sm font-medium text-slate-700 dark:text-slate-200">{selected.size} selected</span>
          <button
            type="button"
            disabled={bulkBusy}
            onClick={() => void runBulk('enable')}
            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700"
          >
            Enable
          </button>
          <button
            type="button"
            disabled={bulkBusy}
            onClick={() => void runBulk('disable')}
            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700"
          >
            Disable
          </button>
          <button
            type="button"
            disabled={bulkBusy}
            onClick={() => void runBulk('delete')}
            className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-100 disabled:opacity-50 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-100 dark:hover:bg-rose-950/60"
          >
            Delete custom
          </button>
        </div>
      ) : null}

      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950/40">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1000px] table-fixed border-collapse text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50/95 dark:border-slate-800 dark:bg-slate-900/60">
                <th className="w-10 px-3 py-3.5">
                  <input
                    type="checkbox"
                    className="rounded border-slate-300"
                    checked={allSelected}
                    onChange={toggleSelectAll}
                    disabled={loading || rows.length === 0}
                    aria-label="Select all templates"
                  />
                </th>
                <th className="px-3 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Template
                </th>
                <th className="px-3 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Event
                </th>
                <th className="px-3 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Subject
                </th>
                <th className="px-3 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Category
                </th>
                <th className="max-w-[200px] px-3 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Send to
                </th>
                <th className="px-3 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Status
                </th>
                <th className="w-28 px-3 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Actions
                </th>
              </tr>
            </thead>
            {loading ? (
              <TableSkeleton columns={8} rows={10} />
            ) : (
              <tbody>
                {rows.map((row, idx) => (
                  <tr
                    key={row.id}
                    className={`border-b border-slate-100 transition hover:bg-slate-50/90 dark:border-slate-800/80 dark:hover:bg-slate-900/40 ${
                      idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50/40 dark:bg-slate-950/30'
                    } ${row.locked ? 'opacity-[0.88]' : ''}`}
                  >
                    <td className="px-3 py-3 align-middle">
                      <input
                        type="checkbox"
                        className="rounded border-slate-300"
                        checked={selected.has(row.id)}
                        onChange={() => toggleRow(row.id)}
                        aria-label={`Select ${row.name}`}
                      />
                    </td>
                    <td className="px-3 py-3 align-top">
                      <div className="flex items-start gap-2">
                        <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300">
                          <NavIcon name="plusDocument" className="h-4 w-4" />
                        </span>
                        <div className="min-w-0">
                          <a
                            href={editHref(row.id)}
                            onClick={(e) => {
                              e.preventDefault();
                              navigateHref(editHref(row.id));
                            }}
                            className="font-semibold text-brand-600 hover:underline dark:text-brand-400"
                          >
                            {row.name}
                          </a>
                          <div className="mt-1 flex flex-wrap items-center gap-1">
                            <span
                              className={`inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${
                                row.template_type === 'system'
                                  ? 'bg-violet-100 text-violet-900 dark:bg-violet-950/50 dark:text-violet-200'
                                  : 'bg-slate-200/90 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
                              }`}
                            >
                              {row.template_type === 'system' ? 'System' : 'Custom'}
                            </span>
                            {row.locked ? (
                              <span className="inline-flex rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-950 dark:bg-amber-950/50 dark:text-amber-100">
                                Add-on off
                              </span>
                            ) : null}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="max-w-[160px] px-3 py-3 align-top">
                      <span className="inline-flex max-w-full items-center gap-1 rounded-full bg-violet-50 px-2 py-1 text-[11px] font-medium text-violet-900 dark:bg-violet-950/40 dark:text-violet-200">
                        <span aria-hidden>⚡</span>
                        <span className="truncate font-mono">{row.event}</span>
                      </span>
                    </td>
                    <td className="max-w-[220px] px-3 py-3 align-top text-xs text-slate-700 dark:text-slate-300">
                      <span className="line-clamp-2">{row.subject}</span>
                    </td>
                    <td className="px-3 py-3 align-top">
                      <span
                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize ${categoryStyle(
                          row.category
                        )}`}
                      >
                        <NavIcon name="course" className="h-3.5 w-3.5 opacity-70" />
                        {row.category}
                      </span>
                    </td>
                    <td className="max-w-[200px] px-3 py-3 align-top">
                      <code className="line-clamp-2 break-all text-[10px] text-slate-600 dark:text-slate-400">
                        {row.recipient_to || '{{student_email}}'}
                      </code>
                    </td>
                    <td className="px-3 py-3 align-middle">
                      <ToggleSwitch
                        checked={row.enabled}
                        disabled={actionBusy === row.id || !!row.locked}
                        onChange={() => void onToggleEnabled(row)}
                        label="Enabled"
                      />
                    </td>
                    <td className="px-3 py-3 align-middle">
                      <div className="flex flex-col gap-1">
                        <button
                          type="button"
                          onClick={() => navigateHref(editHref(row.id))}
                          className={`text-left text-xs font-semibold hover:underline ${
                            row.locked
                              ? 'text-slate-500 dark:text-slate-400'
                              : 'text-brand-600 dark:text-brand-400'
                          }`}
                        >
                          {row.locked ? 'View (locked)' : 'Edit'}
                        </button>
                        {row.locked ? (
                          <a
                            href={addonsHref}
                            className="text-[11px] font-medium text-amber-800 underline underline-offset-2 hover:text-amber-900 dark:text-amber-200 dark:hover:text-amber-100"
                          >
                            Add-ons
                          </a>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            )}
          </table>
        </div>
        {!loading && rows.length === 0 ? (
          <div className="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No templates found.</div>
        ) : null}
      </div>
    </EmbeddableShell>
  );
}
