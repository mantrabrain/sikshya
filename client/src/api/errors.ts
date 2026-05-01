export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

/**
 * Normalized API failure (Sikshya or WP REST).
 */
export class ApiError extends Error {
  readonly status: number;
  readonly body: unknown;
  readonly url: string;
  readonly method: HttpMethod;

  constructor(
    message: string,
    options: { status: number; body: unknown; url: string; method: HttpMethod }
  ) {
    super(message);
    this.name = 'ApiError';
    this.status = options.status;
    this.body = options.body;
    this.url = options.url;
    this.method = options.method;
  }
}

function wpMessage(body: unknown): string | null {
  if (!body || typeof body !== 'object') {
    return null;
  }
  const b = body as { message?: string; code?: string };
  if (typeof b.message === 'string' && b.message.length) {
    return b.message;
  }
  return null;
}

/** WP REST `code` from error JSON body, when present. */
export function getWpRestErrorCode(error: unknown): string | null {
  if (!(error instanceof ApiError)) {
    return null;
  }
  const b = error.body;
  if (!b || typeof b !== 'object') {
    return null;
  }
  const c = (b as { code?: string }).code;
  return typeof c === 'string' && c.length ? c : null;
}

/** Current REST code when a catalog feature is not on the active plan. */
export const SIKSHYA_PLAN_FEATURE_REQUIRED = 'sikshya_plan_feature_required' as const;

/** @deprecated Recognize for backward compatibility with older integrations. */
export const SIKSHYA_PLAN_FEATURE_REQUIRED_LEGACY = 'sikshya_pro_required' as const;

/**
 * True when the API indicates the site plan does not include the requested feature
 * (403 plan gate). Recognizes both {@link SIKSHYA_PLAN_FEATURE_REQUIRED} and the legacy code.
 */
export function isPlanFeatureRequiredError(error: unknown): boolean {
  const code = getWpRestErrorCode(error);
  if (code === SIKSHYA_PLAN_FEATURE_REQUIRED || code === SIKSHYA_PLAN_FEATURE_REQUIRED_LEGACY) {
    return true;
  }
  if (!(error instanceof ApiError) || !error.body || typeof error.body !== 'object') {
    return false;
  }
  const body = error.body as {
    legacy_error_code?: string;
    data?: { legacy_error_code?: string };
  };
  if (body.legacy_error_code === SIKSHYA_PLAN_FEATURE_REQUIRED_LEGACY) {
    return true;
  }
  return body.data?.legacy_error_code === SIKSHYA_PLAN_FEATURE_REQUIRED_LEGACY;
}

/**
 * Use a short toast instead of the large {@link ApiErrorPanel}.
 * Keep the full support panel for server errors (5xx) and validation-style 422 responses.
 */
export function preferToastForApiError(error: unknown): boolean {
  if (error instanceof ApiError) {
    const { status } = error;
    if (status >= 500) {
      return false;
    }
    if (status === 422) {
      return false;
    }
    return true;
  }
  if (error instanceof TypeError) {
    return true;
  }
  if (error instanceof Error) {
    const m = error.message.toLowerCase();
    return m.includes('failed to fetch') || m.includes('networkerror') || m.includes('load failed');
  }
  return true;
}

/** Short toast title for auth/session failures. */
export function getApiErrorToastTitle(error: unknown): string {
  if (error instanceof ApiError) {
    const code = getWpRestErrorCode(error);
    if (error.status === 401 || error.status === 403 || code === 'rest_cookie_invalid_nonce') {
      return 'Session expired';
    }
  }
  return 'Request failed';
}

/** Short line for inline UI. */
export function getErrorSummary(error: unknown): string {
  if (error instanceof ApiError) {
    return wpMessage(error.body) || error.message || `Request failed (${error.status})`;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return 'An unexpected error occurred.';
}

export type ShareableErrorReport = {
  /** One or two sentences for the user. */
  summary: string;
  /** Full blob to copy (email, ticket, Slack). */
  fullText: string;
  /** Pretty JSON for the details panel. */
  detailsJson: string;
};

/**
 * Build a support-ready error report (copy/paste friendly).
 */
export function formatShareableErrorReport(error: unknown): ShareableErrorReport {
  const summary = getErrorSummary(error);
  const lines: string[] = [
    'Sikshya LMS — error report',
    `Time: ${new Date().toISOString()}`,
    `Page: ${typeof window !== 'undefined' ? window.location.href : ''}`,
  ];

  let details: Record<string, unknown> = { summary };

  if (error instanceof ApiError) {
    lines.push(`Request: ${error.method} ${error.url}`);
    lines.push(`HTTP status: ${error.status}`);
    lines.push(`Message: ${summary}`);
    details = {
      summary,
      method: error.method,
      url: error.url,
      status: error.status,
      response: error.body,
    };
  } else if (error instanceof Error) {
    lines.push(`Error name: ${error.name}`);
    lines.push(`Message: ${error.message}`);
    if (error.stack) {
      details.stack = error.stack;
    }
  } else {
    details.raw = error;
  }

  let detailsJson: string;
  try {
    detailsJson = JSON.stringify(details, null, 2);
  } catch {
    detailsJson = String(details);
  }

  lines.push('');
  lines.push('Details (JSON):');
  lines.push(detailsJson);

  return {
    summary,
    fullText: lines.join('\n'),
    detailsJson,
  };
}
