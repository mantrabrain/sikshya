import { useCallback, useEffect, useMemo, useState } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';

export type AddonTier = 'free' | 'starter' | 'pro' | 'scale';

export type AddonRow = {
  id: string;
  label: string;
  description: string;
  tier: AddonTier;
  group: string;
  dependencies: string[];
  enabled: boolean;
  licenseOk: boolean;
};

export type AddonsResponse = {
  success: boolean;
  addons: AddonRow[];
  enabled: string[];
  licensing?: { isProActive?: boolean; siteTier?: string; upgradeUrl?: string };
};

let cache: AddonsResponse | null = null;
let inFlight: Promise<AddonsResponse> | null = null;

async function fetchAddons(): Promise<AddonsResponse> {
  if (cache) return cache;
  if (inFlight) return inFlight;
  inFlight = getSikshyaApi()
    .get<AddonsResponse>(SIKSHYA_ENDPOINTS.admin.addons)
    .then((res) => {
      cache = res;
      inFlight = null;
      return res;
    })
    .catch((e) => {
      inFlight = null;
      throw e;
    });
  return inFlight;
}

export function invalidateAddonsCache() {
  cache = null;
  inFlight = null;
}

export function useAddonsCatalog() {
  const [data, setData] = useState<AddonsResponse | null>(cache);
  const [loading, setLoading] = useState(!cache);
  const [error, setError] = useState<unknown>(null);

  const refetch = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      invalidateAddonsCache();
      const res = await fetchAddons();
      setData(res);
    } catch (e) {
      setError(e);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (cache) return;
    void (async () => {
      setLoading(true);
      setError(null);
      try {
        const res = await fetchAddons();
        setData(res);
      } catch (e) {
        setError(e);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  return { data, loading, error, refetch };
}

export function useAddonEnabled(addonId: string): {
  enabled: boolean | null;
  licenseOk: boolean | null;
  loading: boolean;
  error: unknown;
  enable: () => Promise<void>;
  disable: () => Promise<void>;
  refetch: () => Promise<void>;
} {
  const { data, loading, error, refetch } = useAddonsCatalog();

  const addon = useMemo(() => data?.addons?.find((a) => a.id === addonId) ?? null, [data, addonId]);

  const enable = useCallback(async () => {
    await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.addonsEnable(addonId), {});
    invalidateAddonsCache();
    await refetch();
  }, [addonId, refetch]);

  const disable = useCallback(async () => {
    await getSikshyaApi().post(SIKSHYA_ENDPOINTS.admin.addonsDisable(addonId), {});
    invalidateAddonsCache();
    await refetch();
  }, [addonId, refetch]);

  return {
    enabled: addon ? Boolean(addon.enabled) : data ? false : null,
    licenseOk: addon ? Boolean(addon.licenseOk) : data ? false : null,
    loading,
    error,
    enable,
    disable,
    refetch,
  };
}

