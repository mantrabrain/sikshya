/**
 * WordPress appends `__trashed` to `post_name` while a post is in trash so the slug can be reused.
 * Show the original-looking slug in admin lists (the slug is restored on untrash).
 */
export function formatDisplaySlug(slug: string | undefined, status: string): string {
  if (!slug) {
    return '';
  }
  if (status === 'trash' && slug.endsWith('__trashed')) {
    return slug.slice(0, -'__trashed'.length);
  }
  return slug;
}
