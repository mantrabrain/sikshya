/**
 * Keeping the focus ring inside an open dialog.
 *
 * `aria-modal="true"` tells a screen reader that nothing outside the dialog
 * exists, but it does not move focus: without a trap, Tab walks past the last
 * field and out into the page behind the backdrop, where a keyboard user is
 * operating controls they cannot see and a screen-reader user is being read a
 * page the browser was just told to ignore.
 */

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

/** Visible, reachable focus stops inside `panel`, in tab order. */
export function focusStops(panel: HTMLElement): HTMLElement[] {
  return Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(
    // `offsetParent` is null for anything `display:none`, which is how a
    // collapsed section's fields stay out of the cycle.
    (el) => el.offsetParent !== null || el === document.activeElement
  );
}

/**
 * Handle one Tab press for a dialog. Returns true when the event was consumed,
 * so the caller knows not to let it through.
 */
export function trapTab(panel: HTMLElement | null, event: KeyboardEvent): boolean {
  if (!panel || event.key !== 'Tab') return false;

  const stops = focusStops(panel);
  if (stops.length === 0) return false;

  const first = stops[0];
  const last = stops[stops.length - 1];
  const active = document.activeElement as HTMLElement | null;

  // Focus has escaped already (or never arrived) — pull it back in.
  if (!active || !panel.contains(active)) {
    event.preventDefault();
    (event.shiftKey ? last : first).focus();
    return true;
  }
  if (event.shiftKey && active === first) {
    event.preventDefault();
    last.focus();
    return true;
  }
  if (!event.shiftKey && active === last) {
    event.preventDefault();
    first.focus();
    return true;
  }

  return false;
}

/**
 * The last thing focused outside any dialog — i.e. whatever opened the one
 * that is now on screen.
 *
 * Tracked here rather than read from `document.activeElement` when a dialog
 * mounts, because React runs a child's effects before its parent's: by the
 * time the dialog shell can look, the content inside it has often already
 * focused its first field. Capturing that field as the "return to" target
 * means returning focus to an element that is about to be removed, which is
 * exactly how the focus ended up on `<body>` instead of on the button.
 */
let lastOutsideDialog: HTMLElement | null = null;

if (typeof document !== 'undefined') {
  document.addEventListener(
    'focusin',
    (event) => {
      const el = event.target as HTMLElement | null;
      if (!el || typeof el.closest !== 'function') return;
      if (el.closest('[role="dialog"]')) return;
      lastOutsideDialog = el;
    },
    true
  );
}

/**
 * Remember where focus was, and give it back.
 *
 * Closing a dialog used to drop focus to the top of the document, so a
 * keyboard user had to tab all the way back to the button they had just
 * pressed — every time.
 */
export function focusReturner(): () => void {
  const active = document.activeElement as HTMLElement | null;
  const insideDialog = Boolean(active && typeof active.closest === 'function' && active.closest('[role="dialog"]'));
  const returnTo = insideDialog ? lastOutsideDialog : (active ?? lastOutsideDialog);

  return () => {
    if (returnTo && document.contains(returnTo) && typeof returnTo.focus === 'function') {
      returnTo.focus();
    }
  };
}
