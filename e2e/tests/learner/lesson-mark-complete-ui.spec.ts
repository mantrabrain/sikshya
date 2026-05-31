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

test.describe('learner: lesson player UI Mark-as-complete', () => {
  test('logged-in enrolled student clicks "Mark as complete" and the button flips to "Completed"', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    // Seed: free course + chapter + lesson + student.
    const course = await createCourseViaRest(page, request, {
      title: `E2E lesson UI ${Date.now()}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);
    expect(lesson.link).toMatch(/https?:\/\//);

    const username = slug('stud');
    const password = 'StudPass!234';
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

    // Enroll via REST so the lesson page renders the "Mark as complete" button.
    const enrollRes = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    expect(enrollRes.status()).toBeLessThan(400);

    // Open the lesson permalink and click the UI button.
    await session.page.goto(lesson.link, { waitUntil: 'domcontentloaded' });
    const completeBtn = session.page.locator('[data-sikshya-mark-complete]').first();
    await expect(completeBtn, 'Mark complete button should render for enrolled student').toBeVisible({
      timeout: 20_000,
    });
    await expect(completeBtn).toContainText(/Mark as complete/i);

    await Promise.all([
      session.page.waitForLoadState('domcontentloaded'),
      completeBtn.click(),
    ]);

    // After the REST call resolves the page reloads; the button flips to "Completed".
    const completedBtn = session.page.locator('[data-sikshya-mark-complete]').first();
    await expect(completedBtn).toContainText(/Completed/i, { timeout: 20_000 });
    await expect(completedBtn).toBeDisabled();

    await session.context.close();
  });
});
