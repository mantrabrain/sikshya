import type { APIRequestContext } from '@playwright/test';

/**
 * Mailpit helper.
 *
 * Local serves a Mailpit instance per site, each on its own port, so the base
 * URL is configurable and defaults to this site's. Specs that use it skip when
 * it is unreachable rather than failing, so the suite still runs on a machine or
 * CI box without a mail catcher.
 *
 *   MAILPIT_URL=http://localhost:10015 npx playwright test
 */
export const MAILPIT_URL = process.env.MAILPIT_URL ?? 'http://localhost:10015';

export interface MailMessage {
  ID: string;
  Subject: string;
  To: Array<{ Address: string; Name?: string }>;
  From?: { Address: string; Name?: string };
}

export async function mailpitAvailable(request: APIRequestContext): Promise<boolean> {
  try {
    const res = await request.get(`${MAILPIT_URL}/api/v1/messages?limit=1`, { timeout: 4000 });
    return res.ok();
  } catch {
    return false;
  }
}

/**
 * Poll Mailpit until a message matching `query` arrives, or time out.
 *
 * Mail is dispatched during the request that triggered it, but the SMTP hop is
 * not instantaneous, so a poll is required rather than a single read.
 *
 * One trigger often fans out to several recipients, and those messages do not
 * land together. Returning on the first match would then hand the caller a
 * partial set and make "the learner was never mailed" indistinguishable from
 * "the learner's copy is still in flight", so `minCount` lets a caller that
 * knows how many messages to expect wait for all of them.
 */
export async function waitForMail(
  request: APIRequestContext,
  query: string,
  { timeoutMs = 20_000, intervalMs = 500, minCount = 1 } = {},
): Promise<MailMessage[]> {
  const deadline = Date.now() + timeoutMs;
  let last: MailMessage[] = [];

  while (Date.now() < deadline) {
    const res = await request.get(
      `${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}&limit=50`,
      { timeout: 5000 },
    );
    if (res.ok()) {
      const body = await res.json().catch(() => ({}));
      last = (body?.messages ?? []) as MailMessage[];
      if (last.length >= minCount) {
        return last;
      }
    }
    await new Promise((r) => setTimeout(r, intervalMs));
  }

  return last;
}

/** Recipients across a set of messages, lowercased. */
export function recipientsOf(messages: MailMessage[]): string[] {
  return messages.flatMap((m) => (m.To ?? []).map((t) => (t.Address ?? '').toLowerCase()));
}

/** Subjects across a set of messages. */
export function subjectsOf(messages: MailMessage[]): string[] {
  return messages.map((m) => m.Subject ?? '');
}
