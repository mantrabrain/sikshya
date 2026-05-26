import type { ReactNode } from 'react';
import { AddonEnablePanel } from './AddonEnablePanel';
import { FeaturePreviewSkeleton } from './FeaturePreviewSkeleton';
import { PlanUpgradeOverlay } from './PlanUpgradeOverlay';
import { PREMIUM_GATE_VIEWPORT_MIN_H, PremiumGatedSurface } from './PremiumGatedSurface';
import { sikshyaPricingUrl } from '../lib/upgradeUrl';
import type { GatedWorkspaceMode, SikshyaReactConfig } from '../types';

type PreviewVariant = 'form' | 'table' | 'cards' | 'generic';

type Props = {
  mode: GatedWorkspaceMode;
  featureId: string;
  config: SikshyaReactConfig;
  featureTitle: string;
  featureDescription: string;
  previewVariant?: PreviewVariant;
  renderPreview?: () => ReactNode;
  addonEnableTitle: string;
  addonEnableDescription: string;
  canEnable: boolean;
  enableBusy: boolean;
  onEnable: () => Promise<void>;
  addonError?: unknown;
  children: ReactNode;
};

/**
 * Single wrapper for gated admin pages (plan lock vs addon off vs full).
 * @see docs/AI_ADDON_PREMIUM_UX_IMPLEMENTATION_BLUEPRINT.md Part D.2
 */
export function GatedFeatureWorkspace(props: Props) {
  const {
    mode,
    featureId,
    config,
    featureTitle,
    featureDescription,
    previewVariant,
    renderPreview,
    addonEnableTitle,
    addonEnableDescription,
    canEnable,
    enableBusy,
    onEnable,
    addonError,
    children,
  } = props;

  if (mode === 'full') {
    return <>{children}</>;
  }

  // Always route the addon-off "Upgrade" CTA to the canonical Sikshya LMS
  // pricing page with UTM tracking. Carrying the featureId in utm_term lets
  // mantrabrain.com analytics attribute clicks back to the specific feature
  // gate that triggered them.
  const upgradeUrl = sikshyaPricingUrl('addon-enable-upgrade', featureId);

  return (
    <div className={`relative isolate w-full max-w-none ${PREMIUM_GATE_VIEWPORT_MIN_H}`}>
      <div
        className={`pointer-events-none select-none opacity-[0.72] ${PREMIUM_GATE_VIEWPORT_MIN_H}`}
        aria-hidden
      >
        {renderPreview ? renderPreview() : <FeaturePreviewSkeleton variant={previewVariant} />}
      </div>

      {mode === 'locked-plan' ? (
        <PlanUpgradeOverlay
          config={config}
          featureId={featureId}
          featureTitle={featureTitle}
          description={featureDescription}
        />
      ) : null}

      {mode === 'addon-off' ? (
        <PremiumGatedSurface>
          <AddonEnablePanel
            variant="premium"
            title={addonEnableTitle}
            description={addonEnableDescription}
            canEnable={canEnable}
            enableBusy={enableBusy}
            onEnable={onEnable}
            upgradeUrl={upgradeUrl}
            error={addonError}
          />
        </PremiumGatedSurface>
      ) : null}

      {mode === 'pending-addon' ? (
        <div className="absolute inset-0 z-20 flex min-h-full w-full flex-col items-center justify-center bg-white/85 p-6 backdrop-blur-sm dark:bg-slate-950/85">
          <div className="rounded-xl border border-slate-200 bg-white px-5 py-4 text-sm font-medium text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            Loading add-on status…
          </div>
        </div>
      ) : null}
    </div>
  );
}
