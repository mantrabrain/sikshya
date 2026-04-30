import { useEffect, useMemo, useState, type Dispatch, type ReactNode, type SetStateAction } from 'react';
import type React from 'react';
import { getConfig } from '../config/env';
import { SHELL_HEADER_MIN_CLASS } from '../constants/shellChrome';
import { NavIcon } from './NavIcon';
import type { NavItem, NavItemBadge } from '../types';

function NavBadge({ badge }: { badge: NavItemBadge }) {
  const isOff = badge === 'off';
  return (
    <span
      className={`ml-2 shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ${
        isOff
          ? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
          : 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-100'
      }`}
      title={isOff ? 'Addon is turned off in Addons' : 'Upgrade your plan to unlock'}
    >
      {isOff ? 'Off' : 'Upgrade'}
    </span>
  );
}

/** Map builder / nested routes to a Course submenu id so the Course group stays highlighted. */
function effectiveNavPage(currentPage: string): string {
  if (currentPage === 'add-course') {
    return 'courses';
  }
  if (currentPage === 'add-lesson') {
    return 'lessons';
  }
  if (currentPage === 'email-template-edit' || currentPage === 'email-templates') {
    return 'email-hub';
  }
  // Commerce → Sales hub: standalone lists + detail URLs use other view ids than `sales`.
  if (currentPage === 'sales' || currentPage === 'orders' || currentPage === 'order' || currentPage === 'payments' || currentPage === 'payment') {
    return 'sales';
  }
  return currentPage;
}

function branchActive(item: NavItem, currentPage: string): boolean {
  const navPage = effectiveNavPage(currentPage);
  if (item.id === navPage) {
    return true;
  }
  return item.children?.some((c) => branchActive(c, currentPage)) ?? false;
}

/** Fixed-width column so every row’s icon and label line up. */
function IconSlot({ children, size = 'md' }: { children: ReactNode; size?: 'md' | 'sm' }) {
  const box = size === 'sm' ? 'h-4 w-4' : 'h-5 w-5';
  return (
    <span className={`flex shrink-0 items-center justify-center ${box}`} aria-hidden>
      {children}
    </span>
  );
}

function ChildLink({
  item,
  currentPage,
  brandedChrome,
}: {
  item: NavItem;
  currentPage: string;
  brandedChrome: boolean;
}) {
  if (!item.href) {
    return null;
  }
  const active = item.id === effectiveNavPage(currentPage);
  return (
    <a
      href={item.href}
      className={`flex w-full items-center gap-3 rounded-lg py-2 pl-3 pr-2 text-[13px] font-medium transition-colors ${
        active
          ? 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-100/80 dark:bg-brand-950/40 dark:text-brand-300 dark:ring-brand-900/50'
          : brandedChrome
            ? 'text-inherit/75 hover:bg-current/8'
            : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/70'
      }`}
    >
      <IconSlot size="sm">
        <NavIcon
          name={item.icon}
          className={`h-4 w-4 ${
            active
              ? 'text-brand-600 dark:text-brand-400'
              : brandedChrome
                ? 'text-inherit/60'
                : 'text-slate-400 dark:text-slate-500'
          }`}
        />
      </IconSlot>
      <span className="min-w-0 flex-1 truncate">{item.label}</span>
      {item.badge ? <NavBadge badge={item.badge} /> : null}
    </a>
  );
}

function NavBlock({
  item,
  currentPage,
  open,
  setOpen,
  brandedChrome,
}: {
  item: NavItem;
  currentPage: string;
  open: Record<string, boolean>;
  setOpen: Dispatch<SetStateAction<Record<string, boolean>>>;
  brandedChrome: boolean;
}) {
  const children = item.children;
  if (children?.length) {
    const childBranch = branchActive(item, currentPage);
    const expanded = open[item.id] ?? childBranch;

    return (
      <div className="space-y-0.5">
        <button
          type="button"
          onClick={() => setOpen((o) => ({ ...o, [item.id]: !expanded }))}
          className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-colors ${
            childBranch
              ? brandedChrome
                ? 'bg-current/8 text-inherit'
                : 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white'
              : brandedChrome
                ? 'text-inherit/80 hover:bg-current/8'
                : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/60'
          }`}
        >
          <IconSlot>
            <NavIcon
              name={item.icon}
              className={`h-5 w-5 ${brandedChrome ? 'text-inherit/65' : 'text-slate-500 dark:text-slate-400'}`}
            />
          </IconSlot>
          <span className="min-w-0 flex-1 truncate">{item.label}</span>
          <IconSlot size="sm">
            <NavIcon
              name={expanded ? 'chevronDown' : 'chevronRight'}
              className={`h-4 w-4 ${brandedChrome ? 'text-inherit/45' : 'text-slate-400'}`}
            />
          </IconSlot>
        </button>
        {expanded ? (
          <div
            className={`ml-2.5 space-y-0.5 border-l py-0.5 pl-2.5 ${
              brandedChrome ? 'border-current/15' : 'border-slate-200 dark:border-slate-700'
            }`}
          >
            {children.map((c) => (
              <ChildLink key={c.id} item={c} currentPage={currentPage} brandedChrome={brandedChrome} />
            ))}
          </div>
        ) : null}
      </div>
    );
  }

  if (!item.href) {
    return null;
  }

  const active = item.id === effectiveNavPage(currentPage);
  return (
    <a
      href={item.href}
      className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${
        active
          ? 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-100 dark:bg-brand-950/40 dark:text-brand-300 dark:ring-brand-900/40'
          : brandedChrome
            ? 'text-inherit/80 hover:bg-current/8'
            : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/60'
      }`}
    >
      <IconSlot>
        <NavIcon
          name={item.icon}
          className={`h-5 w-5 ${
            active ? 'text-brand-600 dark:text-brand-400' : brandedChrome ? 'text-inherit/65' : 'text-slate-500 dark:text-slate-400'
          }`}
        />
      </IconSlot>
      <span className="min-w-0 flex-1 truncate">{item.label}</span>
      {item.badge ? <NavBadge badge={item.badge} /> : null}
    </a>
  );
}

type Props = {
  items: NavItem[];
  currentPage: string;
  /** Free / main Sikshya plugin version. */
  version: string;
  /** Installed Pro add-on semver (when the Pro plugin is loaded). */
  proPluginVersion?: string;
  /** True when the Pro licence is active on this site. */
  proLicensed?: boolean;
  adminUrl: string;
  /** Plugin root URL; used for the default mark when no white-label logo is set. */
  pluginUrl?: string;
  branding?: {
    pluginName?: string;
    logoUrl?: string;
    sidebarBg?: string;
    sidebarText?: string;
  };
};

export function Sidebar({
  items,
  currentPage,
  version,
  proPluginVersion,
  proLicensed,
  adminUrl,
  pluginUrl,
  branding,
}: Props) {
  // Tools lives in the top header; omit from the sidebar to reduce duplication.
  const visibleItems = useMemo(() => items.filter((item) => item.id !== 'tools'), [items]);
  const [open, setOpen] = useState<Record<string, boolean>>({});

  useEffect(() => {
    setOpen((prev) => {
      const next = { ...prev };
      visibleItems.forEach((item) => {
        if (item.children?.length && branchActive(item, currentPage)) {
          next[item.id] = true;
        }
      });
      return next;
    });
  }, [currentPage, visibleItems]);

  const wpHome = `${adminUrl.replace(/\/?$/, '/')}index.php`;
  const title = branding?.pluginName?.trim() ? branding.pluginName.trim() : 'Sikshya';
  const pluginBase = (pluginUrl?.trim() || getConfig().pluginUrl || '').replace(/\/+$/, '');
  const whiteLabelLogo = branding?.logoUrl?.trim() || '';
  const logoSrc =
    whiteLabelLogo || (pluginBase ? `${pluginBase}/assets/images/logo-white.png` : '');
  const brandedChrome = Boolean(branding?.sidebarBg || branding?.sidebarText);
  const sidebarStyle = useMemo<React.CSSProperties | undefined>(() => {
    if (!branding?.sidebarBg && !branding?.sidebarText) return undefined;
    return {
      backgroundColor: branding?.sidebarBg || undefined,
      color: branding?.sidebarText || undefined,
    };
  }, [branding?.sidebarBg, branding?.sidebarText]);

  return (
    <aside
      style={sidebarStyle}
      className={`flex h-screen w-[260px] shrink-0 flex-col border-r border-slate-200/90 bg-white dark:border-slate-800 dark:bg-slate-900 ${
        brandedChrome ? 'border-inherit' : ''
      }`}
    >
      <div
        className={`flex shrink-0 flex-col justify-center border-b px-5 ${
          brandedChrome ? 'border-current/10' : 'border-slate-100 dark:border-slate-800'
        } ${SHELL_HEADER_MIN_CLASS}`}
      >
        <div className="flex items-center gap-3">
          {logoSrc ? (
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-600 shadow-sm ring-1 ring-inset ring-brand-500/35">
              <img
                src={logoSrc}
                alt=""
                className="h-6 w-6 object-contain object-center"
                referrerPolicy="no-referrer"
              />
            </div>
          ) : null}
          <div
            className={`min-w-0 text-lg font-semibold leading-tight tracking-tight ${
              brandedChrome ? 'text-inherit' : 'text-slate-900 dark:text-white'
            }`}
          >
            {title}
          </div>
        </div>
        <div className="mt-2.5 flex min-w-0 flex-wrap items-center gap-1.5">
          <span
            className={`inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-medium leading-none tracking-tight shadow-sm ${
              brandedChrome
                ? 'border-white/15 bg-black/[0.12] text-inherit shadow-black/10 dark:border-white/10 dark:bg-white/[0.08]'
                : 'border-slate-200/90 bg-white text-slate-600 shadow-slate-900/[0.04] dark:border-slate-600/80 dark:bg-slate-800/90 dark:text-slate-300 dark:shadow-none'
            }`}
            title={`${title} (free) ${version}`}
          >
            <span
              className={`h-1.5 w-1.5 shrink-0 rounded-full ${
                brandedChrome ? 'bg-current opacity-50' : 'bg-slate-400 dark:bg-slate-500'
              }`}
              aria-hidden
            />
            <span className={brandedChrome ? 'text-inherit/90' : ''}>Free</span>
            <span
              className={`tabular-nums opacity-75 ${
                brandedChrome ? 'text-inherit/80' : 'text-slate-500 dark:text-slate-400'
              }`}
            >
              v{version}
            </span>
          </span>
          {proPluginVersion ? (
            <span
              className={`inline-flex max-w-full shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-medium leading-none tracking-tight shadow-sm ${
                proLicensed
                  ? 'border-emerald-200/80 bg-emerald-50 text-emerald-900 shadow-emerald-900/[0.04] dark:border-emerald-800/70 dark:bg-emerald-950/55 dark:text-emerald-100 dark:shadow-none'
                  : 'border-amber-200/80 bg-amber-50 text-amber-950 shadow-amber-900/[0.04] dark:border-amber-800/70 dark:bg-amber-950/50 dark:text-amber-50 dark:shadow-none'
              }`}
              title={
                proLicensed
                  ? `${title} Pro ${proPluginVersion} (licensed)`
                  : `${title} Pro ${proPluginVersion} — activate your license for updates and paid modules`
              }
            >
              <span
                className={`h-1.5 w-1.5 shrink-0 rounded-full ${
                  proLicensed ? 'bg-emerald-500 dark:bg-emerald-400' : 'bg-amber-500 dark:bg-amber-400'
                }`}
                aria-hidden
              />
              <span className={proLicensed ? 'text-emerald-800 dark:text-emerald-100' : 'text-amber-900 dark:text-amber-100'}>
                Pro
              </span>
              <span
                className={`tabular-nums ${
                  proLicensed ? 'text-emerald-700/85 dark:text-emerald-200/90' : 'text-amber-800/90 dark:text-amber-200/90'
                }`}
              >
                v{proPluginVersion}
              </span>
              {!proLicensed ? (
                <span className="max-w-[5.5rem] truncate text-[9px] font-normal normal-case tracking-normal text-amber-700/80 dark:text-amber-200/75">
                  inactive
                </span>
              ) : null}
            </span>
          ) : null}
        </div>
      </div>
      <nav className="min-h-0 flex-1 space-y-1 overflow-y-auto px-2.5 py-4">
        {visibleItems.map((item) => (
          <NavBlock
            key={item.id}
            item={item}
            currentPage={currentPage}
            open={open}
            setOpen={setOpen}
            brandedChrome={brandedChrome}
          />
        ))}
      </nav>
      <div
        className={`sticky bottom-0 border-t p-3 backdrop-blur ${
          brandedChrome ? 'border-current/10' : 'border-slate-100 bg-white/95 dark:border-slate-800 dark:bg-slate-900/95'
        }`}
      >
        <a
          href={wpHome}
          className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${
            brandedChrome
              ? 'text-inherit/75 hover:bg-current/8 hover:text-inherit'
              : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'
          }`}
        >
          <IconSlot size="sm">
            <NavIcon name="arrowLeft" className={`h-4 w-4 ${brandedChrome ? 'text-inherit/70' : ''}`} />
          </IconSlot>
          <span className="min-w-0 flex-1 truncate">Back to WordPress</span>
        </a>
      </div>
    </aside>
  );
}
