import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.describe('admin: coupon CRUD via REST', () => {
  test('admin can create and list a coupon', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const code = slug('CPN').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 16) || 'TESTCODE';

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
    expect(createRes.status(), JSON.stringify(createBody)).toBeLessThan(400);

    const listRes = await request.get('/wp-json/sikshya/v1/admin/coupons', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const listBody = await listRes.json().catch(() => ({}));
    expect(listRes.ok()).toBeTruthy();
    const codes: string[] = (listBody?.coupons ?? []).map((c: { code: string }) => c.code);
    expect(codes).toContain(code);
  });
});
