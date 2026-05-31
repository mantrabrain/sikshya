import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/multisite-license\/network/.test(r)),
    'multisite_scale routes not registered',
  );
});

test.describe('addon: multisite_scale network license endpoint', () => {
  test('GET /pro/multisite-license/network returns a payload (or graceful 401/403 on single-site)', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/multisite_scale/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const res = await request.get('/wp-json/sikshya/v1/pro/multisite-license/network', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const status = res.status();
    const body = await res.json().catch(() => ({}));
    // On a single-site WP install, the network license endpoint typically
    // returns 401/403 (no network admin). Both are valid operational signals:
    // route registered, perm callback ran. On multisite + network admin, we'd
    // get a 200 with the license payload.
    expect([200, 401, 403], JSON.stringify(body).slice(0, 200)).toContain(status);
  });
});
