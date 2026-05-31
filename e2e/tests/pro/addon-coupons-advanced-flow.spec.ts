import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/coupons\//.test(r) && /advanced/.test(r)),
    'coupons_advanced routes not registered',
  );
});

test.describe('addon: coupons_advanced end-to-end', () => {
  test('admin creates a coupon, attaches advanced rules, and reads them back', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    await request.post('/wp-json/sikshya/v1/admin/addons/coupons_advanced/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    // Create a base coupon via the free coupon API.
    const code = slug('CPNADV').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 16);
    const create = await request.post('/wp-json/sikshya/v1/admin/coupons', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { code, discount_type: 'percent', discount_value: 15, max_uses: 25, status: 'active' },
    });
    const createBody = await create.json().catch(() => ({}));
    expect(create.status(), JSON.stringify(createBody).slice(0, 200)).toBeLessThan(400);
    const couponId = Number(createBody?.id ?? createBody?.data?.id ?? 0);
    expect(couponId).toBeGreaterThan(0);

    // Save advanced rules via the addon endpoint.
    const save = await request.post(
      `/wp-json/sikshya/v1/pro/coupons/${couponId}/advanced`,
      {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: {
          minimum_amount: 50,
          maximum_amount: 500,
          per_user_limit: 1,
        },
      },
    );
    const saveBody = await save.json().catch(() => ({}));
    expect(save.status(), JSON.stringify(saveBody).slice(0, 200)).toBeLessThan(400);
    expect(saveBody?.ok).toBe(true);

    // Read advanced rules back and verify persistence.
    const read = await request.get(`/wp-json/sikshya/v1/pro/coupons/${couponId}/advanced`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const readBody = await read.json().catch(() => ({}));
    expect(read.ok(), JSON.stringify(readBody).slice(0, 200)).toBeTruthy();
    // rules is a canonicalized object; do a structural sanity check.
    expect(typeof readBody?.rules === 'object' && readBody.rules !== null).toBe(true);
  });
});
