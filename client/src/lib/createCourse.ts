import { getSikshyaApi, SIKSHYA_ENDPOINTS } from '../api';
import type { WpPost } from '../types';

export type CreateDraftCourseOptions = {
  /** URL slug (WordPress sanitizes; omit to let core derive from title). */
  slug?: string;
  /** When `bundle`, marks the new post as a course bundle (meta) after creation. */
  kind?: 'regular' | 'bundle';
};

/**
 * Create an empty `sik_course` draft via Sikshya REST (`course-builder/create-draft` → wp_insert_post),
 * then (for bundles) set course type via the Sikshya set-type endpoint.
 */
export async function createDraftCourse(title: string, options: CreateDraftCourseOptions = {}): Promise<number> {
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
    const res = await getSikshyaApi().post<{ success: boolean; message?: string }>(
      SIKSHYA_ENDPOINTS.courseBuilder.setType,
      { course_id: id, course_type: 'bundle' }
    );
    if (!res.success) {
      throw new Error(res.message || 'Failed to mark course as bundle.');
    }
  }

  return id;
}
