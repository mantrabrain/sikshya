import type { ReactNode } from 'react';
import { ApiErrorPanel } from './ApiErrorPanel';

type Props = {
  loading: boolean;
  error: unknown;
  onRetry?: () => void;
  skeleton: ReactNode;
  children: ReactNode;
};

/**
 * Standard loading → skeleton, error → shareable panel, success → children.
 * Pass a skeleton that includes its own card/chrome when needed.
 */
export function AsyncBoundary({ loading, error, onRetry, skeleton, children }: Props) {
  if (loading) {
    return <>{skeleton}</>;
  }
  if (error) {
    return <ApiErrorPanel error={error} onRetry={onRetry} />;
  }
  return <>{children}</>;
}
