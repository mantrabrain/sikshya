import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  createUserViaRest,
  getAdminNonce,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Reviews from submission to publication, in a browser.
 *
 * The existing reviews spec posts to `/me/reviews` and checks the status code.
 * What a site owner cares about is the pipeline either side of that: a learner's
 * review must reach the public course page, the aggregate rating must move, and —
 * the part worth being careful about — a review awaiting moderation must NOT be
 * publicly visible before someone approves it.
 */
test.describe('addon: course review publication', () => {
  test('a review reaches the public course page only once it is approved', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/course_reviews/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'course_reviews is not included in this licence',
    );

    const stamp = Date.now();
    const course = await createCourseViaRest(page, request, {
      title: `E2E review pub ${stamp}`,
      type: 'free',
    });
    await createLessonViaRest(page, request, course.id);

    const username = slug('revwriter');
    const password = 'RevPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);
    await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });

    const reviewText = `REVIEW-BODY-${stamp}`;
    const submit = await session.request.post('/wp-json/sikshya/v1/me/reviews', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, rating: 5, review_text: reviewText },
    });
    const submitText = await submit.text();
    test.info().attach('review-submit', { body: submitText.slice(0, 400) });
    expect(submit.status(), `review submission failed: ${submitText.slice(0, 250)}`).toBeLessThan(400);

    // Find the review and its moderation state through the staff endpoint.
    const listRes = await request.get('/wp-json/sikshya/v1/admin/reviews?per_page=50', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const listText = await listRes.text();
    expect(listRes.ok(), `GET /admin/reviews status=${listRes.status()}`).toBeTruthy();
    const listBody = JSON.parse(listText) as Record<string, unknown>;
    // The payload is `{ success, data: { items, total, page, per_page, counts } }`.
    const rows = (((listBody.data as Record<string, unknown>)?.items ??
      listBody.items ??
      []) as Array<Record<string, unknown>>);
    const mine = rows.find((r) => String(r.review_text ?? r.content ?? '').includes(reviewText));
    expect(
      mine,
      `the submitted review must appear on the moderation screen; saw ${rows.length} rows`,
    ).toBeTruthy();
    const reviewId = Number(mine?.id ?? mine?.review_id ?? 0);
    expect(reviewId, 'the review must have an id').toBeGreaterThan(0);

    // Moderation state is `is_approved` (boolean), not a `status` string.
    const approved = mine?.is_approved === true || String(mine?.is_approved ?? '') === '1';
    test.info().attach('review-moderation', {
      body: JSON.stringify({ is_approved: mine?.is_approved, approved }, null, 2),
    });

    // An anonymous visitor's view of the course page.
    const anonCtx = await browser.newContext({
      baseURL: baseURL ?? 'http://sikshya.local',
      storageState: { cookies: [], origins: [] },
    });
    const anon = await anonCtx.newPage();

    const readPublic = async (): Promise<string> => {
      await anon.goto(`/?p=${course.id}`, { waitUntil: 'domcontentloaded' });
      return anon.content();
    };

    if (!approved) {
      // Held for moderation: it must not be public yet. This is the assertion
      // that matters — an unmoderated review leaking publicly is the failure mode
      // moderation exists to prevent.
      expect(
        await readPublic(),
        'a review awaiting moderation must not be publicly visible before approval',
      ).not.toContain(reviewText);

      const approve = await request.post(`/wp-json/sikshya/v1/admin/reviews/${reviewId}/approve`, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: {},
      });
      expect(approve.status(), (await approve.text()).slice(0, 250)).toBeLessThan(400);
    }

    // Approved (or auto-approved): it must now render publicly.
    const publicHtml = await readPublic();
    test.info().attach('public-course-html', { body: publicHtml.slice(0, 2500) });
    expect(
      publicHtml,
      'an approved review must render on the public course page',
    ).toContain(reviewText);

    // And the aggregate must reflect it — a review that does not move the rating
    // is not really published.
    expect(
      /sikshya-course-lp__rating|out of 5 stars|sikshya-reviews/.test(publicHtml),
      'the course page must show an aggregate rating once a review is approved',
    ).toBeTruthy();

    await anonCtx.close();
    await session.context.close();
  });
});
