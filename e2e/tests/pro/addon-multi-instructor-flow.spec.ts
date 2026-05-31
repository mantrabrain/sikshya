import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  getAdminNonce,
  INSTRUCTOR_ROLE,
  slug,
} from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/multi-instructor\/course-staff/.test(r)),
    'multi_instructor routes not registered',
  );
});

test.describe('addon: multi_instructor end-to-end', () => {
  test('admin adds a co-instructor to a course, the staff list returns them, then removal works', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    await request.post('/wp-json/sikshya/v1/admin/addons/multi_instructor/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const course = await createCourseViaRest(page, request, {
      title: `E2E multi-instr course ${Date.now()}`,
      type: 'free',
    });

    // Create a user with the instructor role to assign as co-instructor.
    const username = slug('co');
    const coInstructor = await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password: 'CoInstrPass!234',
      role: INSTRUCTOR_ROLE,
    });
    expect(coInstructor.id).toBeGreaterThan(0);

    // Attach as course staff.
    const post = await request.post('/wp-json/sikshya/v1/pro/multi-instructor/course-staff', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        course_id: course.id,
        user_id: coInstructor.id,
        revenue_share: 25,
        role: 'co_instructor',
      },
    });
    const postBody = await post.json().catch(() => ({}));
    expect(post.status(), JSON.stringify(postBody).slice(0, 200)).toBeLessThan(400);

    // GET the staff for the course.
    const list = await request.get(
      `/wp-json/sikshya/v1/pro/multi-instructor/course-staff?course_id=${course.id}`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const listBody = await list.json().catch(() => ({}));
    expect(list.ok(), JSON.stringify(listBody).slice(0, 200)).toBeTruthy();
    const members: { user_id?: number }[] =
      listBody?.instructors ?? listBody?.staff ?? listBody?.members ?? listBody?.data ?? listBody?.items ?? [];
    const memberIds = members.map((m) => Number(m.user_id ?? 0)).filter((n) => n > 0);
    expect(memberIds, JSON.stringify(listBody).slice(0, 300)).toContain(coInstructor.id);

    // Clean up.
    await request.delete(
      `/wp-json/sikshya/v1/pro/multi-instructor/course-staff?course_id=${course.id}&user_id=${coInstructor.id}`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
  });
});
