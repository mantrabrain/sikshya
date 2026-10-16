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

  await retireWebhookDebris(page);

  await browser.close();
}

/**
 * Deactivate webhook endpoints left behind by earlier runs.
 *
 * The webhook specs each create an endpoint pointing at a host that does not
 * resolve, and nothing removes them, so every run leaves more active endpoints
 * and more undeliverable queued deliveries than the last. wp-cron then spends
 * each page load attempting them, every attempt waiting out the full HTTP
 * timeout, and the whole suite slows down until unrelated specs start timing
 * out. That is what made the same suite pass on the first browser and fail
 * progressively on the second and third — not any browser difference.
 *
 * An inactive endpoint resolves its queued deliveries immediately as disabled
 * instead of waiting on the network, which drains the backlog cheaply. Specs
 * that need a live endpoint create their own, so nothing here takes anything
 * away from them.
 *
 * Best effort by design: on a site without the Webhooks addon, or an
 * unlicensed one, the route is absent and there is nothing to clean.
 */
async function retireWebhookDebris(page: import('@playwright/test').Page): Promise<void> {
  try {
    const nonce = await page.evaluate(
      () => (window as unknown as { wpApiSettings?: { nonce?: string } }).wpApiSettings?.nonce ?? '',
    );
    if (!nonce) {
      return;
    }

    const headers = { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' };
    const list = await page.request.get(
      '/wp-json/sikshya/v1/scale/webhooks/v2/endpoints?per_page=200',
      { headers },
    );
    if (!list.ok()) {
      return;
    }

    const body = (await list.json().catch(() => ({}))) as {
      endpoints?: Array<{ id?: number; is_active?: boolean }>;
      items?: Array<{ id?: number; is_active?: boolean }>;
    };
    const rows = body.endpoints ?? body.items ?? [];

    let retired = 0;
    for (const row of rows) {
      const id = Number(row?.id ?? 0);
      if (id <= 0 || row?.is_active === false) {
        continue;
      }
      const res = await page.request.patch(`/wp-json/sikshya/v1/scale/webhooks/v2/endpoints/${id}`, {
        headers,
        data: { is_active: false },
      });
      if (res.ok()) {
        retired++;
      }
    }

    if (retired > 0) {
      console.log(`globalSetup: deactivated ${retired} webhook endpoint(s) left by earlier runs.`);
    }
  } catch {
    // Never fail the run over housekeeping.
  }
}
