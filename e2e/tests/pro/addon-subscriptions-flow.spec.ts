import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/plans/.test(r)),
    'subscriptions routes not registered',
  );
});

test.describe('addon: subscriptions plan CRUD', () => {
  test('admin can create a plan, find it in the list, update it, then delete it', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/subscriptions/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    // CREATE: monthly plan @ 9.99.
    const planName = `E2E Plan ${slug('p')}`;
    const create = await request.post('/wp-json/sikshya/v1/pro/plans', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        name: planName,
        amount: 9.99,
        interval_unit: 'month',
        status: 'active',
      },
    });
    const createBody = await create.json().catch(() => ({}));
    expect(create.status(), JSON.stringify(createBody).slice(0, 200)).toBeLessThan(400);
    const planId = Number(createBody?.id ?? createBody?.plan_id ?? createBody?.data?.id ?? 0);
    expect(planId, JSON.stringify(createBody)).toBeGreaterThan(0);

    // LIST: should contain our plan.
    const list = await request.get('/wp-json/sikshya/v1/pro/plans', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const listBody = await list.json().catch(() => ({}));
    expect(list.ok(), JSON.stringify(listBody).slice(0, 200)).toBeTruthy();
    const plans: { id?: number; name?: string }[] =
      listBody?.plans ?? listBody?.rows ?? listBody?.data ?? listBody?.items ?? [];
    const ours = plans.find((p) => Number(p.id) === planId || p.name === planName);
    expect(ours, `plan ${planId} should appear in list of ${plans.length}`).toBeDefined();

    // UPDATE: bump amount.
    const update = await request.fetch(`/wp-json/sikshya/v1/pro/plans/${planId}`, {
      method: 'PUT',
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { id: planId, name: planName, amount: 19.99, interval_unit: 'month', status: 'active' },
    });
    expect(update.status(), 'update plan').toBeLessThan(400);

    // DELETE.
    const del = await request.delete(`/wp-json/sikshya/v1/pro/plans/${planId}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    expect(del.status()).toBeLessThan(400);
  });
});
