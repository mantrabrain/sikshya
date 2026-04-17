import { ApiErrorPanel } from './shared/ApiErrorPanel';
import { ButtonPrimary } from './shared/buttons';

export function AddonEnablePanel(props: {
  title: string;
  description: string;
  canEnable: boolean;
  enableLabel?: string;
  enableBusy?: boolean;
  onEnable: () => void;
  upgradeUrl?: string;
  error?: unknown;
}) {
  const { title, description, canEnable, enableLabel, enableBusy, onEnable, upgradeUrl, error } = props;

  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <div className="text-sm font-semibold text-slate-900 dark:text-white">{title}</div>
      <div className="mt-1 text-sm text-slate-600 dark:text-slate-400">{description}</div>

      {error ? (
        <div className="mt-4">
          <ApiErrorPanel error={error} title="Could not update addon" onRetry={() => void 0} />
        </div>
      ) : null}

      <div className="mt-5 flex flex-wrap items-center gap-2">
        {canEnable ? (
          <ButtonPrimary type="button" disabled={enableBusy} onClick={onEnable}>
            {enableBusy ? 'Enabling…' : enableLabel || 'Enable addon'}
          </ButtonPrimary>
        ) : upgradeUrl ? (
          <a
            href={upgradeUrl}
            target="_blank"
            rel="noreferrer noopener"
            className="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
          >
            Upgrade to unlock
          </a>
        ) : null}
      </div>
    </div>
  );
}

