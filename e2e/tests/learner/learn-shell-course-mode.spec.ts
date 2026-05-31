import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('learner: learn-shell course-mode layout', () => {
  test('course landing in the learn shell renders without layout breakage or duplicate description', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const courseTitle = `E2E learn-course ${Date.now()}`;
    const course = await createCourseViaRest(page, request, {
      title: courseTitle,
      type: 'free',
      content: `<p>Sample course description for ${courseTitle}.</p>`,
    });

    const username = slug('lrn');
    const password = 'LrnPass!234';
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

    // PublicPageUrls::learnForCourse → /learn/?course_id=<id> by default.
    const res = await session.page.goto(`/learn/?course_id=${course.id}`, {
      waitUntil: 'domcontentloaded',
    });
    expect(res?.status() ?? 0).toBeLessThan(500);

    // The right column must be present (regression: it must not be misaligned
    // by addon blocks rendering as flex siblings).
    const contentCol = session.page.locator('.sikshya-learnContentCol');
    await expect(contentCol).toHaveCount(1);

    // Course description must NOT appear twice — regression for the excerpt +
    // content duplication seen on auto-generated content.
    const body = await session.page.locator('body').innerText();
    const descMatches = body.match(/Sample course description/g) ?? [];
    expect(
      descMatches.length,
      `course description rendered ${descMatches.length} times (expected 1)`,
    ).toBe(1);

    await session.context.close();
  });
});
