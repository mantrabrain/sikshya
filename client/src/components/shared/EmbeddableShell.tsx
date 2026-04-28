import type { ReactNode } from 'react';
import { AppShell } from '../AppShell';
import type { NavItem, SikshyaReactConfig } from '../../types';

type Props = {
  /**
   * When true, render only the page body — no surrounding `AppShell`. The parent
   * (typically a tabbed hub page) is expected to own the shell. The page-level
   * action bar still renders, just inline above the content so the tab can keep
   * its primary CTA next to the content it controls.
   */
  embedded?: boolean;
  config: SikshyaReactConfig;
  title: string;
  subtitle?: string;
  badge?: string;
  pageActions?: ReactNode;
  sidebarActivePage?: string;
  children: ReactNode;
};

/**
 * Wraps a page body so the same component can render either standalone (with
 * its own `AppShell`) or inside a hub that already provides the shell. Lets us
 * collapse multiple sidebar entries into tabbed hubs without forking page
 * implementations.
 */
export function EmbeddableShell(props: Props) {
  const { embedded, config, title, subtitle, badge, pageActions, sidebarActivePage, children } = props;

  // Default to embedded mode to avoid "shell inside shell" when a route forgets to pass `embedded`.
  // Only render a standalone `AppShell` when explicitly requested.
  if (embedded !== false) {
    return (
      <div className="space-y-4">
        {pageActions ? (
          <div className="flex flex-wrap items-center justify-end gap-2">{pageActions}</div>
        ) : null}
        {children}
      </div>
    );
  }

  return (
    <AppShell
      page={config.page}
      sidebarActivePage={sidebarActivePage}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      pluginUrl={config.pluginUrl}
      user={config.user}
      branding={config.branding}
      title={title}
      subtitle={subtitle}
      badge={badge}
      pageActions={pageActions}
    >
      {children}
    </AppShell>
  );
}
