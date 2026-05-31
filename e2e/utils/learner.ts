import type { APIRequestContext, Browser } from '@playwright/test';

/**
 * Open a fresh browser context, log a student in, and return their cookie-authed
 * APIRequestContext plus the sikshyaFrontend.restNonce extracted from a frontend page.
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
}> {
  const context = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });
  const page = await context.newPage();

  await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await page
    .waitForURL(/wp-admin|wp-login\.php|\//, { timeout: 30_000 })
    .catch(() => undefined);

  // Visit the frontend so the `sikshyaFrontend` localization is in the page.
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  const restNonce = await page.evaluate(() => {
    const w = window as unknown as { sikshyaFrontend?: { restNonce?: string } };
    return w.sikshyaFrontend?.restNonce ?? '';
  });

  return { context, page, request: context.request, restNonce };
}
