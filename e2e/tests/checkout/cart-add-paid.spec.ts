import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  getAdminNonce,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';

test.describe('checkout: admin manual fulfillment', () => {
  test('admin can manually enroll a student in a paid course (offline fulfillment path)', async ({
    page,
    request,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E paid course ${Date.now()}`,
      price: 25,
    });

    const username = slug('stud');
    const userRes = await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password: 'StudPass!234',
      role: STUDENT_ROLE,
    });
    expect(userRes.id).toBeGreaterThan(0);

    const nonce = await getAdminNonce(page);
    const enrollRes = await request.post('/wp-json/sikshya/v1/admin/enrollments/manual', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { user_id: userRes.id, course_id: course.id },
    });
    const enrollBody = await enrollRes.json().catch(() => ({}));
    expect(enrollRes.status(), JSON.stringify(enrollBody)).toBeLessThan(400);
  });
});
