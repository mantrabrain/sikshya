import { useCallback, useMemo, useState } from 'react';
import { formatShareableErrorReport, getErrorSummary } from '../../api/errors';

type Props = {
  error: unknown;
  onRetry?: () => void;
  title?: string;
};

/**
 * Human-readable error + copyable support bundle (Slack / email / ticket).
 */
export function ApiErrorPanel({ error, onRetry, title = 'Something went wrong' }: Props) {
  const report = useMemo(() => formatShareableErrorReport(error), [error]);
  const summary = useMemo(() => getErrorSummary(error), [error]);
  const [copied, setCopied] = useState(false);

  const handleCopy = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(report.fullText);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      setCopied(false);
    }
  }, [report.fullText]);

  return (
    <div
      className="rounded-xl border border-red-200 bg-red-50/90 p-4 text-red-950 shadow-sm"
      role="alert"
    >
      <h3 className="text-sm font-semibold text-red-900">{title}</h3>
      <p className="mt-1 text-sm text-red-800">{summary}</p>
      <p className="mt-2 text-xs text-red-700/90">
        Copy the report below and send it to Sikshya support so we can reproduce the issue.
      </p>
      <div className="mt-3 max-h-48 overflow-auto rounded-lg border border-red-200/80 bg-white p-3">
        <pre className="whitespace-pre-wrap break-all font-mono text-[11px] leading-relaxed text-slate-800">
          {report.fullText}
        </pre>
      </div>
      <div className="mt-3 flex flex-wrap gap-2">
        <button
          type="button"
          onClick={handleCopy}
          className="rounded-lg bg-red-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-900"
        >
          {copied ? 'Copied!' : 'Copy error report'}
        </button>
        {onRetry ? (
          <button
            type="button"
            onClick={onRetry}
            className="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-900 hover:bg-red-50"
          >
            Try again
          </button>
        ) : null}
      </div>
    </div>
  );
}
