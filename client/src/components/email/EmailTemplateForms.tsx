import { useEffect, useState } from 'react';
import { NavIcon } from '../NavIcon';
import { ButtonPrimary } from '../shared/buttons';
import { QuillField } from '../shared/QuillField';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../../api';
import type { EmailTemplateApi } from '../../types/emailTemplate';
import { TriggerEventSelect } from './TriggerEventSelect';
import { __ } from '../../lib/i18n';

export function ToggleSwitch(props: {
  checked: boolean;
  disabled?: boolean;
  onChange: (next: boolean) => void;
  label: string;
}) {
  const { checked, disabled, onChange, label } = props;
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      disabled={disabled}
      title={label}
      onClick={(e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!disabled) {
          onChange(!checked);
        }
      }}
      className={`relative inline-flex h-6 w-11 shrink-0 rounded-full border transition ${
        checked
          ? 'border-brand-500 bg-brand-500 dark:border-brand-400 dark:bg-brand-500'
          : 'border-slate-300 bg-slate-200 dark:border-slate-600 dark:bg-slate-700'
      } ${disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'}`}
    >
      <span
        className={`pointer-events-none inline-block h-5 w-5 translate-y-0 rounded-full bg-white shadow transition ${
          checked ? 'translate-x-5' : 'translate-x-0.5'
        }`}
      />
    </button>
  );
}

async function copyToClipboard(text: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(text);
  } catch {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
  }
}

const FT_INPUT =
  'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const LB = 'block text-sm font-medium text-slate-800 dark:text-slate-200';
const HELP = 'mt-1 text-xs text-slate-500 dark:text-slate-400';

type EditorProps = {
  editing: EmailTemplateApi;
  actionBusy: string | null;
  onBack: () => void;
  /** Called after successful save (e.g. navigate to list). Defaults to onBack. */
  afterSave?: () => void;
  patchTemplate: (id: string, body: Record<string, unknown>) => Promise<EmailTemplateApi>;
  onPreview: (row: EmailTemplateApi, draft?: { subject?: string; body_html?: string }) => Promise<void>;
};

export function EmailTemplateEditorPanel(props: EditorProps) {
  const { editing, actionBusy, onBack, afterSave, patchTemplate, onPreview } = props;
  const locked = !!editing.locked;
  const [name, setName] = useState(editing.name);
  const [description, setDescription] = useState(editing.description);
  const [subject, setSubject] = useState(editing.subject);
  const [bodyHtml, setBodyHtml] = useState(editing.body_html);
  const [enabled, setEnabled] = useState(editing.enabled);
  const [eventKey, setEventKey] = useState(editing.event);
  const [recipientTo, setRecipientTo] = useState(editing.recipient_to || '{{student_email}}');
  const [saveErr, setSaveErr] = useState<string | null>(null);

  useEffect(() => {
    setName(editing.name);
    setDescription(editing.description);
    setSubject(editing.subject);
    setBodyHtml(editing.body_html);
    setEnabled(editing.enabled);
    setEventKey((editing.event || 'custom.manual').trim() || 'custom.manual');
    setRecipientTo(editing.recipient_to?.trim() || '{{student_email}}');
  }, [editing]);

  const save = async () => {
    if (locked) return;
    setSaveErr(null);
    try {
      await patchTemplate(editing.id, {
        name,
        description,
        subject,
        body_html: bodyHtml,
        enabled,
        recipient_to: recipientTo,
        ...(editing.template_type === 'custom' ? { event: eventKey } : {}),
      });
      const done = afterSave ?? onBack;
      done();
    } catch (e) {
      setSaveErr(e instanceof Error ? e.message : __('Save failed', 'sikshya'));
    }
  };

  const busy = actionBusy === editing.id;
  const fieldLock = locked || busy;

  return (
    <div className="w-full space-y-6">
      {locked ? (
        <div className="rounded-xl border border-amber-200/90 bg-amber-50/95 px-4 py-3 text-sm text-amber-950 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-50">
          <strong className="font-semibold">{__('Add-on required.', 'sikshya')}</strong>{' '}
          {editing.locked_reason?.trim() ||
            __(
              'Enable the add-on and plan for this template under Addons and licensing, then return here to edit.',
              'sikshya'
            )}
        </div>
      ) : null}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <button
            type="button"
            onClick={onBack}
            className="mb-2 inline-flex items-center gap-2 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
          >
            {__('← Back to templates', 'sikshya')}
          </button>
          <div className="flex items-center gap-2">
            <NavIcon name="layers" className="h-5 w-5 text-slate-500" />
            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
              {__('Edit email template', 'sikshya')}
            </h2>
          </div>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {__('Sender identity and SMTP are configured under Email (delivery).', 'sikshya')}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2.5 rounded-xl border border-slate-200/90 bg-slate-50 px-3.5 py-2 dark:border-slate-600 dark:bg-slate-800/60">
            <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              {__('Status', 'sikshya')}
            </span>
            <span className="text-sm font-semibold text-slate-800 dark:text-slate-100">
              {enabled ? __('Active', 'sikshya') : __('Inactive', 'sikshya')}
            </span>
            <ToggleSwitch
              checked={enabled}
              onChange={setEnabled}
              label={__('Template active', 'sikshya')}
              disabled={fieldLock}
            />
          </div>
          <button
            type="button"
            disabled={fieldLock}
            onClick={() => void onPreview(editing, { subject, body_html: bodyHtml })}
            className="inline-flex items-center justify-center rounded-lg border border-slate-200/70 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
          >
            {__('Preview', 'sikshya')}
          </button>
          <ButtonPrimary type="button" disabled={fieldLock} onClick={() => void save()}>
            {__('Save template', 'sikshya')}
          </ButtonPrimary>
        </div>
      </div>

      {saveErr ? <p className="text-sm text-red-600 dark:text-red-400">{saveErr}</p> : null}

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div className="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950/40">
          <label className="block">
            <span className={LB}>{__('Template name', 'sikshya')}</span>
            <input
              className={FT_INPUT}
              value={name}
              onChange={(e) => setName(e.target.value)}
              disabled={fieldLock}
              placeholder={__('e.g. Post-enrollment tips', 'sikshya')}
            />
          </label>
          <label className="block">
            <QuillField
              label={__('Description', 'sikshya')}
              value={description}
              onChange={(html) => setDescription(html)}
              disabled={fieldLock}
              placeholder={__('Short note for admins: when this email should be used', 'sikshya')}
              minHeightPx={96}
            />
          </label>
          <div>
            <span className={LB}>{__('Trigger Event', 'sikshya')}</span>
            {editing.template_type === 'system' ? (
              <>
                <div className="mt-1.5 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900/50">
                  <span className="text-violet-600 dark:text-violet-400">⚡</span>
                  <code className="text-xs text-slate-800 dark:text-slate-200">{editing.event}</code>
                </div>
                <p className={HELP}>{__('System templates cannot change events.', 'sikshya')}</p>
              </>
            ) : (
              <>
                <div className="mt-1.5">
                  <TriggerEventSelect value={eventKey} onChange={setEventKey} disabled={fieldLock} />
                </div>
                <p className={HELP}>
                  {__(
                    'Sikshya fires these WordPress actions; your template runs when the matching event occurs.',
                    'sikshya'
                  )}
                </p>
              </>
            )}
          </div>
          <label className="block">
            <span className={LB}>{__('Send to', 'sikshya')}</span>
            <input
              className={`${FT_INPUT} font-mono text-xs`}
              value={recipientTo}
              onChange={(e) => setRecipientTo(e.target.value)}
              disabled={fieldLock}
              placeholder="{{student_email}} or {{instructor_email}} or {{admin_email}}"
            />
            <p className={HELP}>
              {__('Use merge tags for dynamic recipients. Examples:', 'sikshya')}{' '}
              <code className="text-xs">{'{{student_email}}'}</code>,{' '}
              <code className="text-xs">{'{{instructor_email}}'}</code>, <code className="text-xs">{'{{admin_email}}'}</code>
            </p>
          </label>
          <label className="block">
            <span className={LB}>{__('Subject line', 'sikshya')}</span>
            <input
              className={FT_INPUT}
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              disabled={fieldLock}
              placeholder="e.g. [{{site_name}}] Update about {{course_title}}"
            />
          </label>
          <label className="block">
            <span className={LB}>{__('Email body (HTML)', 'sikshya')}</span>
            <textarea
              className={`${FT_INPUT} min-h-[280px] font-mono text-xs leading-relaxed`}
              value={bodyHtml}
              onChange={(e) => setBodyHtml(e.target.value)}
              disabled={fieldLock}
              placeholder="e.g. <p>Hi {{student_name}},</p><p>Your message here…</p>"
            />
          </label>
        </div>

        <div className="space-y-4">
          <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950/40">
            <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
              <span className="font-mono text-slate-500">&lt; &gt;</span>
              {__('Available variables', 'sikshya')}
            </div>
            <p className="mb-3 text-xs text-slate-500 dark:text-slate-400">
              {__('Click a tag to copy; paste into subject or HTML body.', 'sikshya')}
            </p>
            <div className="flex flex-wrap gap-2">
              {(editing.merge_tags || []).map((tag) => (
                <button
                  key={tag}
                  type="button"
                  onClick={() => void copyToClipboard(tag)}
                  className="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-900 hover:bg-sky-100 dark:bg-sky-950/50 dark:text-sky-200 dark:hover:bg-sky-900/40"
                >
                  {tag}
                </button>
              ))}
            </div>
          </div>
          <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950/40">
            <div className="mb-3 text-sm font-semibold text-slate-900 dark:text-white">
              {__('Template info', 'sikshya')}
            </div>
            <dl className="space-y-2 text-xs">
              <div className="flex justify-between gap-2">
                <dt className="text-slate-500">{__('Event', 'sikshya')}</dt>
                <dd>
                  <span className="rounded-md bg-violet-100 px-2 py-0.5 font-mono text-xs text-violet-900 dark:bg-violet-950/50 dark:text-violet-200">
                    {editing.event}
                  </span>
                </dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-slate-500">{__('Key', 'sikshya')}</dt>
                <dd className="font-mono text-slate-800 dark:text-slate-200">{editing.id}</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-slate-500">{__('Category', 'sikshya')}</dt>
                <dd className="capitalize text-slate-800 dark:text-slate-200">{editing.category}</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-slate-500">{__('Audience', 'sikshya')}</dt>
                <dd className="font-mono text-xs text-slate-700 dark:text-slate-300">{editing.recipient}</dd>
              </div>
              <div className="flex flex-col gap-1">
                <dt className="text-slate-500">{__('Send to', 'sikshya')}</dt>
                <dd className="break-all font-mono text-xs text-slate-800 dark:text-slate-200">{recipientTo}</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-slate-500">{__('Type', 'sikshya')}</dt>
                <dd className="text-slate-800 dark:text-slate-200">
                  {editing.template_type === 'system' ? __('System', 'sikshya') : __('Custom', 'sikshya')}
                </dd>
              </div>
            </dl>
          </div>
        </div>
      </div>
    </div>
  );
}

type CreateProps = {
  onCancel: () => void;
  onCreated: (t: EmailTemplateApi) => void;
};

export function EmailTemplateCreateForm(props: CreateProps) {
  const { onCancel, onCreated } = props;
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [event, setEvent] = useState('custom.manual');
  const [recipientTo, setRecipientTo] = useState('{{student_email}}');
  const [subject, setSubject] = useState('');
  const [bodyHtml, setBodyHtml] = useState('<p></p>');
  const [enabled, setEnabled] = useState(true);
  const [err, setErr] = useState<string | null>(null);

  const submit = async () => {
    setErr(null);
    try {
      const created = await getSikshyaApi().post<EmailTemplateApi>(SIKSHYA_ENDPOINTS.admin.emailTemplates, {
        name,
        description,
        event,
        recipient_to: recipientTo,
        subject,
        body_html: bodyHtml,
        enabled,
      });
      onCreated(created);
    } catch (e) {
      setErr(e instanceof Error ? e.message : __('Could not create template', 'sikshya'));
    }
  };

  return (
    <div className="w-full space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <button
            type="button"
            onClick={onCancel}
            className="mb-2 inline-flex items-center gap-2 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
          >
            {__('← Back to templates', 'sikshya')}
          </button>
          <div className="flex items-center gap-2">
            <NavIcon name="plusDocument" className="h-5 w-5 text-slate-500" />
            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
              {__('New email template', 'sikshya')}
            </h2>
          </div>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {__('Custom templates can use manual or automation triggers.', 'sikshya')}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2.5 rounded-xl border border-slate-200/90 bg-slate-50 px-3.5 py-2 dark:border-slate-600 dark:bg-slate-800/60">
            <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              {__('Status', 'sikshya')}
            </span>
            <span className="text-sm font-semibold text-slate-800 dark:text-slate-100">
              {enabled ? __('Active', 'sikshya') : __('Inactive', 'sikshya')}
            </span>
            <ToggleSwitch checked={enabled} onChange={setEnabled} label={__('Template active', 'sikshya')} />
          </div>
          <ButtonPrimary type="button" onClick={() => void submit()} disabled={!name.trim()}>
            {__('Create template', 'sikshya')}
          </ButtonPrimary>
        </div>
      </div>

      {err ? <p className="text-sm text-red-600 dark:text-red-400">{err}</p> : null}

      <div className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950/40">
        <div className="space-y-4">
          <label className="block">
            <span className={LB}>{__('Template name', 'sikshya')}</span>
            <input
              className={FT_INPUT}
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={__('e.g. Re-engagement after enrollment', 'sikshya')}
            />
          </label>
          <label className="block">
            <QuillField
              label={__('Description', 'sikshya')}
              value={description}
              onChange={(html) => setDescription(html)}
              placeholder={__('When to send this template (internal note)', 'sikshya')}
              minHeightPx={160}
            />
          </label>
          <div>
            <span className={LB}>{__('Trigger Event', 'sikshya')}</span>
            <div className="mt-1.5">
              <TriggerEventSelect value={event} onChange={setEvent} />
            </div>
            <p className={HELP}>
              {__('Select an event to trigger this email, or “No event” for sequence / manual use.', 'sikshya')}
            </p>
          </div>
          <label className="block">
            <span className={LB}>{__('Send to', 'sikshya')}</span>
            <input
              className={`${FT_INPUT} font-mono text-xs`}
              value={recipientTo}
              onChange={(e) => setRecipientTo(e.target.value)}
              placeholder="{{student_email}} · {{instructor_email}} · {{admin_email}}"
            />
            <p className={HELP}>
              {__('Who receives this email — merge tags resolve when the message is sent.', 'sikshya')}
            </p>
          </label>
          <label className="block">
            <span className={LB}>{__('Subject line', 'sikshya')}</span>
            <input
              className={FT_INPUT}
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              placeholder="e.g. [{{site_name}}] A quick note about {{course_title}}"
            />
          </label>
          <label className="block">
            <span className={LB}>{__('Email body (HTML)', 'sikshya')}</span>
            <textarea
              className={`${FT_INPUT} min-h-[200px] font-mono text-xs`}
              value={bodyHtml}
              onChange={(e) => setBodyHtml(e.target.value)}
              placeholder="e.g. <p>Hello {{student_name}},</p><p>Your HTML content…</p>"
            />
          </label>
        </div>
      </div>
    </div>
  );
}
