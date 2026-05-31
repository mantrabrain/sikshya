import { test, expect, type Page } from '@playwright/test';
import {
  attachQuestionsToQuiz,
  createCourseViaRest,
  createQuestionViaRest,
  createQuizViaRest,
  createUserViaRest,
  slug,
  STUDENT_ROLE,
  type QuestionType,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

type Scenario = {
  label: string;
  type: QuestionType;
  options?: string[];
  correct: number | number[] | string;
  /** What the server should report once submitted. Default: 100% pass. */
  expectedScore?: number;
  expectedPassed?: boolean;
  /** Drive the form for this question type. */
  select: (page: Page, qid: number) => Promise<void>;
};

const SCENARIOS: Scenario[] = [
  {
    label: 'true_false',
    type: 'true_false',
    options: ['True', 'False'],
    correct: 0,
    expectedScore: 100,
    async select(page, qid) {
      await page.locator(`input[type="radio"][name="question_${qid}"][value="0"]`).check();
    },
  },
  {
    label: 'multiple_response',
    type: 'multiple_response',
    options: ['Apples', 'Oranges', 'Carrots'],
    correct: [0, 2],
    expectedScore: 100,
    async select(page, qid) {
      await page.locator(`input[type="checkbox"][name="question_${qid}[]"][value="0"]`).check();
      await page.locator(`input[type="checkbox"][name="question_${qid}[]"][value="2"]`).check();
    },
  },
  {
    label: 'short_answer',
    type: 'short_answer',
    correct: 'Paris',
    expectedScore: 100,
    async select(page, qid) {
      await page.locator(`textarea[name="question_${qid}"]`).fill('paris');
    },
  },
  {
    label: 'fill_blank',
    type: 'fill_blank',
    correct: 'photosynthesis',
    expectedScore: 100,
    async select(page, qid) {
      await page.locator(`textarea[name="question_${qid}"]`).fill('Photosynthesis');
    },
  },
  {
    label: 'essay',
    type: 'essay',
    correct: '',
    // Essays auto-grade as 0 (require manual review). Submission still succeeds.
    expectedScore: 0,
    expectedPassed: false,
    async select(page, qid) {
      await page.locator(`textarea[name="question_${qid}"]`).fill('This is my essay response demonstrating mastery.');
    },
  },
  {
    label: 'ordering',
    type: 'ordering',
    options: ['First', 'Second'],
    correct: [0, 1],
    expectedScore: 100,
    async select(page, qid) {
      // The display shuffles items server-side; read the current order and
      // click "Move up" on the second li until [0, 1] is reached.
      const ol = page.locator(`fieldset[data-qid="${qid}"] ol.sikshya-ordering`);
      const items = ol.locator('li[data-item-index]');
      const indices = await items.evaluateAll((els) =>
        els.map((el) => Number((el as HTMLElement).dataset.itemIndex ?? -1)),
      );
      // Two-item case: if [1, 0], swap by moving the second up.
      if (indices.length === 2 && indices[0] === 1 && indices[1] === 0) {
        await items.nth(1).locator('button.sikshya-ordering__up').click();
      }
      // Re-read to assert.
      const finalIdx = await items.evaluateAll((els) =>
        els.map((el) => Number((el as HTMLElement).dataset.itemIndex ?? -1)),
      );
      expect(finalIdx).toEqual([0, 1]);
    },
  },
  {
    label: 'matching',
    type: 'matching',
    // Single-row matching: only one possible pairing, so display shuffle is a no-op.
    correct: JSON.stringify({
      matching: { left: ['Capital of France'], right: ['Paris'], map: [0] },
    }),
    expectedScore: 100,
    async select(page, qid) {
      const select = page.locator(`fieldset[data-qid="${qid}"] select.sikshya-matching__select`).first();
      await expect(select).toBeVisible();
      await select.selectOption({ value: '0' });
    },
  },
];

for (const s of SCENARIOS) {
  const expScoreLabel = `${s.expectedScore ?? 100}%`;
  test(`quiz attempt UI · ${s.label} · server scores ${expScoreLabel}`, async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E ${s.label} ${Date.now()}`,
      type: 'free',
    });
    const quiz = await createQuizViaRest(page, request, course.id);
    const question = await createQuestionViaRest(page, request, {
      type: s.type,
      options: s.options,
      correct: s.correct,
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

    const startBtn = session.page.locator('[data-sikshya-quiz-start]').first();
    await expect(startBtn).toBeEnabled({ timeout: 20_000 });
    await startBtn.click();

    const form = session.page.locator('[data-sikshya-quiz-form]').first();
    await expect(form).toBeVisible({ timeout: 10_000 });

    await s.select(session.page, question.id);

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
    const wantScore = s.expectedScore ?? 100;
    const wantPassed = s.expectedPassed ?? true;
    expect(Number(body?.data?.score_percent ?? -1), `score for ${s.label}`).toBe(wantScore);
    expect(body?.data?.passed, `passed for ${s.label}`).toBe(wantPassed);

    await session.context.close();
  });
}
