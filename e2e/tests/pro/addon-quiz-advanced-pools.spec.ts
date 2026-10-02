import { test, expect } from '@playwright/test';
import { createQuestionViaRest, createQuizViaRest, createCourseViaRest, getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Question banks: does a bank actually resolve to the questions put in it?
 *
 * Quiz Advanced draws quiz questions from a pool — a `sikshya_qbank` taxonomy term
 * (or a legacy meta tag) — so everything downstream depends on that resolution being
 * right. Resolving too few means short quizzes; resolving the wrong bank means
 * learners are asked questions from another topic entirely.
 *
 * Scope note: the quiz-side configuration (`_sikshya_quiz_bank_tag`,
 * `_sikshya_quiz_bank_count`) is not registered for REST, so it can only be set
 * through the admin form. That makes the full learner-draw path unreachable from an
 * API-driven setup; this covers the resolution the draw depends on, through the
 * addon's own `pool-preview` endpoint, and the gap is stated rather than implied.
 */
test.describe('addon: quiz advanced question banks', () => {
  test('a bank resolves to exactly the questions assigned to it', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/quiz_advanced/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'quiz_advanced is not included in this licence',
    );

    const stamp = Date.now();
    const bankName = `E2E Bank ${stamp}`;

    // Create the bank term.
    const term = await request.post('/wp-json/wp/v2/sikshya_qbank', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { name: bankName },
    });
    const termText = await term.text();
    test.info().attach('bank-term', { body: termText.slice(0, 400) });
    expect(term.status(), `creating the bank failed: ${termText.slice(0, 250)}`).toBeLessThan(400);
    const termBody = JSON.parse(termText) as { id?: number; slug?: string };
    const termId = Number(termBody.id ?? 0);
    const termSlug = String(termBody.slug ?? '');
    expect(termId, 'the bank must have an id').toBeGreaterThan(0);
    expect(termSlug, 'the bank must have a slug — pool-preview resolves by slug').toBeTruthy();

    // Baseline: an empty bank must resolve to nothing. Without this, a later
    // non-zero count could come from questions that were already lying around.
    const emptyPreview = await request.get(
      `/wp-json/sikshya/v1/pro/quiz-advanced/pool-preview?tag=${encodeURIComponent(termSlug)}`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const emptyBody = await emptyPreview.json().catch(() => ({}));
    expect(emptyPreview.ok(), `pool-preview status=${emptyPreview.status()}`).toBeTruthy();
    expect(
      Number(emptyBody?.combined_count ?? -1),
      'a freshly created bank must resolve to zero questions',
    ).toBe(0);

    // Put three questions in the bank, and one outside it as a negative control.
    const course = await createCourseViaRest(page, request, {
      title: `E2E bank course ${stamp}`,
      type: 'free',
    });
    const quiz = await createQuizViaRest(page, request, course.id, {
      title: `E2E bank quiz ${stamp}`,
    });

    const inBank: number[] = [];
    for (let i = 0; i < 3; i++) {
      const q = await createQuestionViaRest(page, request, quiz.id, {
        title: `E2E banked Q${i} ${stamp}`,
        type: 'true_false',
      });
      const assign = await request.post(`/wp-json/wp/v2/sik_question/${q.id}`, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: { sikshya_qbank: [termId] },
      });
      expect(assign.status(), `assigning question ${q.id} to the bank failed`).toBeLessThan(400);
      inBank.push(q.id);
    }

    const outsider = await createQuestionViaRest(page, request, quiz.id, {
      title: `E2E unbanked Q ${stamp}`,
      type: 'true_false',
    });

    // The bank must resolve to exactly the three assigned questions.
    const preview = await request.get(
      `/wp-json/sikshya/v1/pro/quiz-advanced/pool-preview?tag=${encodeURIComponent(termSlug)}`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const previewText = await preview.text();
    test.info().attach('pool-preview', { body: previewText.slice(0, 700) });
    expect(preview.ok(), `pool-preview status=${preview.status()}`).toBeTruthy();

    const body = JSON.parse(previewText) as {
      combined_count?: number;
      taxonomy_count?: number;
      sample_question_ids?: number[];
    };
    expect(
      Number(body.combined_count ?? 0),
      `the bank must resolve to the 3 assigned questions; body=${previewText.slice(0, 300)}`,
    ).toBe(3);

    const sample = (body.sample_question_ids ?? []).map(Number);
    for (const id of inBank) {
      expect(sample, `question ${id} was assigned to the bank and must be in the pool`).toContain(id);
    }
    expect(
      sample,
      'a question that was never assigned must not appear in the bank — that would mix topics',
    ).not.toContain(outsider.id);
  });
});
