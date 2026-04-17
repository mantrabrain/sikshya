import { useEffect, useState, type Dispatch, type ReactNode, type SetStateAction } from 'react';
import { SHELL_HEADER_MIN_CLASS } from '../constants/shellChrome';
import { NavIcon } from './NavIcon';
import type { NavItem } from '../types';

/** Map builder / nested routes to a Course submenu id so the Course group stays highlighted. */
function effectiveNavPage(currentPage: string): string {
  if (currentPage === 'add-course') {
    return 'courses';
  }
  if (currentPage === 'add-lesson') {
    return 'lessons';
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

function ChildLink({ item, currentPage }: { item: NavItem; currentPage: string }) {
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
          : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/70'
      }`}
    >
      <IconSlot size="sm">
        <NavIcon name={item.icon} className="h-4 w-4 text-slate-400 dark:text-slate-500" />
      </IconSlot>
      <span className="min-w-0 flex-1 truncate">{item.label}</span>
    </a>
  );
}

function NavBlock({
  item,
  currentPage,
  open,
  setOpen,
}: {
  item: NavItem;
  currentPage: string;
  open: Record<string, boolean>;
  setOpen: Dispatch<SetStateAction<Record<string, boolean>>>;
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
              ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white'
              : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/60'
          }`}
        >
          <IconSlot>
            <NavIcon name={item.icon} className="h-5 w-5 text-slate-500 dark:text-slate-400" />
          </IconSlot>
          <span className="min-w-0 flex-1 truncate">{item.label}</span>
          <IconSlot size="sm">
            <NavIcon name={expanded ? 'chevronDown' : 'chevronRight'} className="h-4 w-4 text-slate-400" />
          </IconSlot>
        </button>
        {expanded ? (
          <div className="ml-2.5 space-y-0.5 border-l border-slate-200 py-0.5 pl-2.5 dark:border-slate-700">
            {children.map((c) => (
              <ChildLink key={c.id} item={c} currentPage={currentPage} />
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
          : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/60'
      }`}
    >
      <IconSlot>
        <NavIcon
          name={item.icon}
          className={`h-5 w-5 ${active ? 'text-brand-600 dark:text-brand-400' : 'text-slate-500 dark:text-slate-400'}`}
        />
      </IconSlot>
      <span className="min-w-0 flex-1 truncate">{item.label}</span>
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
};

export function Sidebar({ items, currentPage, version, proPluginVersion, proLicensed, adminUrl }: Props) {
  const [open, setOpen] = useState<Record<string, boolean>>({});

  useEffect(() => {
    setOpen((prev) => {
      const next = { ...prev };
      items.forEach((item) => {
        if (item.children?.length && branchActive(item, currentPage)) {
          next[item.id] = true;
        }
      });
      return next;
    });
  }, [currentPage, items]);

  const wpHome = `${adminUrl.replace(/\/?$/, '/')}index.php`;

  return (
    <aside className="flex w-[260px] shrink-0 flex-col border-r border-slate-200/90 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div
        className={`flex shrink-0 flex-col justify-center border-b border-slate-100 px-5 dark:border-slate-800 ${SHELL_HEADER_MIN_CLASS}`}
      >
        <div className="text-lg font-semibold leading-tight tracking-tight text-slate-900 dark:text-white">Sikshya</div>
        <div className="mt-2 flex min-w-0 flex-nowrap items-center gap-1.5 overflow-x-auto overflow-y-hidden [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <span
            className="inline-flex shrink-0 items-center whitespace-nowrap rounded-md border border-slate-200/90 bg-slate-100/90 px-2 py-0.5 text-[11px] font-semibold leading-none tracking-tight text-slate-700 ring-1 ring-inset ring-slate-200/60 dark:border-slate-600 dark:bg-slate-800/90 dark:text-slate-200 dark:ring-slate-700/80"
            title={`Sikshya (free) ${version}`}
          >
            <span className="text-slate-500 dark:text-slate-400">Free</span>
            <span className="mx-0.5 text-slate-300 dark:text-slate-600" aria-hidden>
              ·
            </span>
            <span className="tabular-nums">v{version}</span>
          </span>
          {proPluginVersion ? (
            <span
              className={`inline-flex shrink-0 items-center whitespace-nowrap rounded-md border px-2 py-0.5 text-[11px] font-semibold leading-none tracking-tight ring-1 ring-inset ${
                proLicensed
                  ? 'border-emerald-200/90 bg-emerald-50 text-emerald-950 ring-emerald-100/80 dark:border-emerald-800/80 dark:bg-emerald-950/50 dark:text-emerald-100 dark:ring-emerald-900/50'
                  : 'border-amber-200/90 bg-amber-50 text-amber-950 ring-amber-100/80 dark:border-amber-800/80 dark:bg-amber-950/50 dark:text-amber-100 dark:ring-amber-900/50'
              }`}
              title={
                proLicensed
                  ? `Sikshya Pro ${proPluginVersion} (licensed)`
                  : `Sikshya Pro ${proPluginVersion} — activate your license for updates and paid modules`
              }
            >
              <span className={proLicensed ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-800 dark:text-amber-200'}>
                Pro
              </span>
              <span
                className={
                  proLicensed
                    ? 'mx-0.5 text-emerald-300/90 dark:text-emerald-700/90'
                    : 'mx-0.5 text-amber-300/90 dark:text-amber-800/90'
                }
                aria-hidden
              >
                ·
              </span>
              <span className="tabular-nums">v{proPluginVersion}</span>
              {!proLicensed ? (
                <>
                  <span className="mx-0.5 text-amber-300/90 dark:text-amber-800/90" aria-hidden>
                    ·
                  </span>
                  <span className="text-[10px] font-semibold uppercase tracking-wide opacity-90">Unlicensed</span>
                </>
              ) : null}
            </span>
          ) : null}
        </div>
      </div>
      <nav className="flex-1 space-y-1 overflow-y-auto px-2.5 py-4">
        {items.map((item) => (
          <NavBlock key={item.id} item={item} currentPage={currentPage} open={open} setOpen={setOpen} />
        ))}
      </nav>
      <div className="border-t border-slate-100 p-3 dark:border-slate-800">
        <a
          href={wpHome}
          className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
        >
          <IconSlot size="sm">
            <NavIcon name="arrowLeft" className="h-4 w-4" />
          </IconSlot>
          <span className="min-w-0 flex-1 truncate">Back to WordPress</span>
        </a>
      </div>
    </aside>
  );
}
