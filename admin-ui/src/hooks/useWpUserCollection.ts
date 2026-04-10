import { getWpApi } from '../api';
import type { WpRestUser } from '../types';
import { useAsyncData } from './useAsyncData';

export type WpUserCollectionQuery = {
  roleSlug: string;
  search: string;
  orderby: 'name' | 'registered_date';
  order: 'asc' | 'desc';
  perPage?: number;
};

/**
 * WordPress `/wp/v2/users` collection filtered by role.
 */
export function useWpUserCollection(query: WpUserCollectionQuery) {
  const { roleSlug, search, orderby, order, perPage = 50 } = query;

  return useAsyncData(async () => {
    const params = new URLSearchParams({
      per_page: String(perPage),
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
  }, [roleSlug, search, orderby, order, perPage]);
}
