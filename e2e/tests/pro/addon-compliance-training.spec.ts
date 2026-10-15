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

/**
 * Mandatory training, end to end.
 *
 * The risk this feature carries is a report that is confidently wrong, so the
 * assertions are about the figures as much as the mechanics: a requirement must
 * assign its audience, a learner must see their own obligation with a derived
 * status, and the audit report must count them. The deadline case is checked as
 * a contrast — an obligation due soon must not read the same as one already met.
 */
test.describe('addon: compliance and mandatory training', () => {
  test('a requirement assigns its audience, the learner sees it, and the report counts it', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post(
      '/wp-json/sikshya/v1/admin/addons/compliance_training/enable',
      { headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' } },
    );
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'compliance_training is not included in this licence',
    );

    const stamp = Date.now();
    const course = await createCourseViaRest(page, request, {
      title: `E2E fire safety ${stamp}`,
      type: 'free',
    });

    // A learner who will be in the audience.
    const username = slug('compstud');
    const password = 'CompPass!2026';
    const created = await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    expect(created.id, 'the learner must exist to be assigned').toBeGreaterThan(0);

    // Mandatory for every student, due in three days — inside the warning window.
    const create = await request.post('/wp-json/sikshya/v1/pro/compliance/requirements', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        course_id: course.id,
        title: `Annual fire safety ${stamp}`,
        audience_type: 'role',
        audience_key: STUDENT_ROLE,
        due_days: 3,
        recurs_every_days: 365,
      },
    });
    const createText = await create.text();
    test.info().attach('requirement-create', { body: createText.slice(0, 500) });
    expect(create.status(), `creating the requirement failed: ${createText.slice(0, 250)}`).toBeLessThan(400);

    const body = JSON.parse(createText) as { id?: number; assigned?: number };
    const requirementId = Number(body.id ?? 0);
    expect(requirementId, 'the requirement must have an id').toBeGreaterThan(0);
    expect(
      Number(body.assigned ?? 0),
      'creating a requirement must assign its audience immediately, not wait for a cron tick',
    ).toBeGreaterThan(0);

    // An audience that names nothing must be refused rather than reporting 100%
    // compliant with nobody in it.
    const empty = await request.post('/wp-json/sikshya/v1/pro/compliance/requirements', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, audience_type: 'role', audience_key: '' },
    });
    expect(
      empty.status(),
      'a requirement whose audience names nobody must be refused',
    ).toBe(400);

    // The admin report must count the obligation.
    const report = await request.get(`/wp-json/sikshya/v1/pro/compliance/requirements/${requirementId}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const reportBody = (await report.json().catch(() => ({}))) as {
      summary?: { total?: number; compliant?: number; compliance_percent?: number };
      assignments?: Array<{ user_id?: number; status?: string }>;
    };
    test.info().attach('requirement-report', { body: JSON.stringify(reportBody).slice(0, 800) });
    expect(report.ok(), 'the requirement report must be readable').toBeTruthy();
    expect(Number(reportBody.summary?.total ?? 0), 'the report must count the audience').toBeGreaterThan(0);
    expect(
      Number(reportBody.summary?.compliant ?? -1),
      'nobody has done the training yet, so nobody is compliant',
    ).toBe(0);
    expect(
      (reportBody.assignments ?? []).some((a) => Number(a.user_id) === created.id),
      'the learner must appear on the requirement',
    ).toBeTruthy();

    // The learner's own view.
    const learner = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);
    try {
      const mine = await learner.request.get('/wp-json/sikshya/v1/me/compliance', {
        headers: { 'X-WP-Nonce': learner.restNonce },
      });
      const mineBody = (await mine.json().catch(() => ({}))) as {
        obligations?: Array<{ course_id?: number; status?: string; due_at?: string }>;
      };
      test.info().attach('me-compliance', { body: JSON.stringify(mineBody).slice(0, 600) });

      const own = (mineBody.obligations ?? []).find((o) => Number(o.course_id) === course.id);
      expect(own, 'a learner must see the training assigned to them').toBeTruthy();
      expect(
        own?.status,
        'an obligation three days out is due soon, not merely assigned and not already met',
      ).toBe('due_soon');
      expect(own?.due_at, 'a deadline must be reported so the learner knows when').toBeTruthy();
    } finally {
      await learner.context.close();
    }

    // A waiver has to be auditable, so it cannot be recorded without a reason.
    const assignmentId = Number((reportBody.assignments ?? [])[0]
      ? ((reportBody.assignments ?? [])[0] as { assignment_id?: number }).assignment_id ?? 0
      : 0);
    if (assignmentId > 0) {
      const noReason = await request.post(
        `/wp-json/sikshya/v1/pro/compliance/assignments/${assignmentId}/waive`,
        {
          headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
          data: { reason: '   ' },
        },
      );
      expect(
        noReason.status(),
        'a waiver with no reason is unauditable and must be refused',
      ).toBe(400);
    }

    // And the site-wide roll-up must include this requirement.
    const overall = await request.get('/wp-json/sikshya/v1/pro/compliance/report', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const overallBody = (await overall.json().catch(() => ({}))) as {
      requirements?: Array<{ requirement_id?: number }>;
      overall?: { total?: number };
    };
    expect(overall.ok(), 'the site-wide compliance report must be readable').toBeTruthy();
    expect(
      (overallBody.requirements ?? []).some((r) => Number(r.requirement_id) === requirementId),
      'the new requirement must appear in the site-wide report',
    ).toBeTruthy();
    expect(Number(overallBody.overall?.total ?? 0)).toBeGreaterThan(0);

    // Clean up so the dev site is not left with a recurring obligation.
    await request.delete(`/wp-json/sikshya/v1/pro/compliance/requirements/${requirementId}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
  });
});
