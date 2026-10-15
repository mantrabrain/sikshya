import type { APIRequestContext, Browser } from '@playwright/test';

/**
 * Open a fresh browser context, log a student in, and return their cookie-authed
 * APIRequestContext plus the sikshyaFrontend.restNonce extracted from a frontend page.
 *
 * The identity check at the end is not ceremony. Around twenty specs build a
 * learner session this way and then assert things like "this learner sees only
 * their own data" — assertions that pass trivially if the session silently
 * belongs to somebody else, or to nobody. A failed login used to sail through
 * here: `waitForURL(/wp-admin|wp-login\.php|\//)` matches the login page it
 * started on *and* matches every other URL, so it could not distinguish success
 * from failure, and the caller went on to make requests as a guest or as
 * whoever the ambient session happened to be. Confirming the resolved user
 * before handing the session back turns that class of silent wrong-identity bug
 * into an immediate, named failure.
 */
export async function studentSession(
  browser: Browser,
  baseURL: string,
  username: string,
  password: string,
): Promise<{
  context: import('@playwright/test').BrowserContext;
  page: import('@playwright/test').Page;
  request: APIRequestContext;
  restNonce: string;
  userId: number;
}> {
  const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });

  // `browser.newContext()` starts from the project's `storageState`, which is the
  // administrator's session — so a context created here is *not* empty, it holds
  // the admin's `wordpress_logged_in_*` cookie at path `/`. Logging a student in
  // on top of that left both sessions' cookies in the jar, and which one the
  // server honoured varied by request, so a "student" session intermittently
  // resolved to the administrator. Specs then asserted learner behaviour while
  // acting as an admin, which passes for the wrong reason and hides real
  // permission bugs. Clearing the jar first is what makes this a learner session.
  await context.clearCookies();

  const page = await context.newPage();

  await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);

  /*
   * Wait for whichever signal arrives first: the login POST response, or the
   * navigation away from the form.
   *
   * Waiting only for the URL change let a slow POST still be in flight when the
   * nonce was read below, producing a logged-out nonce and an intermittent 401
   * that read as "no authenticated session" rather than "the login was still
   * going". But waiting only for the POST is worse: the
   * `isNavigationRequest()` predicate does not match in WebKit, so every login
   * burned its full timeout and the WebKit suite went from 20 minutes to 1.3
   * hours. Racing the two is fast on every engine and still cannot proceed with
   * a half-finished login.
   */
  await Promise.all([
    Promise.race([
      page
        .waitForResponse(
          (r) => r.request().method() === 'POST' && r.request().isNavigationRequest(),
          { timeout: 30_000 },
        )
        .catch(() => undefined),
      page
        .waitForURL((url) => !/wp-login\.php/.test(url.pathname), { timeout: 30_000 })
        .catch(() => undefined),
    ]),
    page.locator('#wp-submit').click(),
  ]);

  const loginError = await page
    .locator('#login_error')
    .textContent({ timeout: 1000 })
    .catch(() => null);
  if (loginError) {
    await context.close();
    throw new Error(
      `studentSession: WordPress rejected the login for "${username}": ${loginError.trim()}`,
    );
  }

  // Visit the frontend so the `sikshyaFrontend` localization is in the page.
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  const restNonce = await page.evaluate(() => {
    const w = window as unknown as { sikshyaFrontend?: { restNonce?: string } };
    return w.sikshyaFrontend?.restNonce ?? '';
  });

  // Confirm the session really belongs to the requested account.
  const meRes = await context.request.get('/wp-json/wp/v2/users/me', {
    headers: restNonce ? { 'X-WP-Nonce': restNonce } : {},
  });
  const meBody = (await meRes.json().catch(() => ({}))) as {
    id?: number;
    slug?: string;
    username?: string;
  };
  if (!meRes.ok()) {
    await context.close();
    throw new Error(
      `studentSession: no authenticated session for "${username}" (/users/me returned ${meRes.status()}).`,
    );
  }

  const resolved = String(meBody.username ?? meBody.slug ?? '').toLowerCase();
  const expected = username.toLowerCase();
  if (resolved !== expected) {
    await context.close();
    throw new Error(
      `studentSession: asked for "${username}" but the session resolved to "${resolved}" ` +
        `(user id ${meBody.id ?? 0}). Refusing to hand back a session for the wrong account.`,
    );
  }

  return { context, page, request: context.request, restNonce, userId: Number(meBody.id ?? 0) };
}
