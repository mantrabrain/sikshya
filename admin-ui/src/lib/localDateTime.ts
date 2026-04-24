export function parseDateInput(value: string | null | undefined): Date | null {
  const v = String(value || '').trim();
  if (!v) return null;
  // Expected: YYYY-MM-DD
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
  if (!m) return null;
  const y = Number(m[1]);
  const mo = Number(m[2]);
  const d = Number(m[3]);
  if (!Number.isFinite(y) || !Number.isFinite(mo) || !Number.isFinite(d)) return null;
  const dt = new Date(y, mo - 1, d, 0, 0, 0, 0);
  return Number.isFinite(dt.getTime()) ? dt : null;
}

export function formatDateInput(dt: Date | null): string {
  if (!dt) return '';
  const y = dt.getFullYear();
  const m = String(dt.getMonth() + 1).padStart(2, '0');
  const d = String(dt.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

export function parseDateTimeLocalInput(value: string | null | undefined): Date | null {
  const v = String(value || '').trim();
  if (!v) return null;
  // Expected: YYYY-MM-DDTHH:mm (seconds ignored if present)
  const m = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/.exec(v);
  if (!m) return null;
  const y = Number(m[1]);
  const mo = Number(m[2]);
  const d = Number(m[3]);
  const hh = Number(m[4]);
  const mm = Number(m[5]);
  const ss = m[6] ? Number(m[6]) : 0;
  if (![y, mo, d, hh, mm, ss].every((n) => Number.isFinite(n))) return null;
  const dt = new Date(y, mo - 1, d, hh, mm, ss, 0);
  return Number.isFinite(dt.getTime()) ? dt : null;
}

export function formatDateTimeLocalInput(dt: Date | null): string {
  if (!dt) return '';
  const y = dt.getFullYear();
  const m = String(dt.getMonth() + 1).padStart(2, '0');
  const d = String(dt.getDate()).padStart(2, '0');
  const hh = String(dt.getHours()).padStart(2, '0');
  const mm = String(dt.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${d}T${hh}:${mm}`;
}

