import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiError } from '../api/errors';

export type AsyncState<T> = {
  loading: boolean;
  data: T | null;
  error: unknown;
  refetch: () => void;
};

/**
 * Load async data with loading / error flags.
 * Uses a ref for `loader` so callers can pass an inline function without breaking the dependency array.
 */
export function useAsyncData<T>(loader: () => Promise<T>, deps: readonly unknown[]): AsyncState<T> {
  const loaderRef = useRef(loader);
  loaderRef.current = loader;

  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [tick, setTick] = useState(0);

  const refetch = useCallback(() => {
    setTick((t) => t + 1);
  }, []);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

    (async () => {
      try {
        const result = await loaderRef.current();
        if (!cancelled) {
          setData(result);
        }
      } catch (e) {
        if (!cancelled) {
          setError(e);
          setData(null);
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [tick, ...deps]);

  return { loading, data, error, refetch };
}

export function isApiError(e: unknown): e is ApiError {
  return e instanceof ApiError;
}
