import { getConfig } from './api';

export async function wpV2Fetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const { siteUrl, restNonce } = getConfig();
  const base = siteUrl.replace(/\/$/, '');
  const url = `${base}/wp-json/wp/v2${path.startsWith('/') ? path : '/' + path}`;
  const headers: Record<string, string> = {
    'X-WP-Nonce': restNonce,
    ...(init.headers as Record<string, string>),
  };
  const res = await fetch(url, {
    ...init,
    credentials: 'same-origin',
    headers,
  });
  const data = (await res.json().catch(() => ({}))) as T;
  if (!res.ok) {
    const err = new Error((data as { message?: string }).message || res.statusText) as Error & {
      status: number;
      body: unknown;
    };
    err.status = res.status;
    err.body = data;
    throw err;
  }
  return data;
}
