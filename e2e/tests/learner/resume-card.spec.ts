import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  createUserViaRest,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('learner: My Learning resume card', () => {
  test('enrolled student with one completed lesson sees a "Continue learning" hero card on /my-learning/learning/', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    // Seed: a course with 2 lessons so we have one to resume to.
    const course = await createCourseViaRest(page, request, {
      title: `E2E resume-card course ${Date.now()}`,
      type: 'free',
    });
    const firstLesson = await createLessonViaRest(page, request, course.id);
    const nextLesson = await createLessonViaRest(page, request, course.id);

    const username = slug('rsm');
    const password = 'RsmPass!234';
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

    // Mark the first lesson complete so the progress repo has a touched row.
    const mark = await session.request.post('/wp-json/sikshya/v1/me/lesson-complete', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, lesson_id: firstLesson.id },
    });
    expect(mark.status()).toBeLessThan(400);

    // The learning view (pretty perms) lives at /my-learning/learning/. Try the
    // pretty path first, then fall back to the plain query string if needed.
    let res = await session.page.goto('/my-learning/learning/', { waitUntil: 'domcontentloaded' });
    if (!res || res.status() >= 400) {
      res = await session.page.goto('/my-learning/?account_view=learning', { waitUntil: 'domcontentloaded' });
    }
    expect(res?.status() ?? 0, 'GET /my-learning/learning/').toBeLessThan(400);

    // The hero card should show our course title and a "Continue learning" CTA.
    await expect(session.page.locator('.sik-acc-resume')).toBeVisible({ timeout: 10_000 });
    await expect(session.page.locator('.sik-acc-resume__title')).toContainText(course.title);
    await expect(session.page.locator('.sik-acc-resume__cta')).toContainText(/continue|start learning/i);

    // The progress text should report 1 of 2 lessons (50% complete).
    await expect(session.page.locator('.sik-acc-resume__progress-text')).toContainText(/1 of 2 lessons/);
    // Avoid an unused-var lint on nextLesson.
    expect(nextLesson.id).toBeGreaterThan(0);

    await session.context.close();
  });
});
