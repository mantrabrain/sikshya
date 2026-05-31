import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: bulk action validators', () => {
  test('orders/bulk rejects empty action / ids with 400 invalid_request', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    const empty = await request.post('/wp-json/sikshya/v1/admin/orders/bulk', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { action: '', ids: [] },
    });
    const emptyBody = await empty.json().catch(() => ({}));
    expect(empty.status(), JSON.stringify(emptyBody).slice(0, 200)).toBe(400);
    expect(String(emptyBody?.code ?? '')).toMatch(/invalid_request|invalid_action/);

    const tooMany = await request.post('/wp-json/sikshya/v1/admin/orders/bulk', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { action: 'delete', ids: Array.from({ length: 101 }, (_, i) => i + 1) },
    });
    const tooManyBody = await tooMany.json().catch(() => ({}));
    expect(tooMany.status(), JSON.stringify(tooManyBody).slice(0, 200)).toBe(400);
    expect(String(tooManyBody?.code ?? '')).toMatch(/too_many|invalid/);
  });

  test('payments/bulk rejects empty action / ids', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);

    const empty = await request.post('/wp-json/sikshya/v1/admin/payments/bulk', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { action: '', ids: [] },
    });
    const body = await empty.json().catch(() => ({}));
    expect(empty.status(), JSON.stringify(body).slice(0, 200)).toBe(400);
  });

  test('email-template-bulk rejects empty action / ids', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);

    const empty = await request.post('/wp-json/sikshya/v1/admin/email-template-bulk', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { action: '', ids: [] },
    });
    const body = await empty.json().catch(() => ({}));
    expect(empty.status(), JSON.stringify(body).slice(0, 200)).toBe(400);
    expect(String(body?.code ?? '')).toMatch(/invalid_request|invalid_action/);
  });
});
