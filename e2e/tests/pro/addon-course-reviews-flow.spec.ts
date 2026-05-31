import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  getAdminNonce,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(!routes.some((r) => /\/pro\//.test(r)), 'Sikshya Pro not active');
});

test.describe('addon: course_reviews end-to-end', () => {
  test('enrolled student submits a review and it appears in the public list', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);

    // Ensure the addon is enabled (idempotent).
    await request.post('/wp-json/sikshya/v1/admin/addons/course_reviews/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const course = await createCourseViaRest(page, request, {
      title: `E2E reviews course ${Date.now()}`,
      type: 'free',
    });

    const username = slug('rev');
    const password = 'RevPass!234';
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

    // Student must be enrolled to submit a review.
    const enrollRes = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    expect(enrollRes.status()).toBeLessThan(400);

    // Submit a 5-star review.
    const reviewText = `E2E review ${slug('r')}`;
    const submit = await session.request.post('/wp-json/sikshya/v1/me/reviews', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, rating: 5, review_text: reviewText },
    });
    const submitBody = await submit.json().catch(() => ({}));
    expect(submit.status(), JSON.stringify(submitBody).slice(0, 200)).toBeLessThan(400);
    expect(submitBody?.success, JSON.stringify(submitBody).slice(0, 200)).toBe(true);

    // Public list should contain our review (default site approval = auto).
    const listRes = await request.get(`/wp-json/sikshya/v1/courses/${course.id}/reviews`);
    const listBody = await listRes.json().catch(() => ({}));
    expect(listRes.ok()).toBeTruthy();
    const items: { review_text?: string }[] = listBody?.data?.items ?? listBody?.items ?? [];
    const foundOurs = items.some((r) => (r.review_text ?? '').includes(reviewText));
    expect(foundOurs, `our review should appear in public list; got ${items.length} items`).toBe(true);

    await session.context.close();
  });
});
