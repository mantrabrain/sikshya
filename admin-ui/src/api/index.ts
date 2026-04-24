import { getConfig } from '../config/env';
import { createHttpClient, type HttpClient } from './client';

export { SIKSHYA_ENDPOINTS, WP_ENDPOINTS } from './endpoints';
export {
  ApiError,
  formatShareableErrorReport,
  getErrorSummary,
  type HttpMethod,
  type ShareableErrorReport,
} from './errors';
export { createHttpClient, type HttpClient, type WpCollectionMeta } from './client';

let sikshyaClient: HttpClient | null = null;
let wpV2Client: HttpClient | null = null;

/** Sikshya plugin REST (`sikshya/v1`). */
export function getSikshyaApi(): HttpClient {
  if (!sikshyaClient) {
    sikshyaClient = createHttpClient(
      () => getConfig().restUrl,
      () => getConfig().restNonce
    );
  }
  return sikshyaClient;
}

/** WordPress core REST (`wp/v2`). */
export function getWpApi(): HttpClient {
  if (!wpV2Client) {
    wpV2Client = createHttpClient(
      () =>
        String(getConfig().wpRestUrl || '').trim() ||
        `${getConfig().siteUrl.replace(/\/$/, '')}/wp-json/wp/v2`,
      () => getConfig().restNonce
    );
  }
  return wpV2Client;
}
