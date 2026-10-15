import type { APIRequestContext, Browser, BrowserContext, Page } from '@playwright/test';

/**
 * Open a fresh browser context, sign a learner in, and return their cookie-authed
 * APIRequestContext plus the `sikshyaFrontend.restNonce` from a frontend page.
 *
 * The identity check at the end is not ceremony. Around twenty specs build a
 * learner session this way and then assert things like "this learner sees only
 * their own data" — assertions that pass trivially if the session silently
 * belongs to somebody else, or to nobody.
 */
export async function studentSession(
  browser: Browser,
  baseURL: string,
  username: string,
  password: string,
): Promise<{
  context: BrowserContext;
  page: Page;
  request: APIRequestContext;
  restNonce: string;
  userId: number;
}> {
  const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });

  /*
   * `browser.newContext()` starts from the project's `storageState`, which is the
   * administrator's session — so a context created here is *not* empty, it holds
   * the admin's `wordpress_logged_in_*` cookie at path `/`. Signing a student in
   * on top of that left both sessions' cookies in the jar, and which one the
   * server honoured varied by request, so a "student" session intermittently
   * resolved to the administrator. Specs then asserted learner behaviour while
   * acting as an admin, which passes for the wrong reason and hides real
   * permission bugs. Clearing the jar first is what makes this a learner session.
   */
  await context.clearCookies();

  const page = await context.newPage();

  /*
   * The whole sign-in is attempted more than once.
   *
   * An earlier version retried only the `/users/me` identity read. That cannot
   * help when the problem is the login itself: on a server with few PHP workers
   * the form POST sometimes does not complete inside its wait, the page stays on
   * wp-login.php with no error banner, and no amount of re-reading a nonce will
   * conjure a session that was never established. Re-submitting the form is the
   * only thing that recovers it, so the attempt loop wraps submit *and* verify.
   */
  const attempts = 3;
  let lastReason = '';

  for (let attempt = 1; attempt <= attempts; attempt++) {
    const outcome = await attemptLogin(context, page, username, password);

    if (outcome.rejected) {
      // Bad credentials are not a timing problem; retrying cannot fix them and
      // would only turn a clear failure into a slow one.
      await context.close();
      throw new Error(
        `studentSession: WordPress rejected the login for "${username}": ${outcome.rejected}`,
      );
    }

    if (outcome.userId > 0) {
      const resolved = outcome.resolvedName;
      if (resolved !== username.toLowerCase()) {
        await context.close();
        throw new Error(
          `studentSession: asked for "${username}" but the session resolved to "${resolved}" ` +
            `(user id ${outcome.userId}). Refusing to hand back a session for the wrong account.`,
        );
      }

      return {
        context,
        page,
        request: context.request,
        restNonce: outcome.restNonce,
        userId: outcome.userId,
      };
    }

    lastReason = outcome.reason;
    if (attempt < attempts) {
      await page.waitForTimeout(1_000 * attempt);
    }
  }

  await context.close();
  throw new Error(
    `studentSession: could not establish a session for "${username}" after ${attempts} attempts. ${lastReason}`,
  );
}

/**
 * One submit-and-verify cycle.
 *
 * Returns the resolved user on success, a rejection message when WordPress said
 * the credentials were wrong, or a reason string when the attempt simply did not
 * land and is worth repeating.
 */
async function attemptLogin(
  context: BrowserContext,
  page: Page,
  username: string,
  password: string,
): Promise<{ userId: number; resolvedName: string; restNonce: string; rejected: string; reason: string }> {
  const miss = (reason: string) => ({ userId: 0, resolvedName: '', restNonce: '', rejected: '', reason });

  await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });

  // Already signed in from a previous attempt? WordPress redirects away from the
  // form, so there is nothing to fill.
  const hasForm = (await page.locator('#user_login').count()) > 0;
  if (hasForm) {
    await page.locator('#user_login').fill(username);
    await page.locator('#user_pass').fill(password);

    /*
     * Wait for whichever signal arrives first: the login POST response, or the
     * navigation away from the form. Waiting only for the URL change let a slow
     * POST still be in flight when the nonce was read, giving a logged-out nonce.
     * Waiting only for the POST is worse — the `isNavigationRequest()` predicate
     * does not match in WebKit, so every login burned its full timeout and the
     * WebKit suite went from 20 minutes to 1.3 hours.
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
      .textContent({ timeout: 1_000 })
      .catch(() => null);
    if (loginError && loginError.trim() !== '') {
      return { userId: 0, resolvedName: '', restNonce: '', rejected: loginError.trim(), reason: '' };
    }
  }

  // Visit the frontend so the `sikshyaFrontend` localization is in the page.
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  const restNonce = await page.evaluate(() => {
    const w = window as unknown as { sikshyaFrontend?: { restNonce?: string } };
    return w.sikshyaFrontend?.restNonce ?? '';
  });

  const meRes = await context.request.get('/wp-json/wp/v2/users/me', {
    headers: restNonce ? { 'X-WP-Nonce': restNonce } : {},
  });

  if (meRes.status() === 401) {
    return miss('The session was not authenticated (/users/me returned 401).');
  }
  if (!meRes.ok()) {
    return miss(`/users/me returned ${meRes.status()}.`);
  }

  const meBody = (await meRes.json().catch(() => ({}))) as {
    id?: number;
    slug?: string;
    username?: string;
  };
  const userId = Number(meBody.id ?? 0);
  if (userId <= 0) {
    return miss('/users/me returned no user id.');
  }

  return {
    userId,
    resolvedName: String(meBody.username ?? meBody.slug ?? '').toLowerCase(),
    restNonce,
    rejected: '',
    reason: '',
  };
}
