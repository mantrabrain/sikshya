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
 * Cohorts: the behaviour that justifies the feature.
 *
 * Ordinary content drip counts from each learner's own enrolment date, so two
 * people who signed up a month apart reach week two a month apart. A scheduled
 * intake has to do the opposite — everyone in the January cohort reaches week
 * two on the same day, however late they joined.
 *
 * The test is a contrast, because a single assertion could pass for the wrong
 * reason. One lesson, one drip rule of ten days, two learners who both enrol
 * today. The one outside a cohort must find it locked; the one in a cohort that
 * started thirty days ago must find it open. If the anchor were not moving, both
 * would be locked.
 */
test.describe('addon: cohort-anchored content drip', () => {
  test('a dripped lesson is locked by enrolment date but open on a past cohort start', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);

    for (const addon of ['cohorts', 'content_drip']) {
      const res = await request.post(`/wp-json/sikshya/v1/admin/addons/${addon}/enable`, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      });
      test.skip(
        res.status() === 402 || res.status() === 403,
        `${addon} is not included in this licence`,
      );
    }

    const stamp = Date.now();
    const course = await createCourseViaRest(page, request, {
      title: `E2E cohort drip ${stamp}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    // Ten days after whatever the anchor turns out to be.
    const rule = await request.post('/wp-json/sikshya/v1/pro/drip-rules', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        course_id: course.id,
        lesson_id: lesson.id,
        rule_type: 'delay_days',
        rule_value: '10',
      },
    });
    expect(rule.status(), (await rule.text()).slice(0, 200)).toBeLessThan(400);

    const password = 'CohortPass!2026';
    const soloName = slug('cohsolo');
    const memberName = slug('cohmember');
    for (const u of [soloName, memberName]) {
      await createUserViaRest(page, request, {
        username: u,
        email: `${u}@example.com`,
        password,
        role: STUDENT_ROLE,
      });
    }

    // Control: enrols today, in no cohort. Drip counts from today, so the
    // lesson is ten days away.
    const solo = await studentSession(browser, baseURL ?? 'http://sikshya.local', soloName, password);
    try {
      const enroll = await solo.request.post('/wp-json/sikshya/v1/me/enroll', {
        headers: { 'X-WP-Nonce': solo.restNonce, 'Content-Type': 'application/json' },
        data: { course_id: course.id },
      });
      expect(enroll.status(), 'precondition: the control learner must enrol').toBeLessThan(400);

      await solo.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
      const soloText = (await solo.page.locator('body').innerText()).toLowerCase();
      test.info().attach('control-learner', { body: soloText.slice(0, 1000) });
      expect(
        soloText,
        'precondition: without a cohort, a ten-day drip must still withhold the lesson',
      ).toMatch(/will unlock on|is locked|not yet available/);
    } finally {
      await solo.context.close();
    }

    // The intake started thirty days ago, so ten days after its start is twenty
    // days in the past.
    const startedAt = new Date(Date.now() - 30 * 86_400_000).toISOString().slice(0, 19).replace('T', ' ');
    const createCohort = await request.post('/wp-json/sikshya/v1/pro/cohorts', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        course_id: course.id,
        name: `January intake ${stamp}`,
        starts_at: startedAt,
      },
    });
    const cohortText = await createCohort.text();
    test.info().attach('cohort-create', { body: cohortText.slice(0, 400) });
    expect(createCohort.status(), `creating the cohort failed: ${cohortText.slice(0, 250)}`).toBeLessThan(400);
    const cohort = JSON.parse(cohortText) as { id?: number; cohort?: { state?: string } };
    const cohortId = Number(cohort.id ?? 0);
    expect(cohortId, 'the cohort must have an id').toBeGreaterThan(0);
    expect(cohort.cohort?.state, 'an intake that has started is running').toBe('running');

    // Joining the intake enrols the learner, so no separate enrolment call.
    const addMember = await request.post(
      `/wp-json/sikshya/v1/pro/cohorts/${cohortId}/members`,
      {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: { email: `${memberName}@example.com` },
      },
    );
    expect(addMember.status(), (await addMember.text()).slice(0, 250)).toBeLessThan(400);

    const member = await studentSession(
      browser,
      baseURL ?? 'http://sikshya.local',
      memberName,
      password,
    );
    try {
      // Joining must have granted the course on its own.
      const progress = await member.request.get(
        `/wp-json/sikshya/v1/me/progress?course_id=${course.id}`,
        { headers: { 'X-WP-Nonce': member.restNonce } },
      );
      expect(
        progress.status(),
        'joining an intake must grant its course without a separate enrolment',
      ).toBeLessThan(400);

      // The learner must see their intake.
      const mine = await member.request.get('/wp-json/sikshya/v1/me/cohorts', {
        headers: { 'X-WP-Nonce': member.restNonce },
      });
      const mineBody = (await mine.json().catch(() => ({}))) as {
        cohorts?: Array<{ id?: number; state?: string }>;
      };
      test.info().attach('me-cohorts', { body: JSON.stringify(mineBody).slice(0, 500) });
      expect(
        (mineBody.cohorts ?? []).some((c) => Number(c.id) === cohortId),
        'a learner must see the intake they are in',
      ).toBeTruthy();

      // The assertion the feature exists for.
      await member.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
      const memberText = (await member.page.locator('body').innerText()).toLowerCase();
      test.info().attach('cohort-learner', { body: memberText.slice(0, 1000) });
      expect(
        memberText,
        'the same lesson must be open to a cohort member, because the ten-day drip ' +
          'counts from the intake start thirty days ago rather than from today',
      ).not.toMatch(/will unlock on|is locked|not yet available/);
    } finally {
      await member.context.close();
    }
  });
});
