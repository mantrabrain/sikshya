import { chromium, expect, type FullConfig } from '@playwright/test';
import path from 'node:path';
import fs from 'node:fs';

export default async function globalSetup(_config: FullConfig) {
  const baseURL = process.env.WP_BASE_URL ?? 'http://sikshya.local';
  const user = process.env.WP_ADMIN_USER;
  const pass = process.env.WP_ADMIN_PASS;

  if (!user || !pass) {
    throw new Error('WP_ADMIN_USER / WP_ADMIN_PASS not set. Copy e2e/.env.example to e2e/.env.');
  }

  const authDir = path.resolve('e2e/.auth');
  fs.mkdirSync(authDir, { recursive: true });
  const storagePath = path.join(authDir, 'admin.json');

  const browser = await chromium.launch();
  const ctx = await browser.newContext({ baseURL, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();

  // Retry the whole login flow up to 3× because Local's `wp-login.php` can
  // intermittently issue `?reauth=1` if the testcookie/Set-Cookie sequence races.
  let visible = false;
  let lastUrl = '';
  for (let attempt = 0; attempt < 3 && !visible; attempt++) {
    await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
    if (!/wp-admin/.test(page.url())) {
      await page.locator('#user_login').fill(user);
      await page.locator('#user_pass').fill(pass);
      await page.locator('#wp-submit').click();
      await page
        .waitForURL(/wp-admin|wp-login\.php/, { timeout: 60_000 })
        .catch(() => undefined);
    }
    await page.goto('/wp-admin/', { waitUntil: 'domcontentloaded', timeout: 60_000 });
    visible = await page.locator('#wpadminbar').isVisible({ timeout: 15_000 }).catch(() => false);
    lastUrl = page.url();
    if (!visible) {
      await ctx.clearCookies();
    }
  }
  if (!visible) {
    throw new Error(`globalSetup: #wpadminbar never appeared (last URL: ${lastUrl})`);
  }

  await ctx.storageState({ path: storagePath });
  await browser.close();
}
