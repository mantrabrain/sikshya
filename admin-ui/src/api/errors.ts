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
