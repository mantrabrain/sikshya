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

test.describe('learner: /me/progress endpoint', () => {
  test('student GET /me/progress for an enrolled course returns lesson totals + percent', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E progress course ${Date.now()}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    const username = slug('prg');
    const password = 'PrgPass!234';
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

    await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });

    // Initial progress: 0 / 1 lesson, 0%.
    const before = await session.request.get(
      `/wp-json/sikshya/v1/me/progress?course_id=${course.id}`,
      { headers: { 'X-WP-Nonce': session.restNonce } },
    );
    const beforeBody = await before.json().catch(() => ({}));
    expect(before.ok(), JSON.stringify(beforeBody).slice(0, 200)).toBeTruthy();
    expect(beforeBody?.ok).toBe(true);
    expect(Number(beforeBody?.data?.lesson_total ?? 0)).toBeGreaterThan(0);
    expect(Number(beforeBody?.data?.lessons_completed ?? 0)).toBe(0);
    expect(Number(beforeBody?.data?.progress_percent ?? -1)).toBe(0);

    // Mark complete via REST.
    await session.request.post('/wp-json/sikshya/v1/me/lesson-complete', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { lesson_id: lesson.id, course_id: course.id },
    });

    const after = await session.request.get(
      `/wp-json/sikshya/v1/me/progress?course_id=${course.id}`,
      { headers: { 'X-WP-Nonce': session.restNonce } },
    );
    const afterBody = await after.json().catch(() => ({}));
    expect(after.ok()).toBeTruthy();
    expect(Number(afterBody?.data?.lessons_completed ?? 0)).toBeGreaterThan(0);
    expect(Number(afterBody?.data?.progress_percent ?? 0)).toBeGreaterThan(0);

    await session.context.close();
  });
});
