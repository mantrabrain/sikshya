import { SHELL_HEADER_MIN_CLASS } from '../constants/shellChrome';
import type React from 'react';
import { NavIcon } from './NavIcon';

type Props = {
  title: string;
  subtitle?: string;
  badge?: string;
  userName: string;
  userAvatarUrl?: string;
  adminUrl: string;
  toolsHref?: string;
  isDark: boolean;
  onToggleDark: () => void;
  branding?: {
    topbarBg?: string;
    topbarText?: string;
  };
};

export function TopBar({
  title,
  subtitle,
  badge,
  userName,
  userAvatarUrl,
  adminUrl,
  toolsHref,
  isDark,
  onToggleDark,
  branding,
}: Props) {
  const wpIndex = `${adminUrl.replace(/\/?$/, '/')}index.php`;
  const topbarStyle: React.CSSProperties | undefined =
    branding?.topbarBg || branding?.topbarText
      ? {
          backgroundColor: branding?.topbarBg || undefined,
          color: branding?.topbarText || undefined,
        }
      : undefined;

  return (
    <header
      style={topbarStyle}
      className={`sticky top-0 z-20 flex shrink-0 items-center border-b border-slate-200 bg-white px-6 dark:border-slate-800 dark:bg-slate-900 ${SHELL_HEADER_MIN_CLASS}`}
    >
      <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
        <div className="min-w-0 flex-1 py-1 sm:py-0">
          <h1 className="truncate text-xl font-semibold leading-tight tracking-tight text-slate-900 dark:text-white">
            {title}
          </h1>
          {(subtitle || badge) && (
            <p className="mt-1 text-sm leading-snug text-slate-500 dark:text-slate-400">
              {subtitle}
              {badge ? (
                <span className="ml-2 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                  {badge}
                </span>
              ) : null}
            </p>
          )}
        </div>

        <div className="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
          <a
            href={wpIndex}
            className="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white xl:inline"
          >
            Back to WordPress
          </a>
          {toolsHref ? (
            <a
              href={toolsHref}
              className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            >
              <NavIcon name="wrench" className="h-4 w-4 text-slate-500 dark:text-slate-400" />
              Tools
            </a>
          ) : null}
          <button
            type="button"
            className="rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
            aria-label="Search"
          >
            <NavIcon name="search" className="h-5 w-5" />
          </button>
          <button
            type="button"
            className="rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
            aria-label="Notifications"
          >
            <NavIcon name="bell" className="h-5 w-5" />
          </button>
          <button
            type="button"
            onClick={onToggleDark}
            className="rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
            aria-label={isDark ? 'Light mode' : 'Dark mode'}
          >
            <NavIcon name={isDark ? 'sun' : 'moon'} className="h-5 w-5" />
          </button>
          <div
            className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-100 dark:border-slate-600 dark:bg-slate-800"
            title={userName}
          >
            {userAvatarUrl ? (
              <img src={userAvatarUrl} alt="" className="h-full w-full object-cover" referrerPolicy="no-referrer" />
            ) : (
              <span className="text-sm font-semibold text-slate-600 dark:text-slate-300">
                {userName.trim().charAt(0).toUpperCase() || '?'}
              </span>
            )}
          </div>
        </div>
      </div>
    </header>
  );
}
