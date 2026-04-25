/**
 * Client-side slug suggestion (WordPress will still sanitize/unique-ify on save).
 */
export function slugFromTitle(title: string): string {
  const raw = title
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 180);

  return raw || 'course';
}
