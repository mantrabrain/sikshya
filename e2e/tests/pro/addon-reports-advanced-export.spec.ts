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
 * Advanced reports: the learner self-export must return the learner's own rows
 * and nobody else's.
 *
 * This is the one Advanced-analytics surface a learner can reach directly, so it
 * is also the one place where a missing `user_id` filter would hand one student
 * the whole enrolment table. Two students are enrolled in the *same* course so
 * the only thing distinguishing their rows is the scoping — if the filter were
 * dropped, the second login would appear in the first student's file.
 */
test.describe('addon: advanced reports learner export', () => {
  test('a learner exports their own enrolments and sees no other learner', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post(
      '/wp-json/sikshya/v1/admin/addons/reports_advanced/enable',
      { headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' } },
    );
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'reports_advanced is not included in this licence',
    );

    const courseTitle = `E2E export course ${slug('exp')}`;
    const { id: courseId } = await createCourseViaRest(page, request, {
      title: courseTitle,
      price: 0,
    });
    expect(courseId).toBeGreaterThan(0);

    const mine = `e2e_exp_a_${slug('u')}`;
    const other = `e2e_exp_b_${slug('u')}`;
    const password = 'E2ePass!2026';
    for (const username of [mine, other]) {
      await createUserViaRest(page, request, {
        username,
        email: `${username}@example.com`,
        password,
        role: STUDENT_ROLE,
      });
    }

    // Both learners enrol in the same course.
    const sessions: Array<Awaited<ReturnType<typeof studentSession>>> = [];
    for (const username of [mine, other]) {
      const s = await studentSession(
        browser,
        baseURL ?? 'http://sikshya.local',
        username,
        password,
      );
      sessions.push(s);
      const enroll = await s.request.post('/wp-json/sikshya/v1/me/enroll', {
        headers: { 'X-WP-Nonce': s.restNonce, 'Content-Type': 'application/json' },
        data: { course_id: courseId },
      });
      expect(
        enroll.status(),
        `precondition: ${username} must enrol: ${(await enroll.text()).slice(0, 200)}`,
      ).toBeLessThan(400);
    }

    try {
      const [a] = sessions;
      const res = await a.request.get(
        '/wp-json/sikshya/v1/me/reports-advanced/export?type=my_enrollments',
        { headers: { 'X-WP-Nonce': a.restNonce } },
      );
      const text = await res.text();
      test.info().attach('learner-export', { body: text.slice(0, 1200) });
      test.skip(
        res.status() === 403 && /turned off/i.test(text),
        'learner self-export is disabled in Advanced analytics settings',
      );
      expect(res.status(), `the learner export failed: ${text.slice(0, 300)}`).toBe(200);

      const body = JSON.parse(text) as { ok?: boolean; csv?: string; filename?: string };
      expect(body.ok, 'the export must report success').toBeTruthy();
      expect(body.filename ?? '', 'the file must be named as the learner’s own export').toContain(
        'my-enrollments',
      );

      const csv = body.csv ?? '';
      expect(csv.split('\n')[0], 'the CSV must carry a header row').toContain('learner_login');

      // Precondition: the learner's own row must actually be in the file,
      // otherwise "no other learner appears" would hold for an empty export.
      expect(csv, 'the learner’s own enrolment must be exported').toContain(mine);
      expect(csv, 'the course they enrolled in must be named').toContain(courseTitle);

      // The actual scoping assertion.
      expect(csv, 'another learner’s enrolment must never appear in a self-export').not.toContain(
        other,
      );
    } finally {
      for (const s of sessions) {
        await s.context.close();
      }
    }
  });
});
