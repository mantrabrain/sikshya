import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/social-login/.test(r)),
    'social_login routes not registered',
  );
});

test.describe('addon: social_login settings round-trip', () => {
  test('admin saves a provider config and reads it back', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/social_login/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    // Settings are stored with flat keys (`google_client_id`, etc.) not nested.
    const tag = `E2E-client-${slug('cl').replace(/[^A-Za-z0-9-]/g, '')}`;
    const save = await request.post('/wp-json/sikshya/v1/pro/social-login', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        google_client_id: tag,
        google_client_secret: 'sssh',
      },
    });
    const saveBody = await save.json().catch(() => ({}));
    expect(save.status(), JSON.stringify(saveBody).slice(0, 200)).toBeLessThan(400);
    expect(saveBody?.ok).toBe(true);

    const read = await request.get('/wp-json/sikshya/v1/pro/social-login', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const readBody = await read.json().catch(() => ({}));
    expect(read.ok(), JSON.stringify(readBody).slice(0, 200)).toBeTruthy();
    const flat = JSON.stringify(readBody);
    expect(flat.includes(tag), `social login round-trip; got ${flat.slice(0, 200)}`).toBe(true);
  });
});
