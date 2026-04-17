import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { SikshyaReactConfig } from '../types';
import { appViewHref } from './appUrl';

export type AdminRoute = {
  page: string;
  query: Record<string, string>;
};

function isModifiedClick(e: MouseEvent): boolean {
  // Respect open-in-new-tab, context menu, etc.
  return !!(e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0);
}

function closestAnchor(el: EventTarget | null): HTMLAnchorElement | null {
  let node = el as HTMLElement | null;
  while (node) {
    if (node instanceof HTMLAnchorElement) return node;
    node = node.parentElement;
  }
  return null;
}

function isSameOrigin(url: URL): boolean {
  return url.origin === window.location.origin;
}

function isSikshyaAdminUrl(url: URL): boolean {
  // We keep this intentionally narrow: only admin.php?page=sikshya routes belong to the React shell.
  if (url.pathname.split('/').pop() !== 'admin.php') return false;
  return url.searchParams.get('page') === 'sikshya';
}

export function parseAdminRoute(baseConfig: SikshyaReactConfig, href?: string): AdminRoute {
  const url = new URL(href || window.location.href, window.location.href);

  if (!isSikshyaAdminUrl(url)) {
    return { page: baseConfig.page || 'dashboard', query: baseConfig.query || {} };
  }

  const page = (url.searchParams.get('view') || baseConfig.page || 'dashboard').trim() || 'dashboard';
  const query: Record<string, string> = {};
  url.searchParams.forEach((v, k) => {
    if (k === 'page' || k === 'view') return;
    query[k] = v;
  });

  return { page, query };
}

type Ctx = {
  route: AdminRoute;
  navigateView: (view: string, extra?: Record<string, string>, opts?: { replace?: boolean }) => void;
  navigateHref: (href: string, opts?: { replace?: boolean }) => void;
};

const AdminRoutingContext = createContext<Ctx | null>(null);

export function useAdminRouting(): Ctx {
  const ctx = useContext(AdminRoutingContext);
  if (!ctx) {
    throw new Error('useAdminRouting must be used within AdminRoutingProvider');
  }
  return ctx;
}

export function AdminRoutingProvider({
  baseConfig,
  children,
}: {
  baseConfig: SikshyaReactConfig;
  children: React.ReactNode;
}) {
  const [route, setRoute] = useState<AdminRoute>(() => parseAdminRoute(baseConfig));

  const navigateHref = useCallback(
    (href: string, opts?: { replace?: boolean }) => {
      const nextRoute = parseAdminRoute(baseConfig, href);
      const nextUrl = new URL(href, window.location.href);

      if (opts?.replace) {
        window.history.replaceState({}, '', nextUrl.href);
      } else {
        window.history.pushState({}, '', nextUrl.href);
      }
      setRoute(nextRoute);
    },
    [baseConfig]
  );

  const navigateView = useCallback(
    (view: string, extra?: Record<string, string>, opts?: { replace?: boolean }) => {
      const href = appViewHref(baseConfig, view, extra || {});
      navigateHref(href, opts);
    },
    [baseConfig, navigateHref]
  );

  useEffect(() => {
    const onPopState = () => setRoute(parseAdminRoute(baseConfig));
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, [baseConfig]);

  useEffect(() => {
    // Intercept in-app clicks so navigation stays SPA, but only for Sikshya React routes.
    const onClick = (e: MouseEvent) => {
      if (e.defaultPrevented || isModifiedClick(e)) return;

      const a = closestAnchor(e.target);
      if (!a) return;
      if (a.hasAttribute('download')) return;
      if (a.getAttribute('target') && a.getAttribute('target') !== '_self') return;

      const href = a.getAttribute('href');
      if (!href || href.startsWith('#')) return;

      let url: URL;
      try {
        url = new URL(href, window.location.href);
      } catch {
        return;
      }

      if (!isSameOrigin(url)) return;
      if (!isSikshyaAdminUrl(url)) return;
      if (url.href === window.location.href) return;

      e.preventDefault();
      navigateHref(url.href);
    };

    document.addEventListener('click', onClick, true);
    return () => document.removeEventListener('click', onClick, true);
  }, [navigateHref]);

  const value = useMemo<Ctx>(() => ({ route, navigateView, navigateHref }), [route, navigateView, navigateHref]);

  return <AdminRoutingContext.Provider value={value}>{children}</AdminRoutingContext.Provider>;
}

