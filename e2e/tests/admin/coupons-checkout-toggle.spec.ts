import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: coupons checkout-toggle endpoint', () => {
  test('GET returns current state, POST flips it, and state survives the round-trip', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    // Snapshot current value so we can restore it at the end of the test.
    const initial = await request.get('/wp-json/sikshya/v1/admin/coupons/checkout-toggle', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const initialBody = await initial.json().catch(() => ({}));
    expect(initial.ok(), JSON.stringify(initialBody).slice(0, 200)).toBeTruthy();
    expect(typeof initialBody?.enabled === 'boolean').toBeTruthy();
    const startedEnabled = Boolean(initialBody.enabled);

    // Flip to the opposite state.
    const flipped = !startedEnabled;
    const flip = await request.post('/wp-json/sikshya/v1/admin/coupons/checkout-toggle', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { enabled: flipped },
    });
    const flipBody = await flip.json().catch(() => ({}));
    expect(flip.ok(), JSON.stringify(flipBody).slice(0, 200)).toBeTruthy();
    expect(flipBody?.enabled).toBe(flipped);

    // Confirm the new state is now visible on a fresh GET.
    const after = await request.get('/wp-json/sikshya/v1/admin/coupons/checkout-toggle', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const afterBody = await after.json().catch(() => ({}));
    expect(after.ok()).toBeTruthy();
    expect(afterBody?.enabled).toBe(flipped);

    // Restore the original state so we don't leak setting state to other specs.
    await request.post('/wp-json/sikshya/v1/admin/coupons/checkout-toggle', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { enabled: startedEnabled },
    });
  });
});
