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
 * `certificates_basic` is a free feature with no learner-facing coverage. The
 * admin side had a list screen test; nothing proved a learner who finishes a
 * course actually receives a certificate, that they can see it, or that the
 * public verification endpoint distinguishes a real code from a forged one.
 *
 * Public verification is the part worth being careful about: it is unauthenticated
 * by design, so it must confirm genuine certificates and reject anything else
 * without leaking whether a given code nearly matched.
 */
test.describe('learner: certificate lifecycle', () => {
  test('completing a course issues a certificate the learner can see and anyone can verify', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);

    const marker = `E2E cert ${Date.now()}`;
    const course = await createCourseViaRest(page, request, { title: marker, type: 'free' });
    const lesson = await createLessonViaRest(page, request, course.id);

    const username = slug('certstud');
    const password = 'CertPass!234';
    const learner = await createUserViaRest(page, request, {
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

    // Finish the only lesson, which completes the course.
    const complete = await session.request.post('/wp-json/sikshya/v1/me/lesson-complete', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, lesson_id: lesson.id },
    });
    expect(complete.ok(), `lesson completion failed: ${(await complete.text()).slice(0, 200)}`).toBeTruthy();

    // Staff view: a certificate row must exist for this learner and course.
    const issued = await request.get('/wp-json/sikshya/v1/admin/issued-certificates?per_page=50', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const issuedText = await issued.text();
    expect(issued.ok(), `GET /admin/issued-certificates status=${issued.status()}`).toBeTruthy();
    test.info().attach('issued-certificates', { body: issuedText.slice(0, 1500) });

    const issuedBody = JSON.parse(issuedText) as Record<string, unknown>;
    const rows = ((issuedBody.certificates ??
      (issuedBody.data as Record<string, unknown>)?.certificates ??
      (issuedBody.data as Record<string, unknown>)?.rows ??
      issuedBody.rows ??
      []) as Array<Record<string, unknown>>);
    const mine = rows.find(
      (r) => Number(r.course_id) === course.id && Number(r.user_id) === learner.id,
    );
    expect(
      mine,
      'completing every lesson must issue a certificate (auto-issue is on by default)',
    ).toBeTruthy();

    const code = String(mine?.verification_code ?? mine?.code ?? '');
    expect(code.length, 'the certificate must carry a verification code').toBeGreaterThan(0);

    // Public verification: genuine code is confirmed...
    const verify = await request.get(
      `/wp-json/sikshya/v1/public/certificates/verify?code=${encodeURIComponent(code)}`,
    );
    const verifyBody = await verify.json().catch(() => ({}));
    expect(verify.ok(), JSON.stringify(verifyBody).slice(0, 200)).toBeTruthy();
    expect(
      verifyBody?.ok,
      'a genuine certificate code must verify',
    ).toBe(true);

    // ...and a forged one is not, without confirming the real certificate exists.
    const forged = await request.get(
      `/wp-json/sikshya/v1/public/certificates/verify?code=${encodeURIComponent(code.slice(0, -4))}dead`,
    );
    const forgedBody = await forged.json().catch(() => ({}));
    expect(
      forgedBody?.ok,
      'a tampered verification code must not verify',
    ).not.toBe(true);

    // Learner view: the certificate must be reachable from their account.
    await session.page.goto('/my-learning/certificates/', { waitUntil: 'domcontentloaded' });
    const certText = (await session.page.locator('body').innerText()).toLowerCase();
    test.info().attach('learner-certificates-page', { body: certText.slice(0, 1200) });
    expect(
      certText,
      'the learner must see the course they completed on their certificates page',
    ).toContain(marker.toLowerCase());

    await session.context.close();
  });
});
