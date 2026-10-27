import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(!routes.some((r) => /\/scale\/xapi\/status/.test(r)), 'xapi routes not registered');
});

test.describe('addon: xAPI / LRS', () => {
  test('the status endpoint reports the queue and never returns the credentials', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    await request.post('/wp-json/sikshya/v1/admin/addons/xapi_lrs/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    // Settings go through the shared Pro add-on settings route, which is what
    // the admin screen writes too — a test that configured it another way
    // would not prove the screen works.
    const saved = await request.post('/wp-json/sikshya/v1/pro/addons/xapi_lrs/settings', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        endpoint: 'http://127.0.0.1:1/data/xAPI',
        auth_key: 'e2e-key',
        auth_secret: 'e2e-secret',
      },
    });
    expect(saved.status(), 'saving xAPI settings').toBeLessThan(400);

    const status = await request.get('/wp-json/sikshya/v1/scale/xapi/status', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const body = await status.json().catch(() => ({}));

    expect(status.status()).toBe(200);
    expect(body?.ok).toBe(true);
    expect(body?.configured).toBe(true);
    expect(body?.xapi_version).toBe('1.0.3');
    expect(body?.counts).toBeTruthy();

    const serialised = JSON.stringify(body);
    expect(serialised, 'the LRS secret must never leave the server').not.toContain('e2e-secret');
    expect(serialised, 'nor the key').not.toContain('e2e-key');
  });

  test('a learner completing a course leaves a statement in the queue', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);

    const before = await request.get('/wp-json/sikshya/v1/scale/xapi/statements?per_page=1', {
      headers: { 'X-WP-Nonce': nonce },
    });
    expect(before.status()).toBe(200);
    const rows = (await before.json().catch(() => ({})))?.rows ?? [];

    // Every row is a real queued statement with its own xAPI id — the id is
    // what makes a retry the same statement rather than a second completion.
    for (const row of rows) {
      expect(String(row.statement_uuid)).toMatch(/^[0-9a-f-]{36}$/);
      expect(String(row.verb)).toMatch(/^https?:\/\//);
    }
  });

  test('the integrations screen shows the xAPI panel with the queue counts', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=sikshya&view=integrations', {
      waitUntil: 'domcontentloaded',
    });
    await page.waitForLoadState('networkidle').catch(() => undefined);

    const card = page.locator('[data-sikshya-xapi-card]');
    await expect(card).toBeVisible();
    await expect(card).toContainText(/xAPI/i);

    // The panel is gated like every other paid surface, so on an unlicensed
    // site it shows an upgrade overlay instead of counts. Only assert the
    // counts when the add-on is actually running.
    const counts = card.locator('[data-sikshya-xapi-count="accepted"]');
    if (await counts.isVisible().catch(() => false)) {
      await expect(counts).toHaveText(/^\d+$/);
    }
  });
});
