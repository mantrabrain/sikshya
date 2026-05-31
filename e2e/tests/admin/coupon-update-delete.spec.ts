import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: coupon UPDATE / DELETE round-trip', () => {
  test('admin can PATCH a coupon to disabled + change discount, then re-enable', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    // Create.
    const code = slug('CPNED').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 16);
    const createRes = await request.post('/wp-json/sikshya/v1/admin/coupons', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        code,
        discount_type: 'percent',
        discount_value: 10,
        max_uses: 100,
        status: 'active',
      },
    });
    const createBody = await createRes.json().catch(() => ({}));
    expect(createRes.status(), JSON.stringify(createBody).slice(0, 200)).toBeLessThan(400);
    const couponId = Number(createBody?.id ?? createBody?.data?.id ?? 0);
    expect(couponId).toBeGreaterThan(0);

    // PATCH: change status to disabled + bump discount.
    const patchRes = await request.fetch(
      `/wp-json/sikshya/v1/admin/coupons/${couponId}`,
      {
        method: 'PATCH',
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: { status: 'disabled', discount_value: 25 },
      },
    );
    const patchBody = await patchRes.json().catch(() => ({}));
    expect(patchRes.status(), JSON.stringify(patchBody).slice(0, 200)).toBeLessThan(400);

    // Verify changes via list endpoint.
    const list1 = await request.get('/wp-json/sikshya/v1/admin/coupons', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const list1Body = await list1.json().catch(() => ({}));
    const updated = (list1Body?.coupons ?? []).find(
      (c: { id: number }) => Number(c.id) === couponId,
    );
    expect(updated, `coupon ${couponId} should still be in list`).toBeDefined();
    expect(String(updated?.status ?? ''), 'status').toBe('disabled');
    expect(Number(updated?.discount_value ?? 0), 'discount_value').toBe(25);

    // Re-enable.
    const reenable = await request.fetch(
      `/wp-json/sikshya/v1/admin/coupons/${couponId}`,
      {
        method: 'PATCH',
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: { status: 'active' },
      },
    );
    expect(reenable.status()).toBeLessThan(400);

    const list2 = await request.get('/wp-json/sikshya/v1/admin/coupons', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const list2Body = await list2.json().catch(() => ({}));
    const reenabled = (list2Body?.coupons ?? []).find(
      (c: { id: number }) => Number(c.id) === couponId,
    );
    expect(String(reenabled?.status ?? '')).toBe('active');
  });
});
