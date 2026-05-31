import { test, expect } from '@playwright/test';
import { createCourseViaRest, getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/gradebook\/grid/.test(r)),
    'gradebook routes not registered',
  );
});

test.describe('addon: gradebook course grid', () => {
  test('GET /pro/gradebook/grid for a course returns the grid envelope', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/gradebook/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const course = await createCourseViaRest(page, request, {
      title: `E2E gradebook course ${Date.now()}`,
      type: 'free',
    });

    // The grid endpoint requires course_id. With a fresh course (no enrollments),
    // the grid returns an empty payload but the envelope shape is the same.
    const res = await request.get(
      `/wp-json/sikshya/v1/pro/gradebook/grid?course_id=${course.id}&per_page=10`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const body = await res.json().catch(() => ({}));
    expect(res.status(), JSON.stringify(body).slice(0, 200)).toBeLessThan(400);
    expect(body?.ok, JSON.stringify(body).slice(0, 200)).toBe(true);

    // Either students/rows is an array (with or without data) — both are fine.
    const rows = body?.rows ?? body?.students ?? body?.data?.rows ?? body?.data?.students ?? [];
    expect(Array.isArray(rows), `expected array; got ${typeof rows}`).toBe(true);
  });
});
