import { useMemo } from 'react';
import { AppShell } from '../AppShell';
import { HorizontalEditorTabs } from './HorizontalEditorTabs';
import { useAdminRouting } from '../../lib/adminRouting';
import type { NavItem, SikshyaReactConfig } from '../../types';

export type HubTab = {
  /** URL slug for the tab (`?tab=…`). */
  id: string;
  label: string;
  icon?: string;
  /**
   * Renders the tab body. Receives the same `config` so child pages keep
   * working with their existing prop contract; child pages MUST honour
   * `embedded` so they skip rendering their own `AppShell`.
   */
  render: (config: SikshyaReactConfig) => React.ReactNode;
  /** Hide the tab entirely (e.g. unlicensed Pro feature). */
  hidden?: boolean;
};

type Props = {
  config: SikshyaReactConfig;
  /** Hub title shown in the AppShell header. */
  title: string;
  subtitle?: string;
  badge?: string;
  /** Sidebar nav id this hub maps to (for active highlight). */
  sidebarActivePage?: string;
  tabs: HubTab[];
  /** Fallback tab when the URL has no `?tab=` (defaults to first visible tab). */
  defaultTabId?: string;
};

/**
 * Generic shell for a tabbed admin hub. Owns the `AppShell`, the tab strip,
 * and reads/writes `?tab=` so each tab has a real URL (deep links + browser
 * back/forward both work). Child pages render in `embedded` mode.
 */
export function TabbedHubPage(props: Props) {
  const { config, title, subtitle, badge, sidebarActivePage, tabs, defaultTabId } = props;
  const { route, navigateView } = useAdminRouting();

  const visibleTabs = useMemo(() => tabs.filter((t) => !t.hidden), [tabs]);
  const fallbackId = defaultTabId && visibleTabs.find((t) => t.id === defaultTabId)
    ? defaultTabId
    : visibleTabs[0]?.id || '';

  const activeId = (() => {
    const fromUrl = (route.query?.tab || '').trim();
    if (fromUrl && visibleTabs.some((t) => t.id === fromUrl)) {
      return fromUrl;
    }
    return fallbackId;
  })();

  const onTabChange = (id: string) => {
    navigateView(route.page, { tab: id });
  };

  const activeTab = visibleTabs.find((t) => t.id === activeId);

  return (
    <AppShell
      page={config.page}
      sidebarActivePage={sidebarActivePage}
      version={config.version}
      navigation={config.navigation as NavItem[]}
      adminUrl={config.adminUrl}
      userName={config.user.name}
      userAvatarUrl={config.user.avatarUrl}
      branding={config.branding}
      title={title}
      subtitle={subtitle}
      badge={badge}
    >
      {visibleTabs.length > 1 ? (
        <div className="-mt-2 mb-5 flex flex-wrap items-center gap-2 overflow-x-auto">
          <HorizontalEditorTabs
            ariaLabel={`${title} sections`}
            tabs={visibleTabs.map((t) => ({ id: t.id, label: t.label, icon: t.icon }))}
            value={activeId}
            onChange={onTabChange}
          />
        </div>
      ) : null}
      {activeTab ? activeTab.render(config) : null}
    </AppShell>
  );
}
