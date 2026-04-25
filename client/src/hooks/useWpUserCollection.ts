import { getWpApi } from '../api';
import type { WpRestUser } from '../types';
import { useAsyncData } from './useAsyncData';

export type WpUserCollectionQuery = {
  roleSlug: string;
  search: string;
  /** WordPress REST `orderby` (e.g. `name`, `registered_date`, `id`, `email`, `slug`). */
  orderby: string;
  order: 'asc' | 'desc';
  page?: number;
  perPage?: number;
  /** Optional bump to force a refetch. */
  refreshToken?: number;
};

/**
 * WordPress `/wp/v2/users` collection filtered by role.
 */
export function useWpUserCollection(query: WpUserCollectionQuery) {
  const { roleSlug, search, orderby, order, page = 1, perPage = 20, refreshToken } = query;

  return useAsyncData(async () => {
    const params = new URLSearchParams({
      per_page: String(perPage),
      page: String(page),
      context: 'edit',
      orderby,
      order,
      role: roleSlug,
    });
    const q = search.trim();
    if (q) {
      params.set('search', q);
    }
    const path = `/users?${params.toString()}`;
    return getWpApi().getWithTotal<WpRestUser[]>(path);
  }, [roleSlug, search, orderby, order, page, perPage, refreshToken]);
}
