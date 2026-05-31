import { test, expect } from '@playwright/test';
import {
  attachQuestionsToQuiz,
  createCourseViaRest,
  createQuestionViaRest,
  createQuizViaRest,
  createUserViaRest,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('learner: quiz attempt UI', () => {
  test('enrolled student starts a quiz, picks the correct answer, submits, and sees a result', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E quiz attempt ${Date.now()}`,
      type: 'free',
    });
    const quiz = await createQuizViaRest(page, request, course.id);
    const question = await createQuestionViaRest(page, request, {
      title: 'Is the sky blue?',
      type: 'multiple_choice',
      options: ['Yes', 'No'],
      correct: 0,
      points: 1,
    });
    await attachQuestionsToQuiz(page, request, quiz.id, [question.id]);

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

    const enrollRes = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    expect(enrollRes.status()).toBeLessThan(400);

    await session.page.goto(quiz.link, { waitUntil: 'domcontentloaded' });

    // Start quiz button is enabled because the quiz now has at least one question.
    const startBtn = session.page.locator('[data-sikshya-quiz-start]').first();
    await expect(startBtn).toBeVisible({ timeout: 20_000 });
    await expect(startBtn).toBeEnabled();
    await startBtn.click();

    // The quiz form un-hides.
    const form = session.page.locator('[data-sikshya-quiz-form]').first();
    await expect(form).toBeVisible({ timeout: 10_000 });

    // Pick the correct option (index 0 — Yes).
    const correctRadio = session.page.locator(`input[name="question_${question.id}"][value="0"]`).first();
    await expect(correctRadio).toBeVisible();
    await correctRadio.check();

    // Submit and watch the request to /me/quiz-submit so we can assert on the
    // server-side score directly — DOM result rendering varies by theme.
    const [submitResponse] = await Promise.all([
      session.page.waitForResponse(
        (r) => r.url().includes('/me/quiz-submit') && r.request().method() === 'POST',
        { timeout: 20_000 },
      ),
      session.page.locator('.sikshya-quiz-submit').first().click(),
    ]);

    expect(submitResponse.status()).toBeLessThan(400);
    const body = await submitResponse.json().catch(() => ({}));
    expect(body?.ok, JSON.stringify(body)).toBe(true);
    expect(Number(body?.data?.score_percent ?? 0)).toBe(100);
    expect(body?.data?.passed).toBe(true);

    await session.context.close();
  });
});
