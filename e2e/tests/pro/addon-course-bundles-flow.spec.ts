import { test, expect } from '@playwright/test';
import { createCourseViaRest, getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(!routes.some((r) => /\/pro\/bundles/.test(r)), 'Sikshya Pro bundles routes not registered');
});

test.describe('addon: course_bundles end-to-end', () => {
  test('admin creates a bundle, attaches two courses, and the bundle lists them', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/course_bundles/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const courseA = await createCourseViaRest(page, request, {
      title: `E2E bundle child A ${Date.now()}`,
      type: 'free',
    });
    const courseB = await createCourseViaRest(page, request, {
      title: `E2E bundle child B ${Date.now()}`,
      type: 'free',
    });

    // Create the bundle: this REST endpoint is POST /pro/bundles.
    const bundleTitle = `E2E Bundle ${slug('bnd')}`;
    const create = await request.post('/wp-json/sikshya/v1/pro/bundles', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { title: bundleTitle, course_ids: [courseA.id, courseB.id] },
    });
    const createBody = await create.json().catch(() => ({}));
    test.info().attach('bundle-create-response', {
      body: `status=${create.status()}\n${JSON.stringify(createBody).slice(0, 300)}`,
    });
    expect(create.status(), JSON.stringify(createBody).slice(0, 200)).toBeLessThan(400);

    const bundleId =
      Number(createBody?.data?.bundle_id ?? createBody?.bundle_id ?? createBody?.id ?? 0);
    expect(bundleId, `bundle id should be in response: ${JSON.stringify(createBody).slice(0, 200)}`).toBeGreaterThan(0);

    // Read the bundle's course list.
    const list = await request.get(`/wp-json/sikshya/v1/pro/bundles/${bundleId}/courses`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const listBody = await list.json().catch(() => ({}));
    expect(list.ok(), JSON.stringify(listBody).slice(0, 200)).toBeTruthy();

    // The list response uses `course_id` (the post ID) and `id` (the pivot row id).
    const courses: { id?: number; course_id?: number; ID?: number }[] =
      listBody?.data?.courses ?? listBody?.courses ?? listBody?.items ?? [];
    const courseIds = courses
      .map((c) => Number(c.course_id ?? c.ID ?? c.id ?? 0))
      .filter((n) => n > 0);
    expect(courseIds, JSON.stringify(listBody).slice(0, 300)).toEqual(
      expect.arrayContaining([courseA.id, courseB.id]),
    );
  });
});
