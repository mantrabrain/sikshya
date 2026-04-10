import { getSikshyaApi } from '../api';
import { SIKSHYA_ENDPOINTS } from '../api/endpoints';
import { useAsyncData } from './useAsyncData';

/** WordPress REST `status` query values for collections. */
export type WpPostCollectionStatus =
  | 'any'
  | 'publish'
  | 'draft'
  | 'pending'
  | 'future'
  | 'private'
  | 'trash';

type StatusCountsPayload = Record<WpPostCollectionStatus, number>;

const EMPTY: StatusCountsPayload = {
  any: 0,
  publish: 0,
  draft: 0,
  pending: 0,
  future: 0,
  private: 0,
  trash: 0,
};

/**
 * Tab totals from Sikshya `admin/post-status-counts` (one request instead of N wp/v2 HEADs).
 * List rows still load via {@link useWpPostCollection} when status/search/sort changes.
 */
export function useWpPostStatusCounts(restBase: string) {
  return useAsyncData(async () => {
    try {
      const path = SIKSHYA_ENDPOINTS.admin.postStatusCounts(restBase);
      const data = await getSikshyaApi().get<Partial<StatusCountsPayload>>(path);
      return { ...EMPTY, ...data };
    } catch {
      return { ...EMPTY };
    }
  }, [restBase]);
}
