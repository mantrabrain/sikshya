import type { Browser, BrowserContext } from '@playwright/test';

/**
 * A browser context that is genuinely signed out.
 *
 * `browser.newContext()` starts from the project's `storageState`, which in this
 * suite is the administrator's session — so a context created that way is not
 * empty, it already holds the admin's `wordpress_logged_in_*` cookie at path `/`.
 * Signing a different user in on top leaves both sessions' cookies in the jar and
 * the server honours whichever it sees first.
 *
 * That is how a "student" session once resolved to the administrator, and why an
 * enrolment got recorded against `admin` while the spec believed it was acting as
 * a learner. The failure mode is worse than a crash: an isolation assertion such
 * as "a vendor sees only their own data" can pass while never exercising a
 * vendor at all.
 *
 * Use this anywhere a spec needs a second identity. Probing a fresh context with
 * `/wp-json/wp/v2/users/me` is *not* a sufficient check — it answers 401 even
 * when admin cookies are present, because REST cookie auth also needs a nonce.
 * Inspect `context.cookies()` instead if you need to confirm.
 */
export async function freshContext(
  browser: Browser,
  baseURL?: string,
): Promise<BrowserContext> {
  // Only set `baseURL` when the caller supplies one. Context options are
  // inherited from the project config the same way `storageState` is, and
  // hardcoding a default here would silently point a context at the wrong host
  // on a site configured through WP_BASE_URL.
  const context = await browser.newContext(
    baseURL ? { baseURL, ignoreHTTPSErrors: true } : { ignoreHTTPSErrors: true },
  );
  await context.clearCookies();

  return context;
}

/**
 * A browser context that deliberately *keeps* the administrator session.
 *
 * Some hooks need a page purely to read an admin REST nonce — setting up
 * fixtures, restoring settings afterwards. Those want the inherited
 * `storageState`, and clearing it breaks them with "No wpApiSettings.nonce".
 *
 * This exists so that intent is visible at the call site: `adminContext()` says
 * "I am the admin on purpose", `freshContext()` says "I am somebody else". A
 * bare `browser.newContext()` says neither, and the difference is invisible
 * until an isolation assertion quietly stops testing anything.
 */
export async function adminContext(
  browser: Browser,
  baseURL?: string,
): Promise<BrowserContext> {
  return browser.newContext(
    baseURL ? { baseURL, ignoreHTTPSErrors: true } : { ignoreHTTPSErrors: true },
  );
}
