import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  getAdminNonce,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: orders + payments surface', () => {
  test('GET /admin/orders returns paginated envelope', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const res = await request.get('/wp-json/sikshya/v1/admin/orders?per_page=10&page=1', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const body = await res.json().catch(() => ({}));
    expect(res.ok(), JSON.stringify(body).slice(0, 200)).toBeTruthy();
    // Either rows or items array is fine — both surface in handler variants.
    const rows = body?.rows ?? body?.orders ?? body?.items ?? body?.data?.rows ?? [];
    expect(Array.isArray(rows), `expected list; got ${typeof rows}`).toBe(true);
  });

  test('GET /admin/payments returns paginated envelope', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const res = await request.get('/wp-json/sikshya/v1/admin/payments?per_page=10', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const body = await res.json().catch(() => ({}));
    expect(res.ok(), JSON.stringify(body).slice(0, 200)).toBeTruthy();
    const rows = body?.rows ?? body?.payments ?? body?.items ?? body?.data?.rows ?? [];
    expect(Array.isArray(rows), `expected list; got ${typeof rows}`).toBe(true);
  });

  test('admin/enrollments returns the paginated envelope', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const res = await request.get('/wp-json/sikshya/v1/admin/enrollments?per_page=10', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const body = await res.json().catch(() => ({}));
    expect(res.ok(), JSON.stringify(body).slice(0, 200)).toBeTruthy();
    const rows = body?.rows ?? body?.enrollments ?? body?.items ?? body?.data?.rows ?? [];
    expect(Array.isArray(rows), `expected list; got ${typeof rows}`).toBe(true);
  });

  test('manual enrollment + admin/enrollments includes the new row', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const course = await createCourseViaRest(page, request, {
      title: `E2E enrol seed ${Date.now()}`,
      type: 'free',
    });
    const username = slug('enr');
    const userRes = await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password: 'EnrPass!234',
      role: STUDENT_ROLE,
    });
    expect(userRes.id).toBeGreaterThan(0);

    const enr = await request.post('/wp-json/sikshya/v1/admin/enrollments/manual', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { user_id: userRes.id, course_id: course.id },
    });
    expect(enr.status()).toBeLessThan(400);

    const list = await request.get(
      `/wp-json/sikshya/v1/admin/enrollments?per_page=50&user_id=${userRes.id}`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const listBody = await list.json().catch(() => ({}));
    expect(list.ok()).toBeTruthy();
    const rows: { user_id?: number; course_id?: number }[] =
      listBody?.rows ?? listBody?.enrollments ?? listBody?.items ?? listBody?.data?.rows ?? [];
    const ours = rows.find(
      (r) => Number(r.user_id) === userRes.id && Number(r.course_id) === course.id,
    );
    expect(ours, `enrollment for user ${userRes.id} should appear in list of ${rows.length}`).toBeDefined();
  });
});
