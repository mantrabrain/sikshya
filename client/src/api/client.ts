import { ApiError, type HttpMethod } from './errors';

export type WpCollectionMeta = {
  total: number | null;
  totalPages: number | null;
};

export type HttpClient = {
  request: <T>(path: string, init?: RequestInit & { method?: HttpMethod }) => Promise<T>;
  get: <T>(path: string, init?: Omit<RequestInit, 'method' | 'body'>) => Promise<T>;
  /** GET and read `X-WP-Total` / `X-WP-TotalPages` (WordPress REST collections). */
  getWithTotal: <T>(path: string, init?: Omit<RequestInit, 'method' | 'body'>) => Promise<{ data: T } & WpCollectionMeta>;
  post: <T>(path: string, body?: unknown, init?: Omit<RequestInit, 'method' | 'body'>) => Promise<T>;
  put: <T>(path: string, body?: unknown, init?: Omit<RequestInit, 'method' | 'body'>) => Promise<T>;
  patch: <T>(path: string, body?: unknown, init?: Omit<RequestInit, 'method' | 'body'>) => Promise<T>;
  delete: <T>(path: string, init?: Omit<RequestInit, 'method' | 'body'>) => Promise<T>;
};

function splitQuery(path: string): { pathname: string; query: string } {
  const i = path.indexOf('?');
  if (i === -1) return { pathname: path, query: '' };
  return { pathname: path.slice(0, i), query: path.slice(i + 1) };
}

/**
 * Join a WP REST base with a request path.
 *
 * Supports both:
 * - Pretty REST: /wp-json/sikshya/v1
 * - Plain REST:  /index.php?rest_route=/sikshya/v1
 *
 * In "plain" mode, extra query params MUST be added as real query params,
 * not embedded inside `rest_route` (otherwise WP returns `rest_no_route`).
 */
function buildUrl(base: string, path: string): string {
  const { pathname, query } = splitQuery(path);
  const cleanPath = (pathname || '').trim();
  const baseUrl = new URL(base, window.location.origin);

  // Normalize incoming path.
  const p = cleanPath.startsWith('/') ? cleanPath : `/${cleanPath}`;

  if (baseUrl.searchParams.has('rest_route')) {
    const rr0 = baseUrl.searchParams.get('rest_route') || '';
    const rr = rr0.replace(/\/$/, '') + p;
    baseUrl.searchParams.set('rest_route', rr);
  } else {
    baseUrl.pathname = `${baseUrl.pathname.replace(/\/$/, '')}${p}`;
  }

  if (query) {
    const extra = new URLSearchParams(query);
    for (const [k, v] of extra.entries()) {
      baseUrl.searchParams.set(k, v);
    }
  }

  return baseUrl.toString();
}

function parseTotalHeader(res: Response, name: string): number | null {
  const raw = res.headers.get(name);
  if (raw === null || raw === '') {
    return null;
  }
  const n = parseInt(raw, 10);
  return Number.isFinite(n) ? n : null;
}

/**
 * Generic JSON HTTP client with WP REST nonce header.
 */
export function createHttpClient(
  resolveBaseUrl: () => string,
  resolveNonce: () => string
): HttpClient {
  function tryExtractJsonFromRawBody(rawBody: string): unknown | null {
    // Some endpoints may emit PHP warnings/notices before the JSON error payload.
    // Try to recover the trailing JSON object so the UI can show a useful message.
    const firstBrace = rawBody.indexOf('{');
    const lastBrace = rawBody.lastIndexOf('}');
    if (firstBrace === -1 || lastBrace === -1 || lastBrace <= firstBrace) {
      return null;
    }
    const candidate = rawBody.slice(firstBrace, lastBrace + 1).trim();
    try {
      return JSON.parse(candidate) as unknown;
    } catch {
      // If there are multiple JSON-like chunks, try the last object.
      const lastObjStart = rawBody.lastIndexOf('{');
      if (lastObjStart !== -1 && lastObjStart < lastBrace) {
        const candidate2 = rawBody.slice(lastObjStart, lastBrace + 1).trim();
        try {
          return JSON.parse(candidate2) as unknown;
        } catch {
          return null;
        }
      }
      return null;
    }
  }

  function resolveErrorMessage(data: unknown, fallback: string): string {
    if (data && typeof data === 'object' && 'message' in data && typeof (data as { message: string }).message === 'string') {
      return (data as { message: string }).message;
    }

    // If parsing failed earlier, we may have { rawBody: "<html warnings>...{json}"}.
    if (
      data &&
      typeof data === 'object' &&
      'rawBody' in data &&
      typeof (data as { rawBody?: unknown }).rawBody === 'string'
    ) {
      const extracted = tryExtractJsonFromRawBody((data as { rawBody: string }).rawBody);
      if (
        extracted &&
        typeof extracted === 'object' &&
        'message' in extracted &&
        typeof (extracted as { message: string }).message === 'string'
      ) {
        return (extracted as { message: string }).message;
      }
    }

    return fallback;
  }

  async function performFetch(
    path: string,
    init: RequestInit & { method?: HttpMethod } = {}
  ): Promise<{ res: Response; data: unknown }> {
    const method = (init.method || 'GET').toUpperCase() as HttpMethod;
    const url = buildUrl(resolveBaseUrl(), path);

    const headers: Record<string, string> = {
      'X-WP-Nonce': resolveNonce(),
      ...(init.headers as Record<string, string>),
    };

    if (init.body && !(init.body instanceof FormData) && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }

    const res = await fetch(url, {
      ...init,
      method,
      credentials: 'same-origin',
      headers,
    });

    const text = await res.text();
    let parsed: unknown = null;
    if (text) {
      try {
        parsed = JSON.parse(text) as unknown;
      } catch {
        parsed = { rawBody: text };
      }
    }

    return { res, data: parsed };
  }

  async function request<T>(
    path: string,
    init: RequestInit & { method?: HttpMethod } = {}
  ): Promise<T> {
    const method = (init.method || 'GET').toUpperCase() as HttpMethod;
    const url = buildUrl(resolveBaseUrl(), path);
    const { res, data } = await performFetch(path, init);

    if (!res.ok) {
      const msg = resolveErrorMessage(data, res.statusText || 'Request failed');

      throw new ApiError(msg || 'Request failed', {
        status: res.status,
        body: data,
        url,
        method,
      });
    }

    return data as T;
  }

  async function getWithTotal<T>(
    path: string,
    init: Omit<RequestInit, 'method' | 'body'> = {}
  ): Promise<{ data: T } & WpCollectionMeta> {
    const method = 'GET' as HttpMethod;
    const url = buildUrl(resolveBaseUrl(), path);
    const { res, data } = await performFetch(path, { ...init, method });

    if (!res.ok) {
      const msg = resolveErrorMessage(data, res.statusText || 'Request failed');

      throw new ApiError(msg || 'Request failed', {
        status: res.status,
        body: data,
        url,
        method,
      });
    }

    return {
      data: data as T,
      total: parseTotalHeader(res, 'X-WP-Total'),
      totalPages: parseTotalHeader(res, 'X-WP-TotalPages'),
    };
  }

  return {
    request,
    get: (path, init) => request(path, { ...init, method: 'GET' }),
    getWithTotal,
    post: (path, body, init) =>
      request(path, {
        ...init,
        method: 'POST',
        body: body === undefined ? undefined : JSON.stringify(body),
      }),
    put: (path, body, init) =>
      request(path, {
        ...init,
        method: 'PUT',
        body: body === undefined ? undefined : JSON.stringify(body),
      }),
    patch: (path, body, init) =>
      request(path, {
        ...init,
        method: 'PATCH',
        body: body === undefined ? undefined : JSON.stringify(body),
      }),
    delete: (path, init) => request(path, { ...init, method: 'DELETE' }),
  };
}
