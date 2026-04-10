import { createPortal } from 'react-dom';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { NavIcon } from '../../NavIcon';

export type RowActionLinkItem = {
  key: string;
  label: string;
  href: string;
  external?: boolean;
};

export type RowActionButtonItem = {
  key: string;
  label: string;
  onClick: () => void;
  danger?: boolean;
};

export type RowActionItem = RowActionLinkItem | RowActionButtonItem;

function isLink(i: RowActionItem): i is RowActionLinkItem {
  return 'href' in i;
}

/**
 * ⋮ row menu. Panel is portaled to `document.body` with fixed positioning so
 * parent `overflow: hidden` / scroll containers (e.g. list tables) cannot clip it.
 */
export function RowActionsMenu({ items, ariaLabel }: { items: RowActionItem[]; ariaLabel: string }) {
  const [open, setOpen] = useState(false);
  const [menuPos, setMenuPos] = useState<{ top: number; right: number } | null>(null);
  const wrapRef = useRef<HTMLDivElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);

  useLayoutEffect(() => {
    if (!open || !wrapRef.current) {
      setMenuPos(null);
      return;
    }
    const update = () => {
      const el = wrapRef.current;
      if (!el) {
        return;
      }
      const rect = el.getBoundingClientRect();
      const gap = 4;
      setMenuPos({ top: rect.bottom + gap, right: window.innerWidth - rect.right });
    };
    update();
    const id = window.requestAnimationFrame(update);
    window.addEventListener('resize', update);
    window.addEventListener('scroll', update, true);
    return () => {
      window.cancelAnimationFrame(id);
      window.removeEventListener('resize', update);
      window.removeEventListener('scroll', update, true);
    };
  }, [open, items.length]);

  useEffect(() => {
    if (!open) {
      return;
    }
    const onPointer = (e: MouseEvent | TouchEvent) => {
      const t = e.target;
      if (!(t instanceof Node)) {
        return;
      }
      if (wrapRef.current?.contains(t) || menuRef.current?.contains(t)) {
        return;
      }
      setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', onPointer);
    document.addEventListener('touchstart', onPointer, { passive: true });
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onPointer);
      document.removeEventListener('touchstart', onPointer);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  if (items.length === 0) {
    return null;
  }

  const menu = open && menuPos ? (
    <div
      ref={menuRef}
      role="menu"
      style={{
        position: 'fixed',
        top: menuPos.top,
        right: menuPos.right,
        zIndex: 10000,
      }}
      className="min-w-[12rem] rounded-xl border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-black/5 dark:border-slate-700 dark:bg-slate-900 dark:ring-white/10"
    >
      {items.map((item) =>
        isLink(item) ? (
          <a
            key={item.key}
            role="menuitem"
            href={item.href}
            {...(item.external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
            className="block px-3 py-2 text-sm text-slate-700 no-underline hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
            onClick={() => setOpen(false)}
          >
            {item.label}
          </a>
        ) : (
          <button
            key={item.key}
            type="button"
            role="menuitem"
            className={`block w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800 ${
              item.danger ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-200'
            }`}
            onClick={() => {
              setOpen(false);
              item.onClick();
            }}
          >
            {item.label}
          </button>
        )
      )}
    </div>
  ) : null;

  return (
    <>
      <div ref={wrapRef} className="relative inline-block text-left">
        <button
          type="button"
          aria-expanded={open}
          aria-haspopup="menu"
          aria-label={ariaLabel}
          onClick={() => setOpen((o) => !o)}
          className="inline-flex rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        >
          <NavIcon name="dotsVertical" className="h-5 w-5" />
        </button>
      </div>
      {typeof document !== 'undefined' && menu ? createPortal(menu, document.body) : null}
    </>
  );
}
