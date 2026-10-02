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
 * Subscription-gated access, both sides of the gate, in a browser.
 *
 * The existing subscriptions spec reads `/pro/plans`. The feature's actual job is
 * to decide who may read a course: a course carrying `_sikshya_required_plan_id`
 * is closed to everyone without an active subscription to that plan, and open to
 * everyone with one. Getting that wrong in either direction is serious — one way
 * gives away paid content, the other locks out paying members.
 */
test.describe('addon: subscription-gated course access', () => {
  test('a plan-gated course is closed without a subscription and open with one', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/subscriptions/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'subscriptions is not included in this licence',
    );

    const stamp = Date.now();

    // A plan.
    const planRes = await request.post('/wp-json/sikshya/v1/pro/plans', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { name: `E2E Plan ${stamp}`, amount: 15, interval_unit: 'month', status: 'active' },
    });
    const planText = await planRes.text();
    test.info().attach('plan-create', { body: planText.slice(0, 400) });
    expect(planRes.status(), `plan creation failed: ${planText.slice(0, 250)}`).toBeLessThan(400);
    const planBody = JSON.parse(planText) as Record<string, unknown>;
    const planId = Number(
      planBody.id ?? planBody.plan_id ?? (planBody.data as Record<string, unknown>)?.id ?? 0,
    );
    expect(planId, `plan id must come back: ${planText.slice(0, 250)}`).toBeGreaterThan(0);

    // A course that requires it.
    const course = await createCourseViaRest(page, request, {
      title: `E2E plan-gated ${stamp}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);
    // Gate the course through the course builder's Pricing tab — the path the
    // admin UI uses. `_sikshya_required_plan_id` is not a REST-registered meta,
    // so writing it via wp/v2 silently does nothing; the precondition below
    // exists because that is exactly what happened on the first attempt, and the
    // access assertions would then have passed for the wrong reason.
    const gateRes = await request.post('/wp-json/sikshya/v1/course-builder/save', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        course_id: course.id,
        title: `E2E plan-gated ${stamp}`,
        description: 'Subscription-gated course.',
        course_status: 'publish',
        course_type: 'subscription',
        required_plan_id: planId,
      },
    });
    const gateText = await gateRes.text();
    test.info().attach('builder-gate-save', { body: gateText.slice(0, 500) });
    expect(gateRes.status(), `gating the course failed: ${gateText.slice(0, 250)}`).toBeLessThan(400);

    // A control course with no plan gate. If enrolment is refused on BOTH, the
    // refusal is not evidence the subscription gate works — it would just mean
    // enrolment is broken. The control must succeed.
    const openCourse = await createCourseViaRest(page, request, {
      title: `E2E ungated control ${stamp}`,
      type: 'free',
    });

    // A learner with no subscription.
    const username = slug('substud');
    const password = 'SubPass!234';
    const learner = await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);

    const control = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: openCourse.id },
    });
    expect(
      control.ok(),
      `control: an ungated free course must be enrollable, else the gate assertion below proves nothing (got ${control.status()})`,
    ).toBeTruthy();

    const enrollBefore = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    const beforeText = await enrollBefore.text();
    test.info().attach('enroll-without-subscription', { body: beforeText.slice(0, 400) });
    expect(
      enrollBefore.status(),
      `a plan-gated course must refuse a learner with no subscription; got ${enrollBefore.status()}: ${beforeText.slice(0, 200)}`,
    ).toBeGreaterThanOrEqual(400);

    await session.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
    const closedText = (await session.page.locator('body').innerText()).toLowerCase();
    test.info().attach('lesson-without-subscription', { body: closedText.slice(0, 900) });
    expect(
      closedText,
      'the lesson must not be readable without a subscription',
    ).toMatch(/subscri|plan|membership|not have access|enrol|purchase/);

    // Give them an active subscription.
    const subRes = await request.post('/wp-json/sikshya/v1/pro/subscriptions', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { plan_id: planId, user_id: learner.id, status: 'active' },
    });
    const subText = await subRes.text();
    test.info().attach('subscription-create', { body: subText.slice(0, 400) });
    expect(subRes.status(), `subscription creation failed: ${subText.slice(0, 250)}`).toBeLessThan(400);

    // Before asserting access, confirm the subscription is actually there and
    // active. Otherwise a failure here is ambiguous: it could mean the gate is
    // broken, or merely that the subscription never took (another spec in the
    // suite bulk-manages plans, and a deleted plan would look identical).
    const subsList = await request.get('/wp-json/sikshya/v1/pro/subscriptions?per_page=50', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const subsListText = await subsList.text();
    test.info().attach('subscriptions-list', { body: subsListText.slice(0, 900) });
    const subsBody = JSON.parse(subsListText || '{}') as Record<string, unknown>;
    const subRows = (((subsBody.data as Record<string, unknown>)?.items ??
      (subsBody.data as Record<string, unknown>)?.subscriptions ??
      subsBody.subscriptions ??
      subsBody.items ??
      []) as Array<Record<string, unknown>>);
    const mySub = subRows.find(
      (r) => Number(r.user_id) === learner.id && Number(r.plan_id) === planId,
    );
    expect(
      mySub,
      `precondition: the learner's subscription to plan ${planId} must exist; ` +
        `saw ${subRows.length} rows: ${subsListText.slice(0, 300)}`,
    ).toBeTruthy();
    expect(
      String(mySub?.status ?? '').toLowerCase(),
      'precondition: the subscription must be active for the gate to open',
    ).toBe('active');

    // Now the same learner must get in.
    const progress = await session.request.get(
      `/wp-json/sikshya/v1/me/progress?course_id=${course.id}&_cb=${Date.now()}`,
      { headers: { 'X-WP-Nonce': session.restNonce, 'Cache-Control': 'no-cache' } },
    );
    const progressText = await progress.text();
    test.info().attach('progress-with-subscription', { body: progressText.slice(0, 400) });
    expect(
      progress.ok(),
      `an active subscription must open the gated course; /me/progress said ${progress.status()}: ${progressText.slice(0, 200)}`,
    ).toBeTruthy();

    await session.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
    const openText = (await session.page.locator('body').innerText()).toLowerCase();
    test.info().attach('lesson-with-subscription', { body: openText.slice(0, 900) });
    expect(
      openText,
      'the lesson must be readable once the learner has an active subscription',
    ).not.toMatch(/requires a subscription|subscribe to access|purchase this course/);

    await session.context.close();
  });
});
