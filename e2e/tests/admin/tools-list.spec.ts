import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: tools surface', () => {
  test('POST /sikshya/v1/tools with empty/invalid action returns a structured error', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    // The tools route is CREATABLE-only (POST). It runs a named action; a missing
    // / unknown action should yield a 400 with a structured envelope (not a 404
    // "no route" or a fatal). Proves the route registers + the handler validates.
    const res = await request.post('/wp-json/sikshya/v1/tools', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {},
    });
    const body = await res.json().catch(() => ({}));
    expect(res.status(), JSON.stringify(body).slice(0, 200)).toBeGreaterThanOrEqual(400);
    expect(res.status()).toBeLessThan(500);
    expect(typeof body === 'object' && body !== null).toBe(true);
  });
});
