import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { getConfig } from '../config/env';
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

/**
 * Whether `href` points at this site's Sikshya React shell (SPA in-app navigation).
 * Used by shell notices so links work even if the delegated document listener misses the click.
 */
export function isSikshyaReactAdminHref(href: string): boolean {
  const h = href.trim();
  if (!h || h.startsWith('#')) return false;
  try {
    const url = new URL(href, window.location.href);
    if (!isSameOrigin(url)) return false;
    return isSikshyaAdminUrl(url);
  } catch {
    return false;
  }
}

function baseRouteFields(baseConfig: SikshyaReactConfig | null | undefined): { page: string; query: Record<string, string> } {
  const rawPage = baseConfig?.page;
  const page = typeof rawPage === 'string' && rawPage.trim() !== '' ? rawPage.trim() : 'dashboard';
  const rawQuery = baseConfig?.query;
  const query =
    rawQuery && typeof rawQuery === 'object' && !Array.isArray(rawQuery)
      ? (rawQuery as Record<string, string>)
      : {};
  return { page, query };
}

export function parseAdminRoute(baseConfig: SikshyaReactConfig | null | undefined, href?: string): AdminRoute {
  const { page: basePage, query: baseQuery } = baseRouteFields(baseConfig);
  const url = new URL(href || window.location.href, window.location.href);

  if (!isSikshyaAdminUrl(url)) {
    return { page: basePage || 'dashboard', query: baseQuery };
  }

  const page = (url.searchParams.get('view') || basePage || 'dashboard').trim() || 'dashboard';
  const query: Record<string, string> = {};
  url.searchParams.forEach((v, k) => {
    if (k === 'page' || k === 'view') return;
    query[k] = v;
  });

  return { page, query };
}

/**
 * Returns a non-empty message to block navigation (used to prompt the user),
 * or `null` to let navigation proceed. Pages register a blocker while they
 * have unsaved work — clicking any in-app link (or calling navigateHref) then
 * surfaces the user's preferred message before throwing away their edits.
 */
export type NavigationBlocker = () => string | null;

type Ctx = {
  route: AdminRoute;
  navigateView: (view: string, extra?: Record<string, string>, opts?: { replace?: boolean }) => void;
  navigateHref: (href: string, opts?: { replace?: boolean }) => void;
  /** Register a blocker; returns the unregister function. */
  registerNavigationBlocker: (blocker: NavigationBlocker) => () => void;
};

const AdminRoutingContext = createContext<Ctx | null>(null);

export function useAdminRouting(): Ctx {
  const ctx = useContext(AdminRoutingContext);
  if (!ctx) {
    // Defensive fallback: some admin UI pieces may be rendered outside the SPA provider
    // (or in rare cases a stale module graph can cause context mismatch).
    // In that scenario we still want basic navigation to work via full page loads.
    const base = getConfig();
    const route = parseAdminRoute(base);
    const navigateHref: Ctx['navigateHref'] = (href, opts) => {
      if (opts?.replace) {
        window.location.replace(href);
      } else {
        window.location.href = href;
      }
    };
    const navigateView: Ctx['navigateView'] = (view, extra, opts) => {
      navigateHref(appViewHref(base, view, extra || {}), opts);
    };
    return {
      route,
      navigateHref,
      navigateView,
      registerNavigationBlocker: () => () => undefined,
    };
  }
  return ctx;
}

export function AdminRoutingProvider({
  baseConfig,
  children,
}: {
  baseConfig: SikshyaReactConfig | null | undefined;
  children: React.ReactNode;
}) {
  const safeBaseConfig = baseConfig ?? getConfig();
  const [route, setRoute] = useState<AdminRoute>(() => parseAdminRoute(safeBaseConfig));
  const blockersRef = useRef<Set<NavigationBlocker>>(new Set());

  const registerNavigationBlocker = useCallback((blocker: NavigationBlocker) => {
    blockersRef.current.add(blocker);
    return () => {
      blockersRef.current.delete(blocker);
    };
  }, []);

  /**
   * Walks every registered blocker. The first one that returns a non-empty
   * string triggers a native confirm with that message; returning `false`
   * aborts navigation entirely.
   */
  const passesBlockers = useCallback(() => {
    for (const blocker of blockersRef.current) {
      const message = blocker();
      if (message) {
        // eslint-disable-next-line no-alert
        const proceed = window.confirm(message);
        if (!proceed) {
          return false;
        }
      }
    }
    return true;
  }, []);

  const navigateHref = useCallback(
    (href: string, opts?: { replace?: boolean }) => {
      if (!passesBlockers()) {
        return;
      }
      const nextRoute = parseAdminRoute(safeBaseConfig, href);
      const nextUrl = new URL(href, window.location.href);

      if (opts?.replace) {
        window.history.replaceState({}, '', nextUrl.href);
      } else {
        window.history.pushState({}, '', nextUrl.href);
      }
      setRoute(nextRoute);
    },
    [safeBaseConfig, passesBlockers]
  );

  const navigateView = useCallback(
    (view: string, extra?: Record<string, string>, opts?: { replace?: boolean }) => {
      const href = appViewHref(safeBaseConfig, view, extra || {});
      navigateHref(href, opts);
    },
    [safeBaseConfig, navigateHref]
  );

  useEffect(() => {
    const onPopState = () => {
      // Browser back/forward: the URL has already changed. If a blocker says
      // no, push the previous URL back so the user stays where they were.
      if (!passesBlockers()) {
        const current = parseAdminRoute(safeBaseConfig);
        // current still reflects the *previous* (pre-popstate) route in state.
        // Re-push the matching URL to undo the popstate.
        try {
          window.history.pushState({}, '', window.location.href.replace(window.location.search, `?${new URLSearchParams({
            page: 'sikshya',
            view: current.page,
            ...current.query,
          }).toString()}`));
        } catch {
          /* if URL rewriting fails, fall back to letting the navigation through */
          setRoute(parseAdminRoute(safeBaseConfig));
        }
        return;
      }
      setRoute(parseAdminRoute(safeBaseConfig));
    };
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, [safeBaseConfig, passesBlockers]);

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

  const value = useMemo<Ctx>(
    () => ({ route, navigateView, navigateHref, registerNavigationBlocker }),
    [route, navigateView, navigateHref, registerNavigationBlocker]
  );

  return <AdminRoutingContext.Provider value={value}>{children}</AdminRoutingContext.Provider>;
}

