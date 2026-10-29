import { test, expect } from '@playwright/test';
import {
  attachQuestionsToQuiz,
  createCourseViaRest,
  createQuestionViaRest,
  createQuizViaRest,
  createUserViaRest,
  getAdminNonce,
  learnUrl,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Question media, end to end.
 *
 * Two things here are only observable over real HTTP. One: an `<img>` loads a
 * *subresource*, where `Content-Disposition` is not consulted — which is the
 * whole reason question media can ride the ordinary download token and leave
 * the proxy's inline allowlist at one entry. That claim is asserted here rather
 * than assumed. Two: the alt text has to survive into the rendered attribute,
 * because an image stem without it is a question some learners cannot attempt.
 */
test.describe('learner: question media', () => {
  test('an image question renders from a signed URL that is still a download', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const stamp = Date.now();
    const alt = `A supply curve crossing demand at 40 units ${stamp}`;

    // Smallest valid PNG: 1x1, transparent.
    const png = Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYGBgAAAABQABh6FO1AAAAABJRU5ErkJggg==',
      'base64',
    );

    const upload = await request.post('/wp-json/wp/v2/media', {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: { name: `curve-${stamp}.png`, mimeType: 'image/png', buffer: png },
        title: `E2E curve ${stamp}`,
      },
    });
    expect(upload.status(), (await upload.text()).slice(0, 200)).toBeLessThan(400);
    const mediaId = Number(((await upload.json()) as { id?: number }).id ?? 0);
    expect(mediaId).toBeGreaterThan(0);

    const course = await createCourseViaRest(page, request, {
      title: `E2E qmedia course ${stamp}`,
      type: 'free',
    });
    const quiz = await createQuizViaRest(page, request, course.id, `E2E qmedia quiz ${stamp}`);
    const question = await createQuestionViaRest(page, request, {
      title: 'What is the equilibrium price?',
      type: 'multiple_choice',
      options: ['£10', '£12', '£14'],
      correct: 1,
    });

    const save = await request.post(`/wp-json/wp/v2/sik_question/${question.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        meta: {
          _sikshya_question_media_id: mediaId,
          _sikshya_question_media_kind: 'image',
          _sikshya_question_media_alt: alt,
        },
      },
    });
    const saveText = await save.text();
    expect(save.status(), `saving question media failed: ${saveText.slice(0, 250)}`).toBeLessThan(400);
    const saved = JSON.parse(saveText) as { meta?: Record<string, unknown> };
    expect(
      Number(saved.meta?._sikshya_question_media_id ?? 0),
      'the media id must persist; the key has to be REST-registered',
    ).toBe(mediaId);
    expect(String(saved.meta?._sikshya_question_media_alt ?? '')).toBe(alt);

    await attachQuestionsToQuiz(page, request, quiz.id, [question.id]);

    const username = slug('qmedstud');
    const password = 'QMedPass!2026';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);
    try {
      const enroll = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
        headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
        data: { course_id: course.id },
      });
      expect(enroll.status(), 'precondition: the learner must enrol').toBeLessThan(400);

      await session.page.goto(learnUrl('quiz', quiz.slug), { waitUntil: 'domcontentloaded' });

      // The question fieldsets are rendered but hidden until the attempt
      // starts, so the image is only *visible* after Start — which is also the
      // only state in which it matters.
      const startBtn = session.page.locator('[data-sikshya-quiz-start]').first();
      await expect(startBtn).toBeEnabled({ timeout: 20_000 });
      await startBtn.click();
      await expect(session.page.locator('[data-sikshya-quiz-form]').first()).toBeVisible({
        timeout: 10_000,
      });

      const img = session.page.locator('img.sikshya-quizQ__mediaImage');
      await expect(img, 'an image question must render its image').toBeVisible({ timeout: 30_000 });

      expect(
        await img.getAttribute('alt'),
        'the description is what makes the question answerable with a screen reader',
      ).toBe(alt);

      const src = String(await img.getAttribute('src'));
      expect(src, 'question media must go through the signed proxy').toContain('/sikshya/v1/file/');
      expect(src, 'the raw uploads path must not reach the page').not.toContain('/uploads/');

      // The image must still be there to see, and the options with it.
      await expect(session.page.locator('input[type="radio"]').first()).toBeVisible();

      const fetched = await session.request.get(src);
      expect(fetched.status(), 'the learner the token was issued to must be able to load it').toBe(200);
      expect(fetched.headers()['content-type']).toContain('image/png');
      // The point of the design: `<img>` ignores Content-Disposition, so this
      // stays `attachment` and the proxy's inline allowlist never has to grow.
      expect(
        fetched.headers()['content-disposition'],
        'question media must not need inline delivery',
      ).toContain('attachment');

      // And the browser really did paint it, rather than showing a broken icon.
      const painted = await img.evaluate(
        (el) => (el as HTMLImageElement).naturalWidth > 0 && (el as HTMLImageElement).complete,
      );
      expect(painted, 'the image must actually load through the signed URL').toBeTruthy();
    } finally {
      await session.context.close();
    }
  });
});
