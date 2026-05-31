import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/marketplace\/vendor/.test(r)),
    'marketplace_multivendor routes not registered',
  );
});

test.describe('addon: marketplace_multivendor vendor self-service surface', () => {
  test('`/me` returns the vendor payload (user + summary) for an authorized caller', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/marketplace_multivendor/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    // On this dev site the admin satisfies VendorPolicy::canActAsVendor (admins
    // and instructors typically do). We assert the shape: user block + summary
    // counters — confirms the route registers AND its repositories + earnings
    // service serialize without fataling.
    const me = await request.get('/wp-json/sikshya/v1/pro/marketplace/vendor/me', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const body = await me.json().catch(() => ({}));
    expect(me.status(), JSON.stringify(body).slice(0, 200)).toBeLessThan(400);
    expect(body?.ok).toBe(true);
    expect(typeof body?.user === 'object' && body.user !== null).toBe(true);
    expect(typeof body?.user?.id === 'number').toBe(true);
    expect(typeof body?.summary === 'object' && body.summary !== null).toBe(true);
    // Earnings counters should be numeric (pending/available/paid).
    const summary = body?.summary ?? {};
    for (const key of ['pending', 'available', 'paid']) {
      expect(typeof summary[key], `summary.${key}`).toBe('number');
    }
  });
});
