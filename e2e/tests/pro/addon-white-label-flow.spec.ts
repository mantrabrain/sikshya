import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/white-label/.test(r)),
    'white_label routes not registered',
  );
});

test.describe('addon: white_label branding save/get', () => {
  test('admin saves global white-label branding and reads it back', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/white_label/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    // Read current to restore at end.
    const initial = await request.get('/wp-json/sikshya/v1/pro/white-label', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const initialBody = await initial.json().catch(() => ({}));
    expect(initial.ok(), JSON.stringify(initialBody).slice(0, 200)).toBeTruthy();
    const before = initialBody?.options ?? initialBody?.data ?? {};

    const tag = `E2E Brand ${slug('br')}`;
    const save = await request.post('/wp-json/sikshya/v1/pro/white-label', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { brand_name: tag, support_email: 'support@example.com' },
    });
    const saveBody = await save.json().catch(() => ({}));
    expect(save.status(), JSON.stringify(saveBody).slice(0, 200)).toBeLessThan(400);
    expect(saveBody?.ok).toBe(true);

    const read = await request.get('/wp-json/sikshya/v1/pro/white-label', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const readBody = await read.json().catch(() => ({}));
    const opts = readBody?.options ?? readBody?.data ?? {};
    // The sanitiser may rename / drop fields; assert at least one of the
    // values we wrote shows up somewhere in the persisted shape.
    const flat = JSON.stringify(opts);
    expect(flat.includes(tag) || flat.includes('support@example.com'), `branding round-trip; got ${flat.slice(0, 200)}`).toBe(true);

    // Restore prior state (best-effort).
    if (before && typeof before === 'object') {
      await request.post('/wp-json/sikshya/v1/pro/white-label', {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: before,
      });
    }
  });
});
