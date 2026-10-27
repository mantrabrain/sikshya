import { test, expect } from '@playwright/test';
import { createCourseViaRest, getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The AI studio.
 *
 * The provider call itself is stubbed at the browser boundary — a real
 * generation would spend the site owner's money and return different words
 * every run, and the provider contract is already pinned by
 * `AiAuthoringGenerationTest`. What cannot be covered there is the part that
 * matters most here: that a draft shown on screen, *after it was edited*, is
 * exactly what ends up in the course. So the apply calls are real, and the
 * result is read back from the curriculum API rather than from the page that
 * just wrote it.
 */

const OUTLINE_DRAFT = {
  ok: true,
  title: 'Bookkeeping for small shops',
  summary: 'For owners with no accounting background.',
  chapters: [
    {
      title: 'Daily records',
      summary: 'What to write down every day.',
      lessons: [
        { title: 'The cash book', summary: 'One row per sale.', duration_minutes: 10 },
        { title: 'Keeping receipts', summary: 'Why and how long.', duration_minutes: 8 },
      ],
    },
    {
      title: 'Monthly close',
      summary: 'Tying the month off.',
      lessons: [{ title: 'Reconciling the till', summary: 'Counting against the book.', duration_minutes: 15 }],
    },
  ],
  usage: { period: '2026-10', used: 1, cap: 200, remaining: 199 },
};

const QUIZ_DRAFT = {
  ok: true,
  questions: [
    {
      type: 'multiple_choice',
      text: 'What goes in the cash book?',
      options: ['Every sale', 'Only card sales', 'Nothing', 'Staff names'],
      correct: '0',
      explanation: 'Every sale, on the day it happens.',
      points: 1,
    },
    {
      type: 'true_false',
      text: 'Receipts can be thrown away the same week.',
      options: ['True', 'False'],
      correct: '1',
      explanation: 'They must be kept for the statutory period.',
      points: 1,
    },
  ],
  usage: { period: '2026-10', used: 2, cap: 200, remaining: 198 },
};

async function enableAiAuthoring(page: import('@playwright/test').Page, request: import('@playwright/test').APIRequestContext) {
  const nonce = await getAdminNonce(page);
  const res = await request.post('/wp-json/sikshya/v1/admin/addons/ai_authoring/enable', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
  });
  test.skip(res.status() === 402 || res.status() === 403, 'ai_authoring is not included in this licence');
  return nonce;
}

async function pickCourse(page: import('@playwright/test').Page, title: string) {
  const picker = page.locator('[data-sikshya-ai-course-picker]').last();
  await picker.getByRole('button', { name: /choose a course|select a course/i }).first().click();
  const dialog = page.getByRole('dialog');
  await dialog.getByPlaceholder(/Search courses/i).fill(title);
  await expect(dialog.getByText(title, { exact: false }).first()).toBeVisible({ timeout: 30_000 });
  await dialog.locator('label', { hasText: title }).first().click();
  await dialog.getByRole('button', { name: /apply|save|select|done/i }).last().click();
  await expect(dialog).toBeHidden({ timeout: 15_000 });
}

test.describe('admin: AI studio', () => {
  test('an edited outline draft becomes exactly the chapters and lessons that were left on screen', async ({
    page,
    request,
  }) => {
    const nonce = await enableAiAuthoring(page, request);
    const stamp = Date.now();
    const course = await createCourseViaRest(page, request, { title: `AI Studio Course ${stamp}` });

    await page.route('**/pro/ai/outline**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(OUTLINE_DRAFT),
      }),
    );

    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto('/wp-admin/admin.php?page=sikshya&view=ai-studio', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-sikshya-ai-studio]')).toBeVisible({ timeout: 30_000 });

    await page.locator('[data-sikshya-ai-topic]').fill('Bookkeeping for small retail shops');
    await page.locator('[data-sikshya-ai-generate-outline] button').click();

    const draft = page.locator('[data-sikshya-ai-outline-draft]');
    await expect(draft, 'the draft must be shown before anything is created').toBeVisible({ timeout: 30_000 });
    await expect(draft.locator('[data-sikshya-ai-draft-chapter]')).toHaveCount(2);
    await expect(draft.locator('[data-sikshya-ai-draft-lesson]')).toHaveCount(3);

    // Edit the draft: rename one lesson and drop another. This is the whole
    // reason generation and creation are separate steps.
    const firstLesson = draft.locator('[data-sikshya-ai-draft-lesson]').first();
    await firstLesson.fill(`The cash book (edited ${stamp})`);
    await draft
      .locator('[data-sikshya-ai-draft-chapter]')
      .first()
      .getByRole('button', { name: /^Remove$/ })
      .last()
      .click();
    await expect(draft.locator('[data-sikshya-ai-draft-lesson]')).toHaveCount(2);

    await pickCourse(page, `AI Studio Course ${stamp}`);
    await page.locator('[data-sikshya-ai-apply-outline] button').click();

    await expect(page.locator('[data-sikshya-ai-applied]'), 'the screen must report what it created').toContainText(
      /chapters created/i,
      { timeout: 60_000 },
    );

    // Read the course back from the API, not from the page that wrote it.
    const treeRes = await request.get(
      `/wp-json/sikshya/v1/admin/course-curriculum-tree?course_id=${course.id}`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    expect(treeRes.ok(), `curriculum tree ${treeRes.status()}`).toBeTruthy();
    const tree = await treeRes.json();
    const chapters: { title: string; contents: { title: string; type: string }[] }[] = tree?.data?.chapters ?? [];

    expect(chapters.map((c) => c.title)).toEqual(['Daily records', 'Monthly close']);

    const firstChapterLessons = chapters[0].contents.map((c) => c.title);
    expect(firstChapterLessons, 'the edited title must be what was saved').toContain(
      `The cash book (edited ${stamp})`,
    );
    expect(firstChapterLessons, 'the removed lesson must not have been created').not.toContain('Keeping receipts');
    expect(chapters[1].contents.map((c) => c.title)).toEqual(['Reconciling the till']);

    expect(errors, `console errors: ${errors.join(' | ')}`).toEqual([]);
  });

  test('generated questions become a real quiz in the chosen chapter', async ({ page, request }) => {
    const nonce = await enableAiAuthoring(page, request);
    const stamp = Date.now();
    const course = await createCourseViaRest(page, request, { title: `AI Quiz Course ${stamp}` });

    const chapterRes = await request.post('/wp-json/sikshya/v1/curriculum/chapters', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, title: `AI Chapter ${slug('ch')}` },
    });
    expect(chapterRes.ok(), `chapter create ${chapterRes.status()}`).toBeTruthy();

    await page.route('**/pro/ai/quiz**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(QUIZ_DRAFT) }),
    );

    await page.goto('/wp-admin/admin.php?page=sikshya&view=ai-studio', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-sikshya-ai-studio]')).toBeVisible({ timeout: 30_000 });

    await page.getByRole('tab', { name: /Quiz questions/i }).click();
    await pickCourse(page, `AI Quiz Course ${stamp}`);

    const chapterSelect = page.locator('[data-sikshya-ai-chapter-select]');
    await expect(chapterSelect.locator('option')).toHaveCount(2, { timeout: 30_000 });
    await chapterSelect.selectOption({ index: 1 });

    await page.locator('[data-sikshya-ai-quiz-title]').fill(`AI Knowledge check ${stamp}`);
    await page.locator('[data-sikshya-ai-generate-quiz] button').click();

    const quizDraft = page.locator('[data-sikshya-ai-quiz-draft]');
    await expect(quizDraft).toBeVisible({ timeout: 30_000 });
    await expect(quizDraft.locator('[data-sikshya-ai-draft-question]')).toHaveCount(2);
    // The correct option is marked, so an author can see a wrong answer key
    // before learners do.
    await expect(quizDraft.locator('[data-sikshya-ai-draft-question]').first()).toContainText('✓ Every sale');

    await page.locator('[data-sikshya-ai-apply-quiz] button').click();
    await expect(page.locator('[data-sikshya-ai-applied]')).toContainText(/Quiz created with 2/i, {
      timeout: 60_000,
    });

    const treeRes = await request.get(
      `/wp-json/sikshya/v1/admin/course-curriculum-tree?course_id=${course.id}`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const tree = await treeRes.json();
    const chapters: { contents: { title: string; type: string }[] }[] = tree?.data?.chapters ?? [];
    const quizzes = chapters.flatMap((c) => c.contents.filter((item) => item.type === 'quiz'));

    expect(quizzes.map((q) => q.title)).toContain(`AI Knowledge check ${stamp}`);
  });

  test('with no API key the studio says so instead of failing on the first click', async ({ page, request }) => {
    await enableAiAuthoring(page, request);

    await page.goto('/wp-admin/admin.php?page=sikshya&view=ai-studio', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-sikshya-ai-studio]')).toBeVisible({ timeout: 30_000 });

    // This site has no provider key configured, which is the state every site
    // is in right after enabling the add-on.
    await expect(page.locator('[data-sikshya-ai-unconfigured]')).toContainText(/API key/i);
    await expect(page.locator('[data-sikshya-ai-usage]')).toBeVisible();
  });
});
