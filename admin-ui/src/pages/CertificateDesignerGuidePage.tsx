import { useCallback, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { AppShell } from '../components/AppShell';
import { AddonEnablePanel } from '../components/AddonEnablePanel';
import { FeatureUpsell } from '../components/FeatureUpsell';
import { ApiErrorPanel } from '../components/shared/ApiErrorPanel';
import { ButtonPrimary } from '../components/shared/buttons';
import { useAsyncData } from '../hooks/useAsyncData';
import { useAddonEnabled } from '../hooks/useAddons';
import { appViewHref } from '../lib/appUrl';
import { getLicensing, isFeatureEnabled } from '../lib/licensing';
import type { NavItem, SikshyaReactConfig } from '../types';

type AdvancedInfo = {
  ok?: boolean;
  merge_fields?: string[];
  document_url_template?: string;
  verify_url_template?: string;
  qr_note?: string;
};

async function copyField(text: string) {
  try {
    await navigator.clipboard.writeText(text);
  } catch {
    // ignore — user can select manually
  }
}

export function CertificateDesignerGuidePage(props: { config: SikshyaReactConfig; title: string }) {
  const { config, title } = props;
  const lic = getLicensing(config);
  const featureOk = isFeatureEnabled(config, 'certificates_advanced');
  const addon = useAddonEnabled('certificates_advanced');
  const enabled = featureOk && Boolean(addon.enabled);
  const [copied, setCopied] = useState<string | null>(null);

  const loader = useCallback(async () => {
    if (!enabled) {
      return { ok: true, merge_fields: [] as string[] } as AdvancedInfo;
    }
    return getSikshyaApi().get<AdvancedInfo>(SIKSHYA_ENDPOINTS.pro.advancedCertificates);
  }, [enabled]);

  const data = useAsyncData(loader, [enabled]);

  const templatesHref = appViewHref(config, 'certificates');
  const issuedHref = appViewHref(config, 'issued-certificates');

  return (
    <AppShell
      page={config.page}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      title={title}
      subtitle="Plain-language steps for beautiful certificates — no code required."
    >
      <div className="mx-auto max-w-3xl space-y-8">
        {!featureOk ? (
          <FeatureUpsell
            title="Advanced certificates"
            description="Give learners a polished PDF-style page, automatic serial numbers, QR codes, and a public “is this real?” link — all from a simple template."
            licensing={lic}
          />
        ) : !enabled ? (
          <AddonEnablePanel
            title="Enable advanced certificates"
            description="Turn on the addon to unlock merge fields, QR codes, and verification links for newly issued certificates."
            canEnable={Boolean(addon.licenseOk)}
            enableBusy={addon.loading}
            onEnable={() => void addon.enable()}
            upgradeUrl={lic.upgradeUrl}
            error={addon.error}
          />
        ) : (
          <>
            <section className="rounded-2xl border border-emerald-200 bg-emerald-50/90 p-5 text-sm leading-relaxed text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/35 dark:text-emerald-50">
              <h2 className="text-base font-semibold text-emerald-950 dark:text-emerald-50">How it works (3 steps)</h2>
              <ol className="mt-3 list-decimal space-y-3 pl-5">
                <li>
                  Open <a className="font-semibold underline underline-offset-2" href={templatesHref}>Certificate templates</a>{' '}
                  and edit a template like a normal WordPress page — headings, colors, and your logo all work.
                </li>
                <li>
                  Drop <strong>merge fields</strong> (short codes) into the content where you want the learner’s name,
                  course title, or a QR code. Tap “Copy” beside any field below so you never mistype one letter.
                </li>
                <li>
                  When someone completes the course, Sikshya fills those fields automatically and stores a{' '}
                  <strong>verification link</strong> you can see under{' '}
                  <a className="font-semibold underline underline-offset-2" href={issuedHref}>Issued certificates</a>.
                </li>
              </ol>
            </section>

            {data.error ? (
              <ApiErrorPanel error={data.error} title="Could not load tips" onRetry={() => data.refetch()} />
            ) : data.loading ? (
              <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900">
                Loading tips…
              </div>
            ) : (
              <>
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                  <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Merge fields (copy & paste)</h2>
                  <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    Click <strong>Copy</strong>, then click inside your template editor and paste. Each field is replaced
                    with real learner data when the certificate is issued.
                  </p>
                  <ul className="mt-5 space-y-3">
                    {(data.data?.merge_fields ?? []).map((f) => (
                      <li
                        key={f}
                        className="flex flex-col gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950/40"
                      >
                        <code className="font-mono text-xs text-slate-900 dark:text-slate-100">{f}</code>
                        <button
                          type="button"
                          className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                          onClick={() => {
                            void copyField(f);
                            setCopied(f);
                            window.setTimeout(() => setCopied(null), 2000);
                          }}
                        >
                          {copied === f ? 'Copied!' : 'Copy'}
                        </button>
                      </li>
                    ))}
                  </ul>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                  <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Links learners and employers use</h2>
                  <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    You do <strong>not</strong> build these by hand — Sikshya fills in the secret code per certificate.
                    These examples show the shape of the URL only.
                  </p>
                  <dl className="mt-4 space-y-4 text-sm">
                    <div>
                      <dt className="font-medium text-slate-800 dark:text-slate-200">Printable page</dt>
                      <dd className="mt-1 break-all rounded-lg bg-slate-50 p-3 font-mono text-xs text-slate-700 dark:bg-slate-950 dark:text-slate-300">
                        {data.data?.document_url_template ?? '—'}
                      </dd>
                    </div>
                    <div>
                      <dt className="font-medium text-slate-800 dark:text-slate-200">“Is this certificate real?” page</dt>
                      <dd className="mt-1 break-all rounded-lg bg-slate-50 p-3 font-mono text-xs text-slate-700 dark:bg-slate-950 dark:text-slate-300">
                        {data.data?.verify_url_template ?? '—'}
                      </dd>
                    </div>
                  </dl>
                  {data.data?.qr_note ? (
                    <p className="mt-4 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{data.data.qr_note}</p>
                  ) : null}
                </section>

                <section className="rounded-2xl border border-sky-200 bg-sky-50/80 p-5 text-sm text-sky-950 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
                  <strong className="font-semibold">Tip for first-time designers:</strong> start from one heading, one
                  paragraph with <code className="rounded bg-white/80 px-1 font-mono text-xs dark:bg-slate-900">{'{{learner_name}}'}</code>, and{' '}
                  <code className="rounded bg-white/80 px-1 font-mono text-xs dark:bg-slate-900">{'{{qr_image}}'}</code> near the bottom. Preview
                  by issuing yourself a test certificate from a short demo course.
                </section>
              </>
            )}
          </>
        )}
      </div>
    </AppShell>
  );
}
