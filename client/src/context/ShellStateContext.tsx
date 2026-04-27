import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import { getConfig, normalizeLicensing, normalizeShellAlerts } from '../config/env';
import { useAdminRouting } from '../lib/adminRouting';
import type { ShellAlert, SikshyaLicensing } from '../types';

type ShellMetaResponse = {
  shellAlerts?: unknown;
  licensing?: unknown;
  proVersion?: unknown;
  proPluginVersion?: unknown;
};

type ShellStateValue = {
  shellAlerts: ShellAlert[];
  licensing: SikshyaLicensing | undefined;
  /** Pro add-on semver whenever the Pro plugin is loaded. */
  proPluginVersion: string;
  refreshShell: () => Promise<void>;
};

const ShellStateContext = createContext<ShellStateValue | null>(null);

export function useShellState(): ShellStateValue {
  const ctx = useContext(ShellStateContext);
  if (!ctx) {
    throw new Error('useShellState must be used within ShellStateProvider');
  }
  return ctx;
}

export function ShellStateProvider({ children }: { children: ReactNode }) {
  const { route } = useAdminRouting();

  const initial = useMemo(() => {
    const c = getConfig();
    return {
      shellAlerts: c.shellAlerts ?? [],
      licensing: c.licensing,
      proPluginVersion: typeof c.proPluginVersion === 'string' && c.proPluginVersion.trim() !== '' ? c.proPluginVersion.trim() : '',
    };
  }, []);

  const [shellAlerts, setShellAlerts] = useState<ShellAlert[]>(initial.shellAlerts);
  const [licensing, setLicensing] = useState<SikshyaLicensing | undefined>(initial.licensing);
  const [proPluginVersion, setProPluginVersion] = useState(initial.proPluginVersion);

  const refreshShell = useCallback(async () => {
    const view =
      typeof route.page === 'string' && route.page.trim() !== '' ? route.page.trim() : 'dashboard';
    try {
      const res = await getSikshyaApi().get<ShellMetaResponse>(SIKSHYA_ENDPOINTS.admin.shellMeta(view));
      const nextAlerts = normalizeShellAlerts(res.shellAlerts);
      const nextLic = normalizeLicensing(res.licensing);
      const nextPv =
        typeof res.proVersion === 'string' && res.proVersion.trim() !== '' ? res.proVersion.trim() : '';
      const nextPluginPv =
        typeof res.proPluginVersion === 'string' && res.proPluginVersion.trim() !== ''
          ? res.proPluginVersion.trim()
          : '';

      setShellAlerts(nextAlerts);
      setLicensing(nextLic);
      setProPluginVersion(nextPluginPv);

      if (typeof window !== 'undefined' && window.sikshyaReact) {
        Object.assign(window.sikshyaReact, {
          shellAlerts: res.shellAlerts ?? [],
          licensing: res.licensing ?? window.sikshyaReact.licensing,
          proVersion: nextPv,
          proPluginVersion: nextPluginPv,
        });
      }
    } catch {
      /* Non-fatal: shell strip may fail if REST is blocked; UI keeps last known state. */
    }
  }, [route.page]);

  const value = useMemo(
    () => ({
      shellAlerts,
      licensing,
      proPluginVersion,
      refreshShell,
    }),
    [shellAlerts, licensing, proPluginVersion, refreshShell]
  );

  return <ShellStateContext.Provider value={value}>{children}</ShellStateContext.Provider>;
}
