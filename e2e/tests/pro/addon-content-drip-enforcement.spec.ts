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
 * Drip *enforcement*, as opposed to rule CRUD.
 *
 * The existing content-drip spec only proves a rule can be saved, listed and
 * deleted. Nothing checked that a rule actually withholds content — which is the
 * entire point of the feature, and the failure mode is silent: a course author
 * schedules a release, the rule saves cleanly, and learners can read everything
 * immediately anyway.
 *
 * Drip plugs into the `sikshya_access_check` filter, so both the lesson page and
 * the completion endpoint should refuse a locked lesson.
 */
test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/drip-rules/.test(r)),
    'content_drip not active or addon disabled',
  );
});

test.describe('addon: content drip enforcement', () => {
  test('a lesson scheduled for later is withheld, while an unscheduled one stays open', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    await request.post('/wp-json/sikshya/v1/admin/addons/content_drip/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const course = await createCourseViaRest(page, request, {
      title: `E2E drip gate ${Date.now()}`,
      type: 'free',
    });
    const openLesson = await createLessonViaRest(page, request, course.id);
    const lockedLesson = await createLessonViaRest(page, request, course.id);

    // Only the second lesson is scheduled, 30 days out.
    const rule = await request.post('/wp-json/sikshya/v1/pro/drip-rules', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        course_id: course.id,
        lesson_id: lockedLesson.id,
        rule_type: 'delay_days',
        rule_value: '30',
      },
    });
    const ruleBody = await rule.text();
    expect(rule.status(), ruleBody.slice(0, 200)).toBeLessThan(400);

    const username = slug('dripstud');
    const password = 'DripPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);
    const enroll = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    expect(enroll.status()).toBeLessThan(400);

    // The unscheduled lesson must be readable.
    await session.page.goto(learnUrl('lesson', openLesson.slug), { waitUntil: 'domcontentloaded' });
    const openText = (await session.page.locator('body').innerText()).toLowerCase();
    test.info().attach('open-lesson', { body: openText.slice(0, 1200) });
    expect(
      openText,
      'a lesson with no drip rule must remain accessible to an enrolled learner',
    ).not.toMatch(/will unlock on|is locked|not yet available/);

    // The scheduled one must not be.
    await session.page.goto(learnUrl('lesson', lockedLesson.slug), { waitUntil: 'domcontentloaded' });
    const lockedText = (await session.page.locator('body').innerText()).toLowerCase();
    test.info().attach('locked-lesson', { body: lockedText.slice(0, 1200) });
    expect(
      lockedText,
      'a lesson dripped 30 days out must be withheld, and the learner told why',
      // The real copy is "This lesson will unlock on <date>"; accept the other
      // phrasings the template may use rather than pinning exact wording.
    ).toMatch(/will unlock on|unlocks on|is locked|not yet available|available on/);

    // And completion must be refused server-side, not merely hidden in the UI.
    //
    // The control below matters: asserting only "status >= 400" would also pass
    // for a typo'd route, since a missing endpoint answers 404. So first prove the
    // endpoint works for the OPEN lesson, then prove it refuses the locked one.
    const openComplete = await session.request.post('/wp-json/sikshya/v1/me/lesson-complete', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, lesson_id: openLesson.id },
    });
    const openCompleteBody = await openComplete.text();
    expect(
      openComplete.ok(),
      `control: the open lesson must be completable, else the assertion below is vacuous; ` +
        `status=${openComplete.status()} body=${openCompleteBody.slice(0, 200)}`,
    ).toBeTruthy();

    const complete = await session.request.post('/wp-json/sikshya/v1/me/lesson-complete', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, lesson_id: lockedLesson.id },
    });
    const completeBody = await complete.text();
    test.info().attach('complete-locked-lesson', { body: completeBody.slice(0, 400) });
    expect(
      complete.status(),
      `completing a withheld lesson must be refused; body=${completeBody.slice(0, 200)}`,
    ).toBeGreaterThanOrEqual(400);
    expect(
      complete.status(),
      'refusal must be an authorization failure, not a missing route',
    ).not.toBe(404);

    await session.context.close();
  });
});
