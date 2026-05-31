import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import type { WpPost } from '../types';

export type CreateDraftCourseOptions = {
  /** URL slug (WordPress sanitizes; omit to let core derive from title). */
  slug?: string;
  /** When `bundle`, marks the new post as a course bundle (meta) after creation. */
  kind?: 'regular' | 'bundle';
};

export type CreateDraftCourseResult = {
  id: number;
  /** Final slug as WordPress stored it (may differ from the requested slug). */
  slug: string;
};

/**
 * Create an empty `sik_course` draft via Sikshya REST (`course-builder/create-draft` → wp_insert_post),
 * then (for bundles) set course type via the Sikshya set-type endpoint.
 *
 * Returns both the new post ID and the *final* slug — WordPress sanitizes
 * (`my Cool Slug` → `my-cool-slug`) and resolves collisions (`react-tips`
 * → `react-tips-2`), so the slug the user typed isn't always what was saved.
 * Callers should compare to surface a "slug changed" notice to the user.
 */
export async function createDraftCourse(
  title: string,
  options: CreateDraftCourseOptions = {}
): Promise<CreateDraftCourseResult> {
  const trimmed = title.trim();
  if (!trimmed) {
    throw new Error('Please enter a course title.');
  }

  const body: Record<string, string> = {
    title: trimmed,
  };
  const slug = options.slug?.trim();
  if (slug) {
    body.slug = slug;
  }

  const post = await getSikshyaApi().post<WpPost & { success?: boolean }>(
    SIKSHYA_ENDPOINTS.courseBuilder.createDraft,
    body
  );

  const id = post?.id;
  if (typeof id !== 'number' || id <= 0) {
    throw new Error('Could not read new course ID from the server.');
  }

  if (options.kind === 'bundle') {
    // Use the dedicated Sikshya endpoint — directly calls update_post_meta,
    // bypassing fragile WP REST meta schema handling for _-prefixed keys.
    try {
      const res = await getSikshyaApi().post<{ success: boolean; message?: string }>(
        SIKSHYA_ENDPOINTS.courseBuilder.setType,
        { course_id: id, course_type: 'bundle' }
      );
      if (!res.success) {
        throw new Error(res.message || 'Failed to mark course as bundle.');
      }
    } catch (e) {
      // Rollback: the draft was created in the first call but the bundle-type
      // marker failed to save. Without cleanup the DB would accumulate orphan
      // drafts every time a user retried the bundle flow after a transient
      // error. Force-delete (no trash) so the post is fully gone — the user
      // can safely click "Create" again. Rollback failure itself is swallowed
      // so the original error is what surfaces to the UI.
      try {
        await getWpApi().delete(`/sik_course/${id}?force=true`);
      } catch {
        /* rollback best-effort; original failure is what the user needs to see */
      }
      throw e;
    }
  }

  // WP REST `WpPost.slug` is always present, but defensively coalesce to the
  // requested slug (if any) and finally to an empty string so the caller's
  // type signature is honoured.
  const finalSlug = typeof post?.slug === 'string' && post.slug ? post.slug : slug || '';
  return { id, slug: finalSlug };
}
