/**
 * One-shot script to activate Sikshya Pro via /wp-admin/plugins.php.
 *
 * Run with:
 *   npx tsx e2e/scripts/activate-pro.ts
 * (or via `npm run pro:activate` once wired up in package.json)
 *
 * This exists separately from the test suite because the activation has been
 * observed to race when bundled with other tests on Local by Flywheel — keeping
 * it stand-alone makes the operation reliable and idempotent.
 */
import { chromium } from '@playwright/test';
import dotenv from 'dotenv';
import path from 'node:path';

dotenv.config({ path: path.resolve('e2e/.env') });

const baseURL = process.env.WP_BASE_URL ?? 'http://sikshya.local';
const user = process.env.WP_ADMIN_USER ?? 'admin';
const pass = process.env.WP_ADMIN_PASS ?? 'admin';

const main = async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();

  // Same login retry pattern as the test suite's global-setup — Local can
  // intermittently miss the post-submit navigation.
  let loggedIn = false;
  for (let i = 0; i < 3 && !loggedIn; i++) {
    await page.goto('/wp-login.php');
    if (!/wp-admin/.test(page.url())) {
      await page.locator('#user_login').fill(user);
      await page.locator('#user_pass').fill(pass);
      await page.locator('#wp-submit').click();
      await page.waitForURL(/wp-admin|wp-login\.php/, { timeout: 30_000 }).catch(() => undefined);
    }
    await page.goto('/wp-admin/', { waitUntil: 'domcontentloaded' });
    loggedIn = await page.locator('#wpadminbar').isVisible({ timeout: 10_000 }).catch(() => false);
    if (!loggedIn) await ctx.clearCookies();
  }
  if (!loggedIn) throw new Error('Could not log in to wp-admin.');

  await page.goto('/wp-admin/plugins.php?s=sikshya-pro');
  const row = page.locator('tr[data-plugin*="sikshya-pro"]').first();
  if (!(await row.count())) {
    console.error('sikshya-pro plugin row not found');
    process.exit(2);
  }
  const text = (await row.textContent()) ?? '';
  if (/Deactivate/i.test(text)) {
    console.log('sikshya-pro is already active.');
    await browser.close();
    return;
  }
  const activate = row.locator('a:has-text("Activate")').first();
  await Promise.all([
    page.waitForURL(/plugins\.php/, { timeout: 30_000 }).catch(() => undefined),
    activate.click(),
  ]);

  // Reload to confirm.
  await page.goto('/wp-admin/plugins.php?s=sikshya-pro');
  const after = (await page.locator('tr[data-plugin*="sikshya-pro"]').first().textContent()) ?? '';
  if (!/Deactivate/i.test(after)) {
    console.error('Activation did not stick — row still shows "Activate".');
    process.exit(3);
  }

  const res = await ctx.request.get('/wp-json/sikshya/v1/');
  const body = (await res.json().catch(() => ({}))) as { routes?: Record<string, unknown> };
  const routes = Object.keys(body.routes ?? {});
  const proRoutes = routes.filter((r) => /\/(pro|scale)\//.test(r));
  console.log(`Activated. Routes: ${routes.length}, Pro/Scale routes: ${proRoutes.length}`);

  await browser.close();
};

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
