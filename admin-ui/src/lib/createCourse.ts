import { getSikshyaApi, getWpApi, SIKSHYA_ENDPOINTS } from '../api';
import type { WpPost } from '../types';

export type CreateDraftCourseOptions = {
  /** URL slug (WordPress sanitizes; omit to let core derive from title). */
  slug?: string;
  /** When `bundle`, marks the new post as a course bundle (meta) after creation. */
  kind?: 'regular' | 'bundle';
};

/**
 * Create an empty `sik_course` post in draft via WordPress REST API,
 * then (for bundles) reliably set the course type via the Sikshya set-type endpoint.
 */
export async function createDraftCourse(title: string, options: CreateDraftCourseOptions = {}): Promise<number> {
  const trimmed = title.trim();
  if (!trimmed) {
    throw new Error('Please enter a course title.');
  }

  const body: Record<string, string> = {
    title: trimmed,
    status: 'draft',
    content: '',
  };
  const slug = options.slug?.trim();
  if (slug) {
    body.slug = slug;
  }

  const post = await getWpApi().post<WpPost>('/sik_course', body);

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
