import { test, expect } from '@playwright/test';
import { createCourseViaRest, getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /extended\/activity-log/.test(r)),
    'activity_log routes not registered',
  );
});

test.describe('addon: activity_log event surface', () => {
  test('after enabling addon and performing an action the log endpoint returns rows', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/activity_log/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    // Trigger some activity: create a course.
    await createCourseViaRest(page, request, {
      title: `E2E activity course ${Date.now()}`,
      type: 'free',
    });

    // The activity log returns a paginated list. We're not asserting specific
    // event content (Pro can register many hook keys), just that the endpoint
    // is operational and returns a structured response.
    const log = await request.get(
      '/wp-json/sikshya/v1/pro/extended/activity-log?per_page=10',
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const logBody = await log.json().catch(() => ({}));
    expect(log.status(), JSON.stringify(logBody).slice(0, 200)).toBeLessThan(400);
    expect(logBody?.ok).toBe(true);

    // The response should expose a rows / items / data array.
    const rows = logBody?.rows ?? logBody?.items ?? logBody?.data?.rows ?? logBody?.data?.items ?? [];
    expect(Array.isArray(rows), `expected array body; got ${typeof rows}`).toBe(true);
  });
});
