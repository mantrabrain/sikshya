import { getWpApi } from '../api';
import type { WpPost } from '../types';

export type CreateDraftCourseOptions = {
  /** URL slug (WordPress sanitizes; omit to let core derive from title). */
  slug?: string;
};

/**
 * Create an empty `sik_course` post in draft via WordPress REST API.
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

  return id;
}
