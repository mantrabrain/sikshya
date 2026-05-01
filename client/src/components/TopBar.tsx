import { useEffect, useId, useRef, useState } from 'react';
import { SHELL_HEADER_MIN_CLASS } from '../constants/shellChrome';
import type { SikshyaShellUser } from '../types';
import { NavIcon } from './NavIcon';

type Props = {
  title: string;
  /** Accepted for API compatibility; not shown in the top chrome (page titles only). */
  subtitle?: string;
  badge?: string;
  user?: SikshyaShellUser;
  adminUrl: string;
  toolsHref?: string;
  isDark: boolean;
  onToggleDark: () => void;
};

const menuLinkClass =
  'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800';

const COMMUNITY_GROUP_URL = 'https://www.facebook.com/groups/sikshyalms/';

/**
 * Top chrome always uses default light/dark shell styling. White-label colours apply to
 * the sidebar and to the global `brand-*` accent (see {@link applyAdminBrandThemeToRoot}), not here.
 */
export function TopBar({
  title,
  subtitle: _subtitle,
  badge,
  user,
  adminUrl,
  toolsHref,
  isDark,
  onToggleDark,
}: Props) {
  const wpIndex = `${adminUrl.replace(/\/?$/, '/')}index.php`;
  const [menuOpen, setMenuOpen] = useState(false);
  const wrapRef = useRef<HTMLDivElement>(null);
  const menuId = useId();
  const safeUser: SikshyaShellUser = user || { name: 'Admin', avatarUrl: '' };
  const { name, avatarUrl, email, profileUrl, logoutUrl } = safeUser;
  const initial = name.trim().charAt(0).toUpperCase() || '?';

  useEffect(() => {
    if (!menuOpen) {
      return;
    }
    const onDocMouseDown = (e: MouseEvent) => {
      const el = wrapRef.current;
      if (!el || el.contains(e.target as Node)) {
        return;
      }
      setMenuOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', onDocMouseDown);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDocMouseDown);
      document.removeEventListener('keydown', onKey);
    };
  }, [menuOpen]);

  return (
    <header
      className={`sticky top-0 z-20 flex shrink-0 items-center border-b border-slate-200 bg-white px-6 dark:border-slate-800 dark:bg-slate-900 ${SHELL_HEADER_MIN_CLASS}`}
    >
      <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
        <div className="min-w-0 flex-1 py-1 sm:py-0">
          <h1 className="min-w-0 truncate text-xl font-semibold leading-tight tracking-tight text-slate-900 dark:text-white">
            {title}
          </h1>
          {badge ? (
            <p className="mt-1 text-sm leading-snug text-slate-500 dark:text-slate-400">
              <span className="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                {badge}
              </span>
            </p>
          ) : null}
        </div>

        <div className="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
          <a
            href={wpIndex}
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            <NavIcon name="arrowLeft" className="h-4 w-4 text-slate-500 dark:text-slate-400" />
            Back to WordPress
          </a>
          <a
            href={COMMUNITY_GROUP_URL}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            <NavIcon name="users" className="h-4 w-4 text-slate-500 dark:text-slate-400" />
            Join Community
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
            onClick={onToggleDark}
            className="rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
            aria-label={isDark ? 'Light mode' : 'Dark mode'}
          >
            <NavIcon name={isDark ? 'sun' : 'moon'} className="h-5 w-5" />
          </button>

          <div className="relative" ref={wrapRef}>
            <button
              type="button"
              id="sikshya-topbar-user-trigger"
              className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-100 ring-brand-500/0 transition hover:ring-2 hover:ring-brand-500/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"
              aria-expanded={menuOpen}
              aria-haspopup="true"
              aria-controls={menuId}
              title={name}
              onClick={() => setMenuOpen((o) => !o)}
            >
              {avatarUrl ? (
                <img src={avatarUrl} alt="" className="h-full w-full object-cover" referrerPolicy="no-referrer" />
              ) : (
                <span className="text-sm font-semibold text-slate-600 dark:text-slate-300">{initial}</span>
              )}
            </button>

            {menuOpen ? (
              <div
                id={menuId}
                role="menu"
                aria-labelledby="sikshya-topbar-user-trigger"
                className="absolute right-0 top-full z-30 mt-2 w-[min(18rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-black/5 dark:border-slate-700 dark:bg-slate-900 dark:ring-white/10"
              >
                <div className="flex gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800">
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-100 dark:border-slate-600 dark:bg-slate-800">
                    {avatarUrl ? (
                      <img
                        src={avatarUrl}
                        alt=""
                        className="h-full w-full object-cover"
                        referrerPolicy="no-referrer"
                      />
                    ) : (
                      <span className="text-base font-semibold text-slate-600 dark:text-slate-300">{initial}</span>
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold text-slate-900 dark:text-white">{name}</div>
                    {email ? (
                      <div className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400" title={email}>
                        {email}
                      </div>
                    ) : null}
                  </div>
                </div>

                <div className="p-1.5" role="none">
                  {profileUrl ? (
                    <a href={profileUrl} role="menuitem" className={menuLinkClass} onClick={() => setMenuOpen(false)}>
                      <NavIcon name="userCircle" className="h-4 w-4 text-slate-500 dark:text-slate-400" />
                      Edit profile
                    </a>
                  ) : null}
                  <a href={wpIndex} role="menuitem" className={menuLinkClass} onClick={() => setMenuOpen(false)}>
                    <NavIcon name="arrowLeft" className="h-4 w-4 text-slate-500 dark:text-slate-400" />
                    Back to WordPress
                  </a>
                  {logoutUrl ? (
                    <a
                      href={logoutUrl}
                      role="menuitem"
                      className={`${menuLinkClass} text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40`}
                      onClick={() => setMenuOpen(false)}
                    >
                      <NavIcon name="logOut" className="h-4 w-4 text-rose-500 dark:text-rose-400" />
                      Log out
                    </a>
                  ) : null}
                </div>
              </div>
            ) : null}
          </div>
        </div>
      </div>
    </header>
  );
}
