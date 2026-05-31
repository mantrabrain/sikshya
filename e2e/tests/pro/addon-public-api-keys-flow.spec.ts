import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/scale\/public-api\/keys/.test(r)),
    'public_api_keys routes not registered',
  );
});

test.describe('addon: public_api_keys end-to-end', () => {
  test('admin creates an API key, lists it, then revokes it', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    await request.post('/wp-json/sikshya/v1/admin/addons/public_api_keys/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const label = `E2E key ${slug('k')}`;
    const create = await request.post('/wp-json/sikshya/v1/scale/public-api/keys', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { label, scopes: ['read:courses'] },
    });
    const createBody = await create.json().catch(() => ({}));
    expect(create.status(), JSON.stringify(createBody).slice(0, 200)).toBeLessThan(400);
    expect(createBody?.ok).toBe(true);

    const list = await request.get('/wp-json/sikshya/v1/scale/public-api/keys', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const listBody = await list.json().catch(() => ({}));
    expect(list.ok()).toBeTruthy();
    const rows: { id?: number; label?: string }[] = listBody?.rows ?? [];
    const ours = rows.find((r) => r.label === label);
    expect(ours, `created key should appear in list (${rows.length} rows)`).toBeDefined();

    if (ours?.id) {
      const del = await request.delete(`/wp-json/sikshya/v1/scale/public-api/keys/${ours.id}`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      expect(del.status()).toBeLessThan(400);
    }
  });
});
