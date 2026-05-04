import { useEffect, useMemo, useState } from 'react';
import { useShellState } from '../context/ShellStateContext';
import { InlineNotices } from './notices/InlineNotices';
import { ShellAlertStrip } from './ShellAlertStrip';
import { Sidebar } from './Sidebar';
import { TopBar } from './TopBar';
import type { NavItem, SikshyaShellUser } from '../types';

const THEME_KEY = 'sikshya-admin-theme';

type Props = {
  page: string;
  /** When the route key differs from the nav item to highlight (e.g. `edit-content` → `lessons`). */
  sidebarActivePage?: string;
  version: string;
  navigation: NavItem[];
  adminUrl: string;
  user?: SikshyaShellUser;
  title: string;
  subtitle?: string;
  badge?: string;
  /** Sidebar brand strip (logo, colours, name). Top bar is title-only — see `TopBar`. */
  branding?: {
    pluginName?: string;
    logoUrl?: string;
    topbarBg?: string;
    topbarText?: string;
    sidebarBg?: string;
    sidebarText?: string;
  };
  /** Primary actions (e.g. Add new) — rendered in the gray content area, not the white top bar. */
  pageActions?: React.ReactNode;
  /** Optional override for pages that need full-bleed workspace layouts. */
  contentClassName?: string;
  /** Plugin root URL (for bundled default sidebar logo). Falls back to `getConfig()` when omitted. */
  pluginUrl?: string;
  children: React.ReactNode;
};

export function AppShell({
  page,
  sidebarActivePage,
  version,
  navigation,
  adminUrl,
  user,
  title,
  subtitle,
  badge,
  branding,
  pageActions,
  contentClassName,
  pluginUrl,
  children,
}: Props) {
  const [isDark, setIsDark] = useState(false);
  const safeUser: SikshyaShellUser = user || { name: 'Admin', avatarUrl: '' };

	useEffect(() => {
		const root = document.documentElement;
		const stored = localStorage.getItem(THEME_KEY);
		// Default to light. Only use dark when the user has chosen it (top bar toggle persists `dark` / `light`).
		// We intentionally do not follow `prefers-color-scheme`, so OS dark mode does not override the default.
		const useDark = stored === 'dark';
		if (useDark) {
			root.classList.add('dark');
			setIsDark(true);
		} else {
			root.classList.remove('dark');
			setIsDark(false);
		}
	}, []);

  const onToggleDark = () => {
    setIsDark((d) => {
      const next = !d;
      if (next) {
        document.documentElement.classList.add('dark');
        localStorage.setItem(THEME_KEY, 'dark');
      } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem(THEME_KEY, 'light');
      }
      return next;
    });
  };

  const toolsHref = useMemo(() => navigation.find((n) => n.id === 'tools')?.href, [navigation]);

  const { shellAlerts, proPluginVersion, licensing } = useShellState();

  return (
    <div className="flex h-screen overflow-hidden bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
      <Sidebar
        items={navigation}
        currentPage={sidebarActivePage ?? page}
        version={version}
        proPluginVersion={proPluginVersion || undefined}
        proLicensed={Boolean(licensing?.isProActive)}
        adminUrl={adminUrl}
        pluginUrl={pluginUrl}
        branding={branding}
      />
      <div className="flex min-h-0 min-w-0 flex-1 flex-col">
        <TopBar
          title={title}
          subtitle={subtitle}
          badge={badge}
          user={safeUser}
          adminUrl={adminUrl}
          toolsHref={toolsHref}
          isDark={isDark}
          onToggleDark={onToggleDark}
        />
        <main
          className={
            contentClassName ??
            'min-h-0 flex-1 overflow-y-auto bg-slate-50 px-6 pb-8 pt-6 dark:bg-slate-950'
          }
        >
          <ShellAlertStrip alerts={shellAlerts} />
          <InlineNotices />
          {pageActions ? (
            <div className="mb-6 flex flex-wrap items-center justify-end gap-2">{pageActions}</div>
          ) : null}
          {children}
        </main>
      </div>
    </div>
  );
}
