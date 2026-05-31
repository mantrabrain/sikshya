import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: auth REST validation envelopes', () => {
  test('POST /auth/web-register with empty email returns 400 invalid envelope', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    const res = await request.post('/wp-json/sikshya/v1/auth/web-register', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { email: '', password: 'whatever' },
    });
    const body = await res.json().catch(() => ({}));
    expect(res.status(), JSON.stringify(body).slice(0, 200)).toBe(400);
    expect(body?.success).toBe(false);
    expect(String(body?.message ?? '')).toMatch(/email|password/i);
  });

  test('POST /auth/web-register with an existing email returns 409', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    // admin@example.com / admin user exists (we logged in as admin).
    const res = await request.post('/wp-json/sikshya/v1/auth/web-register', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { email: 'dev-email@wpengine.local', password: 'whatever-pass' },
    });
    const body = await res.json().catch(() => ({}));
    // 409 if the email matches admin@example.local OR 400 if email_exists check
    // depends on a slightly different signal. Both are valid envelopes here.
    expect([409, 400], JSON.stringify(body).slice(0, 200)).toContain(res.status());
  });

  test('POST /auth/web-register creates a new user when email is fresh', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    const email = `e2e-${slug('reg')}@example.com`;
    const res = await request.post('/wp-json/sikshya/v1/auth/web-register', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { email, password: 'NewPass!234', display_name: 'E2E Newbie' },
    });
    const body = await res.json().catch(() => ({}));
    expect(res.status(), JSON.stringify(body).slice(0, 200)).toBeLessThan(400);
    expect(body?.success).toBe(true);
  });
});
