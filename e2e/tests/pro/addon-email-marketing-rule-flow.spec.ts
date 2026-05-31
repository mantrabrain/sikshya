import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /email-marketing\/rules/.test(r)),
    'email_marketing rules routes not registered',
  );
});

test.describe('addon: email_marketing rule CRUD', () => {
  test('admin can create an email-marketing rule and find it in the list', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/email_marketing/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const ruleName = `E2E rule ${slug('rule')}`;
    const create = await request.post('/wp-json/sikshya/v1/pro/email-marketing/rules', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        name: ruleName,
        description: 'E2E test rule',
        event_key: 'enrollment_created',
        is_active: true,
        priority: 100,
        filters: {},
        actions: [{ type: 'subscribe', list: 'main' }],
      },
    });
    const createBody = await create.json().catch(() => ({}));
    expect(create.status(), JSON.stringify(createBody).slice(0, 200)).toBeLessThan(400);
    const ruleId = Number(createBody?.id ?? createBody?.rule?.id ?? 0);
    expect(ruleId, JSON.stringify(createBody)).toBeGreaterThan(0);

    const list = await request.get('/wp-json/sikshya/v1/pro/email-marketing/rules', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const listBody = await list.json().catch(() => ({}));
    expect(list.ok()).toBeTruthy();
    const rules: { id?: number; name?: string }[] =
      listBody?.rules ?? listBody?.items ?? listBody?.rows ?? listBody?.data ?? [];
    const ours = rules.find((r) => Number(r.id) === ruleId || r.name === ruleName);
    expect(ours, `rule should be in list of ${rules.length}`).toBeDefined();

    const del = await request.delete(`/wp-json/sikshya/v1/pro/email-marketing/rules/${ruleId}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    expect(del.status()).toBeLessThan(400);
  });
});
