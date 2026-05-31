/**
 * Shared z-index scale for portaled admin UI (modals, toasts, confirms).
 * Keep confirm/toast above Modal so saves and destructive actions stay visible.
 * Focus-mode surface sits above #wpadminbar (99999) but below modals so dialogs
 * triggered from within focus mode still appear on top.
 */
export const OVERLAY_Z_FOCUS_SURFACE = 100050;
export const OVERLAY_Z_MODAL = 100090;
export const OVERLAY_Z_TOAST = 100095;
export const OVERLAY_Z_CONFIRM = 100100;
