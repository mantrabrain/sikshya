import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/scale\/webhooks\/v2\/endpoints/.test(r)),
    'webhooks v2 routes not registered',
  );
});

test.describe('addon: webhooks end-to-end', () => {
  test('admin creates a v2 endpoint, lists it, and the new ID is returned', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    await request.post('/wp-json/sikshya/v1/admin/addons/webhooks/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const label = `E2E webhook ${slug('w')}`;
    const create = await request.post('/wp-json/sikshya/v1/scale/webhooks/v2/endpoints', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        label,
        delivery_url: 'https://example.test/webhook',
        secret: 'shhh',
        events: ['enrollment.created', 'order.completed'],
      },
    });
    const createBody = await create.json().catch(() => ({}));
    expect(create.status(), JSON.stringify(createBody).slice(0, 200)).toBeLessThan(400);
    expect(createBody?.ok).toBe(true);
    const createdId = Number(createBody?.item?.id ?? 0);
    expect(createdId).toBeGreaterThan(0);

    const list = await request.get('/wp-json/sikshya/v1/scale/webhooks/v2/endpoints', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const listBody = await list.json().catch(() => ({}));
    expect(list.ok()).toBeTruthy();
    const items: { id?: number; label?: string }[] = listBody?.items ?? listBody?.rows ?? [];
    const found = items.find((r) => Number(r.id) === createdId || r.label === label);
    expect(found, `webhook should appear in list (${items.length} items)`).toBeDefined();
  });
});
