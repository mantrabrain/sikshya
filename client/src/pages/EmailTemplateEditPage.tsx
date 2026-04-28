import { useCallback, useEffect, useState } from 'react';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { EmailPreviewModal } from '../components/email/EmailPreviewModal';
import { LinkButtonSecondary } from '../components/shared/buttons';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { appViewHref } from '../lib/appUrl';
import { useAdminRouting } from '../lib/adminRouting';
import type { NavItem, SikshyaReactConfig } from '../types';
import type { EmailTemplateApi } from '../types/emailTemplate';
import { EmailTemplateCreateForm, EmailTemplateEditorPanel } from '../components/email/EmailTemplateForms';
import { SIKSHYA_ADMIN_PAGE_FULL_WIDTH } from '../constants/shellLayout';

/**
 * Full-page create (`template_id=new`) or edit for a single email template (no modal).
 */
export function EmailTemplateEditPage(props: { embedded?: boolean; config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const { navigateHref } = useAdminRouting();
  const rawId = (config.query.template_id || 'new').trim();
  const isNew = rawId === 'new';

  const listHref = appViewHref(config, 'email-hub', { tab: 'templates' });

  const [loading, setLoading] = useState(!isNew);
  const [error, setError] = useState<unknown>(null);
  const [editing, setEditing] = useState<EmailTemplateApi | null>(null);
  const [actionBusy, setActionBusy] = useState<string | null>(null);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewHtml, setPreviewHtml] = useState('');
  const [previewSubject, setPreviewSubject] = useState('');

  const load = useCallback(async () => {
    if (isNew) {
      setEditing(null);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const t = await getSikshyaApi().get<EmailTemplateApi>(SIKSHYA_ENDPOINTS.admin.emailTemplate(rawId));
      setEditing(t);
    } catch (e) {
      setError(e);
      setEditing(null);
    } finally {
      setLoading(false);
    }
  }, [isNew, rawId]);

  useEffect(() => {
    void load();
  }, [load]);

  const patchTemplate = async (id: string, body: Record<string, unknown>) => {
    setActionBusy(id);
    try {
      const updated = await getSikshyaApi().patch<EmailTemplateApi>(SIKSHYA_ENDPOINTS.admin.emailTemplate(id), body);
      setEditing((prev) => (prev && prev.id === id ? { ...prev, ...updated } : prev));
      return updated;
    } finally {
      setActionBusy(null);
    }
  };

  const openPreview = useCallback(async (row: EmailTemplateApi, draft?: { subject?: string; body_html?: string }) => {
    setActionBusy(row.id);
    try {
      const res = await getSikshyaApi().post<{ subject: string; html: string }>(
        SIKSHYA_ENDPOINTS.admin.emailTemplatePreview(row.id),
        {
          subject: draft?.subject ?? row.subject,
          body_html: draft?.body_html ?? row.body_html,
        }
      );
      setPreviewSubject(res.subject);
      setPreviewHtml(res.html);
      setPreviewOpen(true);
    } catch (e) {
      setError(e);
    } finally {
      setActionBusy(null);
    }
  }, []);

  const goList = () => navigateHref(listHref);

  const onCreated = (t: EmailTemplateApi) => {
    navigateHref(appViewHref(config, 'email-template-edit', { template_id: t.id }));
  };

  return (
    <EmbeddableShell
      embedded={props.embedded}
      config={config}
      title={title}
      subtitle={isNew ? 'Create a custom transactional template' : 'Edit subject, body, and availability'}
      sidebarActivePage="email-hub"
      pageActions={
        <div className="flex flex-wrap items-center gap-2">
          <LinkButtonSecondary href={listHref}>← All templates</LinkButtonSecondary>
          <LinkButtonSecondary href={appViewHref(config, 'email-hub', { tab: 'delivery' })}>Email delivery</LinkButtonSecondary>
        </div>
      }
    >
      <div className={SIKSHYA_ADMIN_PAGE_FULL_WIDTH}>
        {error && !loading ? (
          <div className="mb-4">
            <ApiErrorPanel error={error} title="Could not load template" onRetry={() => void load()} />
          </div>
        ) : null}

        {loading ? (
          <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">
            Loading template…
          </div>
        ) : isNew ? (
          <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950/35">
            <div className="px-6 py-6">
              <EmailTemplateCreateForm onCancel={goList} onCreated={onCreated} />
            </div>
          </div>
        ) : editing ? (
          <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950/35">
            <div className="px-6 py-6">
              <EmailTemplateEditorPanel
                editing={editing}
                actionBusy={actionBusy}
                onBack={goList}
                afterSave={() => {
                  /* stay on page after save */
                }}
                patchTemplate={patchTemplate}
                onPreview={openPreview}
              />
            </div>
          </div>
        ) : (
          <div className="rounded-2xl border border-dashed border-slate-200 px-6 py-12 text-center text-sm text-slate-500 dark:border-slate-800">
            Template not found.
          </div>
        )}
      </div>

      <EmailPreviewModal
        open={previewOpen}
        subject={previewSubject}
        html={previewHtml}
        onClose={() => setPreviewOpen(false)}
      />
    </EmbeddableShell>
  );
}
