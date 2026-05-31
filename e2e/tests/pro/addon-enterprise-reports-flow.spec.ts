import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /enterprise-reports\/v2\/schedules/.test(r)),
    'enterprise_reports schedules routes not registered',
  );
});

test.describe('addon: enterprise_reports schedule CRUD', () => {
  test('admin can create a weekly scheduled report, find it, then delete it', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/enterprise_reports/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const label = `E2E weekly report ${slug('r')}`;
    const create = await request.post(
      '/wp-json/sikshya/v1/pro/enterprise-reports/v2/schedules',
      {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: {
          label,
          status: 'active',
          report_type: 'executive_summary',
          frequency: 'weekly',
          day_of_week: 1,
          hour: 9,
          recipients: 'admin@example.com',
        },
      },
    );
    const createBody = await create.json().catch(() => ({}));
    expect(create.status(), JSON.stringify(createBody).slice(0, 200)).toBeLessThan(400);
    const scheduleId = Number(createBody?.id ?? 0);
    expect(scheduleId).toBeGreaterThan(0);

    const list = await request.get(
      '/wp-json/sikshya/v1/pro/enterprise-reports/v2/schedules',
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const listBody = await list.json().catch(() => ({}));
    expect(list.ok(), JSON.stringify(listBody).slice(0, 200)).toBeTruthy();
    const items: { id?: number; label?: string }[] =
      listBody?.schedules ?? listBody?.items ?? listBody?.rows ?? listBody?.data ?? [];
    const found = items.find((r) => Number(r.id) === scheduleId || r.label === label);
    expect(found, `schedule should appear in list of ${items.length}`).toBeDefined();

    const del = await request.delete(
      `/wp-json/sikshya/v1/pro/enterprise-reports/v2/schedules/${scheduleId}`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    expect(del.status()).toBeLessThan(400);
  });
});
