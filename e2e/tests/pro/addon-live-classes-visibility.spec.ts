import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  createUserViaRest,
  getAdminNonce,
  learnUrl,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Live class details, and who is allowed to see them.
 *
 * Live Classes has no REST surface of its own — it hangs meeting details off
 * lesson meta and renders them into the learn shell and account dashboard. The
 * detail that matters is a join URL: it is effectively a credential, so an enrolled
 * learner must see it and a visitor who has not enrolled must not. A leaked meeting
 * URL lets anyone walk into a paid live session.
 */
test.describe('addon: live class visibility', () => {
  test('an enrolled learner sees the join link; a non-enrolled visitor does not', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/live_classes/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'live_classes is not included in this licence',
    );

    const stamp = Date.now();
    const joinUrl = `https://meet.example.test/room-${stamp}`;

    const course = await createCourseViaRest(page, request, {
      title: `E2E live ${stamp}`,
      price: 25, // paid, so access actually matters
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    const setMeta = await request.post(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        meta: {
          _sikshya_live_meeting_url: joinUrl,
          _sikshya_live_provider: 'zoom',
          _sikshya_live_duration_minutes: 45,
        },
      },
    });
    const setText = await setMeta.text();
    expect(setMeta.status(), `writing live meta failed: ${setText.slice(0, 250)}`).toBeLessThan(400);

    // Precondition: the meta must have actually persisted. These keys are
    // registered by Pro, not Free, so a disabled addon would silently drop them
    // and every assertion below would pass for the wrong reason.
    const verify = await request.get(`/wp-json/wp/v2/sik_lesson/${lesson.id}?context=edit`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const verifyBody = await verify.json().catch(() => ({}));
    expect(
      String(verifyBody?.meta?._sikshya_live_meeting_url ?? ''),
      'precondition: the meeting URL must be stored on the lesson',
    ).toBe(joinUrl);

    // A visitor who has not enrolled must not be given the join URL.
    const anonCtx = await browser.newContext({
      baseURL: baseURL ?? 'http://sikshya.local',
      storageState: { cookies: [], origins: [] },
    });
    const anon = await anonCtx.newPage();
    await anon.goto(`/?p=${course.id}`, { waitUntil: 'domcontentloaded' });
    const anonHtml = await anon.content();
    expect(
      anonHtml,
      'a live meeting URL must never appear on the public course page — it is a credential',
    ).not.toContain(joinUrl);

    await anon.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
    const anonLesson = await anon.content();
    expect(
      anonLesson,
      'a non-enrolled visitor must not be given the join URL on the lesson route either',
    ).not.toContain(joinUrl);
    await anonCtx.close();

    // An enrolled learner must see it.
    const username = slug('livestud');
    const password = 'LivePass!234';
    const learner = await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const manual = await request.post('/wp-json/sikshya/v1/admin/enrollments/manual', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { user_id: learner.id, course_id: course.id },
    });
    expect(manual.status(), 'enrolling the learner must succeed').toBeLessThan(400);

    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);
    await session.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
    const learnerHtml = await session.page.content();
    test.info().attach('learner-lesson-html', {
      body: learnerHtml.slice(Math.max(0, learnerHtml.indexOf('live') - 200), learnerHtml.indexOf('live') + 1200),
    });
    expect(
      learnerHtml,
      'an enrolled learner must be given the live class join link',
    ).toContain(joinUrl);

    await session.context.close();
  });
});
