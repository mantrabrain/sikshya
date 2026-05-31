import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('public: single-course student-count stat', () => {
  test('after one student enrolls, the single-course hero surfaces a "Students" stat', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E student-count course ${Date.now()}`,
      type: 'free',
    });

    // Before any enrollments, the stat should be hidden.
    const before = await page.goto(`/?p=${course.id}`, { waitUntil: 'domcontentloaded' });
    expect(before?.status() ?? 0).toBeLessThan(400);
    await expect(page.locator('.sikshya-course-lp__stat', { hasText: /Students/i })).toHaveCount(0);

    // Enroll one student via REST.
    const username = slug('scnt');
    const password = 'CntPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(
      browser,
      baseURL ?? 'http://sikshya.local',
      username,
      password,
    );
    const enroll = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    expect(enroll.status()).toBeLessThan(400);
    await session.context.close();

    // After the enrollment, the cache should be invalidated and the stat appears.
    const after = await page.goto(`/?p=${course.id}`, { waitUntil: 'domcontentloaded' });
    expect(after?.status() ?? 0).toBeLessThan(400);
    await expect(page.locator('.sikshya-course-lp__stat', { hasText: /Students/i })).toHaveCount(1);
    await expect(page.locator('.sikshya-course-lp__stat', { hasText: /Students/i })).toContainText(/1/);
  });
});
