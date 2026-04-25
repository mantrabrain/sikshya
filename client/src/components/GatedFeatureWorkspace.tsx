import type { ReactNode } from 'react';
import { AddonEnablePanel } from './AddonEnablePanel';
import { FeaturePreviewSkeleton } from './FeaturePreviewSkeleton';
import { PlanUpgradeOverlay } from './PlanUpgradeOverlay';
import { PREMIUM_GATE_VIEWPORT_MIN_H, PremiumGatedSurface } from './PremiumGatedSurface';
import { getLicensing } from '../lib/licensing';
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
  onEnable: () => void;
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

  const lic = getLicensing(config);
  const upgradeUrl = lic?.upgradeUrl;

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
        <div className="absolute inset-0 z-20 flex min-h-full w-full flex-col items-center justify-center bg-gradient-to-b from-amber-50/95 via-white/90 to-amber-100/80 p-6 backdrop-blur-sm dark:from-amber-950/90 dark:via-stone-950/85 dark:to-amber-950/80">
          <div className="rounded-2xl border border-amber-300/80 bg-gradient-to-r from-amber-50 to-yellow-50 px-5 py-4 text-sm font-semibold text-amber-950 shadow-lg shadow-amber-900/10 ring-1 ring-amber-200/60 dark:border-amber-700/50 dark:from-amber-950/80 dark:to-stone-900 dark:text-amber-100 dark:ring-amber-800/40">
            Loading add-on status…
          </div>
        </div>
      ) : null}
    </div>
  );
}
