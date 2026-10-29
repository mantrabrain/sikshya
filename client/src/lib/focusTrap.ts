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
 *
 * Every Tab inside the dialog is driven from {@see focusStops}, not just the
 * ones at the two ends. Intercepting only the edges assumes the browser's own
 * tab order visits the same elements we computed — and Safari's does not. With
 * macOS full keyboard access off (the default), WebKit's Tab order skips
 * buttons and links entirely, so the "last stop" we calculated from the DOM is
 * never reached, the edge never fires, and Tab walks straight out of the dialog
 * into the page behind it. Driving the whole cycle makes the order exactly DOM
 * order in every engine, which is also what a keyboard user expects.
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

  const index = stops.indexOf(active);
  if (index === -1) {
    // Focus is on the panel itself, or on something we do not consider a stop
    // (a container with `tabindex="-1"`, say). Enter the cycle at the end the
    // press is heading for.
    event.preventDefault();
    (event.shiftKey ? last : first).focus();
    return true;
  }

  const next = event.shiftKey
    ? stops[(index - 1 + stops.length) % stops.length]
    : stops[(index + 1) % stops.length];

  event.preventDefault();
  next.focus();

  return true;
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
  const remember = (el: HTMLElement | null): void => {
    if (!el || typeof el.closest !== 'function') return;
    if (el.closest('[role="dialog"]')) return;
    lastOutsideDialog = el;
  };

  document.addEventListener('focusin', (event) => remember(event.target as HTMLElement | null), true);

  /*
   * `focusin` alone is not enough, and the gap is a platform difference rather
   * than an edge case: on macOS, clicking a button does not focus it, so in
   * Safari opening a dialog with the mouse fires no `focusin` at all and we
   * have nothing to hand focus back to on close. Focus then lands on <body>
   * and a keyboard user has lost their place — the exact bug the returner
   * exists to prevent.
   *
   * What the pointer went down on is a better answer than what happened to be
   * focused anyway: it is the control the person actually used.
   */
  document.addEventListener(
    'pointerdown',
    (event) => {
      const target = event.target as HTMLElement | null;
      if (!target || typeof target.closest !== 'function') return;
      // Only an actual control, so clicking blank page chrome does not become
      // the place focus returns to.
      const control = target.closest<HTMLElement>(FOCUSABLE);
      remember(control);
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
  /*
   * `document.activeElement` is `<body>` whenever nothing is focused, which is
   * not a place to send anyone back to — handing focus to <body> is the very
   * "you lost your place" outcome this function exists to prevent. It is also
   * the normal reading in Safari, where clicking a button does not focus it,
   * so a dialog opened with the mouse captures <body> here and would discard
   * the trigger that `pointerdown` had correctly remembered.
   */
  const meaningful = active && active !== document.body && active !== document.documentElement ? active : null;
  const returnTo = insideDialog ? lastOutsideDialog : (meaningful ?? lastOutsideDialog);

  return () => {
    if (returnTo && document.contains(returnTo) && typeof returnTo.focus === 'function') {
      returnTo.focus();
    }
  };
}
