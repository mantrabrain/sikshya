import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createQuizViaRest,
  createUserViaRest,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('learner: quiz page UI', () => {
  test('enrolled student lands on the quiz template and sees Start quiz / empty intro', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E quiz UI ${Date.now()}`,
      type: 'free',
    });
    const quiz = await createQuizViaRest(page, request, course.id);
    expect(quiz.link).toMatch(/https?:\/\//);

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

    // Enroll so the student is allowed inside the quiz template.
    const enrollRes = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    expect(enrollRes.status()).toBeLessThan(400);

    await session.page.goto(quiz.link, { waitUntil: 'domcontentloaded' });

    // The quiz template renders the intro panel + either Start quiz (when
    // questions exist) or an empty-state message. Either signals the player
    // template is wired up for an enrolled student.
    const introOrStart = session.page
      .locator('[data-sikshya-quiz-intro]')
      .or(session.page.locator('[data-sikshya-quiz-start]'))
      .or(session.page.getByRole('button', { name: /Start quiz/i }))
      .or(session.page.getByText(/no questions|not configured/i))
      .first();
    await expect(introOrStart, 'quiz template should render for enrolled student').toBeVisible({
      timeout: 20_000,
    });

    await session.context.close();
  });
});
