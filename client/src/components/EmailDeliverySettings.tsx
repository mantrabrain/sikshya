import type { ReactNode } from 'react';
import { useCallback, useMemo, useState } from 'react';
import { getSikshyaApi } from '../api';
import { SIKSHYA_ENDPOINTS } from '../api/endpoints';
import { getErrorSummary } from '../api/errors';
import { NavIcon } from './NavIcon';
import { PlanUpgradeOverlay } from './PlanUpgradeOverlay';
import { PremiumGatedSurface } from './PremiumGatedSurface';
import { ButtonPrimary, LinkButtonPrimary } from './shared/buttons';
import { getLicensing, isFeatureEnabled } from '../lib/licensing';
import { appViewHref } from '../lib/appUrl';
import { useAddonEnabled } from '../hooks/useAddons';
import type { SettingsField, SettingsSection } from '../types/settingsSchema';
import type { SikshyaReactConfig } from '../types';

type Props = {
  config: SikshyaReactConfig;
  tabSchema: SettingsSection[];
  renderField: (f: SettingsField) => React.ReactNode;
};

function sectionIconName(raw?: string): string {
  const s = (raw || '').trim();
  const fa = s.replace(/^fas\s+fa-/, '').replace(/^fa-/, '');
  switch (fa) {
    case 'link':
      return 'tag';
    case 'folder-open':
    case 'folder':
      return 'course';
    case 'tags':
      return 'tag';
    case 'route':
      return 'layers';
    case 'cog':
    case 'cogs':
      return 'cog';
    case 'info-circle':
    case 'question-circle':
    case 'bell':
      return 'helpCircle';
    case 'shield-alt':
    case 'tools':
      return 'cog';
    case 'envelope':
    case 'paper-plane':
      return 'layers';
    case 'edit':
      return 'plusDocument';
    default:
      return fa || 'cog';
  }
}

function SectionShell(props: {
  title?: string;
  description?: string;
  icon?: string;
  children: ReactNode;
  className?: string;
}) {
  const { title, description, icon, children, className = '' } = props;
  return (
    <section
      className={`rounded-2xl border border-slate-200/80 bg-slate-50 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950/30 ${className}`.trim()}
    >
      {title ? (
        <div className="mb-5 flex items-start gap-3">
          <span className="mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            <NavIcon name={sectionIconName(icon)} className="h-5 w-5" />
          </span>
          <div className="min-w-0">
            <h3 className="text-sm font-semibold text-slate-900 dark:text-white">{title}</h3>
            {description ? (
              <p className="mt-1 text-xs leading-relaxed text-slate-400/90 dark:text-slate-500/80">{description}</p>
            ) : null}
          </div>
        </div>
      ) : null}
      {children}
    </section>
  );
}

function EmailAddonDisabledOverlay({ config }: { config: SikshyaReactConfig }) {
  const href = appViewHref(config, 'addons');
  return (
    <PremiumGatedSurface>
      <div className="pointer-events-auto mx-auto flex max-w-lg flex-col items-center gap-4 px-4 text-center">
        <p className="text-sm leading-relaxed text-white/95">
          Your plan includes professional email delivery. Enable the{' '}
          <strong className="font-semibold">Professional email delivery &amp; branded templates</strong> add-on to use SMTP and
          global HTML branding.
        </p>
        <LinkButtonPrimary href={href}>Open Addons</LinkButtonPrimary>
      </div>
    </PremiumGatedSurface>
  );
}

/**
 * Email → Delivery: addresses, SMTP, global HTML wrappers. Transactional copy lives under Email templates.
 */
export function EmailDeliverySettings(props: Props) {
  const { config, tabSchema, renderField } = props;
  const featureOn = isFeatureEnabled(config, 'email_advanced_customization');
  const emailAdvancedAddon = useAddonEnabled('email_advanced_customization');
  const advancedOk = featureOn && emailAdvancedAddon.enabled && !emailAdvancedAddon.loading;
  const lic = getLicensing(config);
  const addonsHref = useMemo(() => appViewHref(config, 'addons'), [config]);

  const [testTo, setTestTo] = useState('');
  const [testBusy, setTestBusy] = useState(false);
  const [testFeedback, setTestFeedback] = useState<{ tone: 'ok' | 'err'; text: string } | null>(null);

  const sendTestEmail = useCallback(async () => {
    setTestBusy(true);
    setTestFeedback(null);
    try {
      const res = await getSikshyaApi().post<{ success?: boolean; message?: string }>(
        SIKSHYA_ENDPOINTS.pro.emailAdvancedTestDelivery,
        { to: testTo.trim() ? testTo.trim() : undefined }
      );
      if (res.success) {
        setTestFeedback({ tone: 'ok', text: res.message || 'Test email sent.' });
      } else {
        setTestFeedback({ tone: 'err', text: res.message || 'Could not send test email.' });
      }
    } catch (e) {
      setTestFeedback({ tone: 'err', text: getErrorSummary(e) });
    } finally {
      setTestBusy(false);
    }
  }, [testTo]);

  const byKey = new Map<string, SettingsSection>();
  for (const sec of tabSchema) {
    const k = sec.section_key;
    if (k) {
      byKey.set(k, sec);
    }
  }
  const fieldsOf = (key: string) => byKey.get(key)?.fields ?? [];
  const smtpSection = byKey.get('email_smtp');
  const brandingSection = byKey.get('email_html_branding');

  const templatesHref = appViewHref(config, 'email-hub', { tab: 'templates' });

  return (
    <div className="w-full space-y-0">
      <div className="space-y-4 px-6">
        <p className="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
          Delivery sets <strong className="font-medium text-slate-700 dark:text-slate-200">who sends mail and how</strong>. Which
          transactional emails actually go out is controlled per template on the{' '}
          <span className="font-medium text-slate-700 dark:text-slate-200">Email templates</span> screen (enable/disable and
          content). That includes drip unlock emails (
          <span className="font-medium text-slate-700 dark:text-slate-200">Drip: lesson unlocked</span>,{' '}
          <span className="font-medium text-slate-700 dark:text-slate-200">Drip: course schedule unlocked</span>) — turning a
          template off does not disable Content drip, only the email.
        </p>

        <div className="flex flex-col gap-3 rounded-2xl border border-sky-200/80 bg-sky-50/90 p-4 dark:border-sky-900/40 dark:bg-sky-950/25 sm:flex-row sm:items-center sm:justify-between">
          <div className="min-w-0">
            <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Email templates</h3>
            <p className="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
              Welcome, enrollment, drip unlocks, completion, reminders — one list with per-template enable switches and editors.
            </p>
          </div>
          <LinkButtonPrimary href={templatesHref}>Open email templates</LinkButtonPrimary>
        </div>
      </div>

      {!advancedOk && !emailAdvancedAddon.loading ? (
        <div className="mx-6 mt-4 rounded-xl border border-amber-200/80 bg-amber-50/95 px-4 py-3 text-xs leading-relaxed text-amber-950 shadow-sm dark:border-amber-800/50 dark:bg-amber-950/35 dark:text-amber-50">
          {!featureOn ? (
            <>
              <span className="font-semibold text-amber-950 dark:text-amber-100">Growth or higher: </span>
              {lic?.isProActive ? (
                <>
                  Your license is on{' '}
                  <strong className="text-amber-950 dark:text-amber-100">{lic.siteTierLabel || lic.siteTier}</strong>. Custom SMTP
                  and global HTML wrappers unlock on <strong className="text-amber-950 dark:text-amber-100">Growth</strong> or{' '}
                  <strong className="text-amber-950 dark:text-amber-100">Scale</strong> — Starter includes core email only.
                </>
              ) : (
                <>
                  Install Sikshya Pro and choose <strong className="text-amber-950 dark:text-amber-100">Growth</strong> or{' '}
                  <strong className="text-amber-950 dark:text-amber-100">Scale</strong> to edit SMTP and branded HTML templates.
                </>
              )}
            </>
          ) : (
            <>
              <span className="font-semibold text-amber-950 dark:text-amber-100">Add-on: </span>
              Turn on <strong className="text-amber-950 dark:text-amber-100">Professional email delivery &amp; branded templates</strong>{' '}
              on the{' '}
              <a
                href={addonsHref}
                className="font-semibold text-amber-950 underline underline-offset-2 hover:text-amber-900 dark:text-amber-100 dark:hover:text-white"
              >
                Addons
              </a>{' '}
              screen to use SMTP and global HTML wrappers.
            </>
          )}
        </div>
      ) : null}

      <div className="mt-6 w-full space-y-6 px-6">
        <SectionShell
          title={byKey.get('email_config')?.title || 'Email Configuration'}
          description={byKey.get('email_config')?.description}
          icon={byKey.get('email_config')?.icon}
        >
          <div className="grid gap-6 lg:grid-cols-2">{fieldsOf('email_config').map(renderField)}</div>
        </SectionShell>

        <div className="relative isolate min-h-[280px] overflow-hidden rounded-2xl">
          <div
            className={`rounded-2xl border border-slate-200/80 bg-slate-100/80 p-6 dark:border-slate-800 dark:bg-slate-900/40 ${
              advancedOk ? '' : 'opacity-[0.65]'
            }`}
          >
            <div className="mb-5 flex items-start gap-3">
              <span className="mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg bg-white text-slate-600 shadow-sm dark:bg-slate-800 dark:text-slate-300">
                <NavIcon name={sectionIconName(smtpSection?.icon)} className="h-5 w-5" />
              </span>
              <div className="min-w-0">
                <h3 className="text-sm font-semibold text-slate-900 dark:text-white">
                  {smtpSection?.title || 'SMTP Settings (Optional)'}
                </h3>
                {smtpSection?.description ? (
                  <p className="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{smtpSection.description}</p>
                ) : null}
              </div>
            </div>
            <div className="grid gap-6 lg:grid-cols-2">{fieldsOf('email_smtp').map(renderField)}</div>
            {advancedOk ? (
              <div className="mt-6 border-t border-slate-200/90 pt-6 dark:border-slate-700/90">
                <h4 className="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">Test delivery</h4>
                <p className="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-500">
                  Sends a short HTML message using your From / Reply-To settings, optional SMTP when enabled, and branded header and
                  footer when configured.
                </p>
                <div className="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                  <label className="min-w-0 flex-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                    Recipient (optional)
                    <input
                      type="email"
                      name="sikshya_email_test_to"
                      autoComplete="email"
                      value={testTo}
                      onChange={(e) => setTestTo(e.target.value)}
                      placeholder="Defaults to your WordPress account email"
                      className="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none ring-brand-500/0 transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                    />
                  </label>
                  <ButtonPrimary type="button" className="shrink-0" disabled={testBusy} onClick={() => void sendTestEmail()}>
                    {testBusy ? 'Sending…' : 'Send test email'}
                  </ButtonPrimary>
                </div>
                {testFeedback ? (
                  <p
                    className={
                      testFeedback.tone === 'ok'
                        ? 'mt-3 text-xs text-emerald-700 dark:text-emerald-400'
                        : 'mt-3 text-xs text-red-600 dark:text-red-400'
                    }
                    role={testFeedback.tone === 'err' ? 'alert' : undefined}
                  >
                    {testFeedback.text}
                  </p>
                ) : null}
              </div>
            ) : null}
          </div>
          {!advancedOk && !emailAdvancedAddon.loading ? (
            !featureOn ? (
              <PlanUpgradeOverlay
                config={config}
                featureId="email_advanced_customization"
                featureTitle="SMTP & branded HTML email templates"
                description="Route mail through your provider with SMTP and unlock global HTML wrappers around every transactional email."
              />
            ) : (
              <EmailAddonDisabledOverlay config={config} />
            )
          ) : null}
        </div>

        <div className="relative isolate min-h-[320px] w-full overflow-hidden">
          <div className={`rounded-2xl ${advancedOk ? '' : 'opacity-[0.65]'}`}>
            <SectionShell
              title={brandingSection?.title || 'Global HTML Branding'}
              description={
                brandingSection?.description || 'Optional HTML that wraps every Sikshya email — requires Growth or higher.'
              }
              icon={brandingSection?.icon}
            >
              <div className="grid gap-6 lg:grid-cols-2">{fieldsOf('email_html_branding').map(renderField)}</div>
            </SectionShell>
          </div>
          {!advancedOk && !emailAdvancedAddon.loading ? (
            !featureOn ? (
              <PlanUpgradeOverlay
                config={config}
                featureId="email_advanced_customization"
                featureTitle="SMTP & branded HTML email templates"
                description="Edit global header and footer HTML for every transactional email."
              />
            ) : (
              <EmailAddonDisabledOverlay config={config} />
            )
          ) : null}
        </div>
      </div>
    </div>
  );
}
