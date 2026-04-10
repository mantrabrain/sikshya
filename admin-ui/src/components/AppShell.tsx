import { useEffect, useMemo, useState } from 'react';
import { Sidebar } from './Sidebar';
import { TopBar } from './TopBar';
import type { NavItem } from '../types';

const THEME_KEY = 'sikshya-admin-theme';

type Props = {
  page: string;
  /** When the route key differs from the nav item to highlight (e.g. `edit-content` → `lessons`). */
  sidebarActivePage?: string;
  version: string;
  navigation: NavItem[];
  adminUrl: string;
  userName: string;
  userAvatarUrl?: string;
  title: string;
  subtitle?: string;
  badge?: string;
  /** Primary actions (e.g. Add new) — rendered in the gray content area, not the white top bar. */
  pageActions?: React.ReactNode;
  children: React.ReactNode;
};

export function AppShell({
  page,
  sidebarActivePage,
  version,
  navigation,
  adminUrl,
  userName,
  userAvatarUrl,
  title,
  subtitle,
  badge,
  pageActions,
  children,
}: Props) {
  const [isDark, setIsDark] = useState(false);

  useEffect(() => {
    const root = document.documentElement;
    const stored = localStorage.getItem(THEME_KEY);
    const prefersDark =
      stored === 'dark' ||
      (stored !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    if (prefersDark) {
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

  return (
    <div className="flex min-h-screen bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
      <Sidebar items={navigation} currentPage={sidebarActivePage ?? page} version={version} adminUrl={adminUrl} />
      <div className="flex min-w-0 flex-1 flex-col">
        <TopBar
          title={title}
          subtitle={subtitle}
          badge={badge}
          userName={userName}
          userAvatarUrl={userAvatarUrl}
          adminUrl={adminUrl}
          toolsHref={toolsHref}
          isDark={isDark}
          onToggleDark={onToggleDark}
        />
        <main className="flex-1 overflow-auto bg-slate-50 p-6 dark:bg-slate-950">
          {pageActions ? (
            <div className="mb-6 flex flex-wrap items-center justify-end gap-2">{pageActions}</div>
          ) : null}
          {children}
        </main>
      </div>
    </div>
  );
}
