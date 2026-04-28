import { useEffect, useState } from 'react';
import { NavIcon } from '../NavIcon';
import { ButtonPrimary } from '../shared/buttons';
import { QuillField } from '../shared/QuillField';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../../api';
import type { EmailTemplateApi } from '../../types/emailTemplate';
import { TriggerEventSelect } from './TriggerEventSelect';

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
      setSaveErr(e instanceof Error ? e.message : 'Save failed');
    }
  };

  const busy = actionBusy === editing.id;
  const fieldLock = locked || busy;

  return (
    <div className="w-full space-y-6">
      {locked ? (
        <div className="rounded-xl border border-amber-200/90 bg-amber-50/95 px-4 py-3 text-sm text-amber-950 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-50">
          <strong className="font-semibold">Add-on required.</strong>{' '}
          {editing.locked_reason?.trim() ||
            'Enable the add-on and plan for this template under Addons and licensing, then return here to edit.'}
        </div>
      ) : null}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <button
            type="button"
            onClick={onBack}
            className="mb-2 inline-flex items-center gap-2 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
          >
            ← Back to templates
          </button>
          <div className="flex items-center gap-2">
            <NavIcon name="layers" className="h-5 w-5 text-slate-500" />
            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Edit email template</h2>
          </div>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Sender identity and SMTP are configured under <strong className="font-medium">Email</strong> (delivery).
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2.5 rounded-xl border border-slate-200/90 bg-slate-50 px-3.5 py-2 dark:border-slate-600 dark:bg-slate-800/60">
            <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</span>
            <span className="text-sm font-semibold text-slate-800 dark:text-slate-100">{enabled ? 'Active' : 'Inactive'}</span>
            <ToggleSwitch
              checked={enabled}
              onChange={setEnabled}
              label="Template active"
              disabled={fieldLock}
            />
          </div>
          <button
            type="button"
            disabled={fieldLock}
            onClick={() => void onPreview(editing, { subject, body_html: bodyHtml })}
            className="inline-flex items-center justify-center rounded-lg border border-slate-200/70 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
          >
            Preview
          </button>
          <ButtonPrimary type="button" disabled={fieldLock} onClick={() => void save()}>
            Save template
          </ButtonPrimary>
        </div>
      </div>

      {saveErr ? <p className="text-sm text-red-600 dark:text-red-400">{saveErr}</p> : null}

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div className="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950/40">
          <label className="block">
            <span className={LB}>Template name</span>
            <input
              className={FT_INPUT}
              value={name}
              onChange={(e) => setName(e.target.value)}
              disabled={fieldLock}
              placeholder="e.g. Post-enrollment tips"
            />
          </label>
          <label className="block">
            <QuillField
              label="Description"
              value={description}
              onChange={(html) => setDescription(html)}
              disabled={fieldLock}
              placeholder="Short note for admins: when this email should be used"
              minHeightPx={96}
            />
          </label>
          <div>
            <span className={LB}>Trigger Event</span>
            {editing.template_type === 'system' ? (
              <>
                <div className="mt-1.5 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900/50">
                  <span className="text-violet-600 dark:text-violet-400">⚡</span>
                  <code className="text-xs text-slate-800 dark:text-slate-200">{editing.event}</code>
                </div>
                <p className={HELP}>System templates cannot change events.</p>
              </>
            ) : (
              <>
                <div className="mt-1.5">
                  <TriggerEventSelect value={eventKey} onChange={setEventKey} disabled={fieldLock} />
                </div>
                <p className={HELP}>Sikshya fires these WordPress actions; your template runs when the matching event occurs.</p>
              </>
            )}
          </div>
          <label className="block">
            <span className={LB}>Send to</span>
            <input
              className={`${FT_INPUT} font-mono text-xs`}
              value={recipientTo}
              onChange={(e) => setRecipientTo(e.target.value)}
              disabled={fieldLock}
              placeholder="{{student_email}} or {{instructor_email}} or {{admin_email}}"
            />
            <p className={HELP}>
              Use merge tags for dynamic recipients. Examples: <code className="text-[11px]">{'{{student_email}}'}</code>,{' '}
              <code className="text-[11px]">{'{{instructor_email}}'}</code>, <code className="text-[11px]">{'{{admin_email}}'}</code>
            </p>
          </label>
          <label className="block">
            <span className={LB}>Subject line</span>
            <input
              className={FT_INPUT}
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              disabled={fieldLock}
              placeholder="e.g. [{{site_name}}] Update about {{course_title}}"
            />
          </label>
          <label className="block">
            <span className={LB}>Email body (HTML)</span>
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
              Available variables
            </div>
            <p className="mb-3 text-xs text-slate-500 dark:text-slate-400">Click a tag to copy; paste into subject or HTML body.</p>
            <div className="flex flex-wrap gap-2">
              {(editing.merge_tags || []).map((tag) => (
                <button
                  key={tag}
                  type="button"
                  onClick={() => void copyToClipboard(tag)}
                  className="rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-medium text-sky-900 hover:bg-sky-100 dark:bg-sky-950/50 dark:text-sky-200 dark:hover:bg-sky-900/40"
                >
                  {tag}
                </button>
              ))}
            </div>
          </div>
          <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950/40">
            <div className="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Template info</div>
            <dl className="space-y-2 text-xs">
              <div className="flex justify-between gap-2">
                <dt className="text-slate-500">Event</dt>
                <dd>
                  <span className="rounded-md bg-violet-100 px-2 py-0.5 font-mono text-[10px] text-violet-900 dark:bg-violet-950/50 dark:text-violet-200">
                    {editing.event}
                  </span>
                </dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-slate-500">Key</dt>
                <dd className="font-mono text-slate-800 dark:text-slate-200">{editing.id}</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-slate-500">Category</dt>
                <dd className="capitalize text-slate-800 dark:text-slate-200">{editing.category}</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-slate-500">Audience</dt>
                <dd className="font-mono text-[10px] text-slate-700 dark:text-slate-300">{editing.recipient}</dd>
              </div>
              <div className="flex flex-col gap-1">
                <dt className="text-slate-500">Send to</dt>
                <dd className="break-all font-mono text-[10px] text-slate-800 dark:text-slate-200">{recipientTo}</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-slate-500">Type</dt>
                <dd className="text-slate-800 dark:text-slate-200">{editing.template_type === 'system' ? 'System' : 'Custom'}</dd>
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
      setErr(e instanceof Error ? e.message : 'Could not create template');
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
            ← Back to templates
          </button>
          <div className="flex items-center gap-2">
            <NavIcon name="plusDocument" className="h-5 w-5 text-slate-500" />
            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">New email template</h2>
          </div>
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">Custom templates can use manual or automation triggers.</p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2.5 rounded-xl border border-slate-200/90 bg-slate-50 px-3.5 py-2 dark:border-slate-600 dark:bg-slate-800/60">
            <span className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</span>
            <span className="text-sm font-semibold text-slate-800 dark:text-slate-100">{enabled ? 'Active' : 'Inactive'}</span>
            <ToggleSwitch checked={enabled} onChange={setEnabled} label="Template active" />
          </div>
          <ButtonPrimary type="button" onClick={() => void submit()} disabled={!name.trim()}>
            Create template
          </ButtonPrimary>
        </div>
      </div>

      {err ? <p className="text-sm text-red-600 dark:text-red-400">{err}</p> : null}

      <div className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950/40">
        <div className="space-y-4">
          <label className="block">
            <span className={LB}>Template name</span>
            <input
              className={FT_INPUT}
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. Re-engagement after enrollment"
            />
          </label>
          <label className="block">
            <QuillField
              label="Description"
              value={description}
              onChange={(html) => setDescription(html)}
              placeholder="When to send this template (internal note)"
              minHeightPx={160}
            />
          </label>
          <div>
            <span className={LB}>Trigger Event</span>
            <div className="mt-1.5">
              <TriggerEventSelect value={event} onChange={setEvent} />
            </div>
            <p className={HELP}>Select an event to trigger this email, or “No event” for sequence / manual use.</p>
          </div>
          <label className="block">
            <span className={LB}>Send to</span>
            <input
              className={`${FT_INPUT} font-mono text-xs`}
              value={recipientTo}
              onChange={(e) => setRecipientTo(e.target.value)}
              placeholder="{{student_email}} · {{instructor_email}} · {{admin_email}}"
            />
            <p className={HELP}>Who receives this email — merge tags resolve when the message is sent.</p>
          </label>
          <label className="block">
            <span className={LB}>Subject line</span>
            <input
              className={FT_INPUT}
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              placeholder="e.g. [{{site_name}}] A quick note about {{course_title}}"
            />
          </label>
          <label className="block">
            <span className={LB}>Email body (HTML)</span>
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
