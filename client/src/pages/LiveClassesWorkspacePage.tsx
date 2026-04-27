import { useAddonEnabled } from '../hooks/useAddons';
import { appViewHref } from '../lib/appUrl';
import { isFeatureEnabled, resolveGatedWorkspaceMode } from '../lib/licensing';
import { EmbeddableShell } from '../components/shared/EmbeddableShell';
import { GatedFeatureWorkspace } from '../components/GatedFeatureWorkspace';
import { AddonSettingsPage } from './AddonSettingsPage';
import type { SikshyaReactConfig } from '../types';

export function LiveClassesWorkspacePage(props: {
  config: SikshyaReactConfig;
  title: string;
  embedded?: boolean;
}) {
  const { config, title, embedded } = props;
  const featureOk = isFeatureEnabled(config, 'live_classes');
  const addon = useAddonEnabled('live_classes');
  const mode = resolveGatedWorkspaceMode(featureOk, addon.enabled, addon.loading);

  const inner = (
    <GatedFeatureWorkspace
      mode={mode}
      featureId="live_classes"
      config={config}
      featureTitle="Live classes"
      featureDescription="Store join links, providers, and schedules on lessons so learners always know where to go — with optional course-level promos, calendar sync, and global defaults."
      previewVariant="cards"
      addonEnableTitle="Live classes are not enabled"
      addonEnableDescription="Enable the add-on to tune join-link behaviour, session defaults, and where upcoming sessions appear across the storefront and learner experience."
      canEnable={Boolean(addon.licenseOk)}
      enableBusy={addon.loading}
      onEnable={() => void addon.enable()}
    >
      {mode === 'full' ? (
        <div className="space-y-8">
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
            <h2 className="text-base font-semibold text-slate-900 dark:text-white">How teams use this</h2>
            <ul className="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600 dark:text-slate-300">
              <li>Create the meeting in Zoom / Meet / Teams, then paste the join URL on each live lesson.</li>
              <li>Set a start time so Sikshya can surface “Upcoming live sessions” on the course page and learner calendar.</li>
              <li>Use the optional session title when the calendar row should read differently from the lesson name.</li>
              <li>Turn off course promos (but keep lesson joins) from the course builder when marketing surfaces should stay minimal.</li>
            </ul>
          </section>
          <AddonSettingsPage
            embedded
            config={config}
            title="Live class defaults"
            addonId="live_classes"
            subtitle="Join-link behaviour, schedule limits, and learner-facing hints."
            featureTitle="Live class settings"
            featureDescription="Every toggle below changes real behaviour on the storefront, learn shell, cart, or account dashboard."
            relatedCoreSettingsTab="lessons"
            relatedCoreSettingsLabel="Lessons"
            nextSteps={[
              {
                label: 'Edit a live lesson',
                href: appViewHref(config, 'content-library', { tab: 'lessons' }),
                description: 'Pick “Live class” as the lesson type, then add URL, provider, and schedule.',
              },
              {
                label: 'Course builder',
                href: appViewHref(config, 'courses'),
                description: 'Optional: hide live promos for a specific course while keeping join buttons on lessons.',
              },
            ]}
          />
        </div>
      ) : null}
    </GatedFeatureWorkspace>
  );

  if (embedded) {
    return (
      <EmbeddableShell embedded config={config} title={title}>
        {inner}
      </EmbeddableShell>
    );
  }

  return inner;
}
