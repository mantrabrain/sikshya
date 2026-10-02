import { test, expect } from '@playwright/test';
import {
  createAssignmentViaRest,
  createCourseViaRest,
  createUserViaRest,
  getAdminNonce,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Grading an assignment and the learner seeing the result — both sides.
 *
 * The existing gradebook spec hits `/pro/gradebook` and checks it answers. That
 * says nothing about the feature's purpose: a learner submits work, staff grade
 * it, and the learner is told. This drives that round trip and checks the grade
 * surfaces back to the learner who earned it.
 *
 * It also checks the grade does NOT surface to a different learner, since the
 * gradebook reads across everyone's submissions.
 */
test.describe('addon: gradebook grading round-trip', () => {
  test('staff grade a submission and the learner sees the grade and feedback', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/gradebook/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'gradebook is not included in this licence',
    );

    const stamp = Date.now();
    const course = await createCourseViaRest(page, request, {
      title: `E2E gradebook ${stamp}`,
      type: 'free',
    });
    const assignment = await createAssignmentViaRest(page, request, course.id, {
      title: `E2E graded assignment ${stamp}`,
      type: 'essay',
      points: 20,
    });

    // The learner who submits.
    const username = slug('gradee');
    const password = 'GradePass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const learner = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);
    await learner.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': learner.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });

    const essay = `ESSAY-${stamp}`;
    const submitRes = await learner.request.post('/wp-json/sikshya/v1/me/assignment-submit', {
      headers: { 'X-WP-Nonce': learner.restNonce, 'Content-Type': 'application/json' },
      data: { assignment_id: assignment.id, course_id: course.id, content: essay },
    });
    const submitText = await submitRes.text();
    test.info().attach('assignment-submit', { body: submitText.slice(0, 400) });
    expect(submitRes.status(), `submission failed: ${submitText.slice(0, 250)}`).toBeLessThan(400);

    // Staff view: find the submission through the gradebook.
    const subsRes = await request.get(
      `/wp-json/sikshya/v1/pro/gradebook/assignment-submissions?course_id=${course.id}&per_page=50`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const subsText = await subsRes.text();
    test.info().attach('gradebook-submissions', { body: subsText.slice(0, 1200) });
    expect(subsRes.ok(), `GET assignment-submissions status=${subsRes.status()}`).toBeTruthy();

    const subsBody = JSON.parse(subsText) as Record<string, unknown>;
    const rows = ((subsBody.submissions ??
      (subsBody.data as Record<string, unknown>)?.submissions ??
      subsBody.rows ??
      (subsBody.data as Record<string, unknown>)?.rows ??
      []) as Array<Record<string, unknown>>);
    const mine = rows.find((r) => Number(r.assignment_id) === assignment.id);
    expect(
      mine,
      `the learner's submission must appear in the gradebook; saw ${rows.length} rows`,
    ).toBeTruthy();
    const submissionId = Number(mine?.id ?? mine?.submission_id ?? 0);
    expect(submissionId, 'the submission must have an id').toBeGreaterThan(0);

    // Grade it.
    const feedback = `FEEDBACK-${stamp}`;
    const grade = await request.post('/wp-json/sikshya/v1/pro/gradebook/assignment-grade', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        submission_id: submissionId,
        course_id: course.id,
        grade: 18,
        feedback,
        status: 'graded',
      },
    });
    const gradeText = await grade.text();
    test.info().attach('grade-response', { body: gradeText.slice(0, 400) });
    expect(grade.status(), `grading failed: ${gradeText.slice(0, 250)}`).toBeLessThan(400);

    // The learner must be told.
    const fb = await learner.request.get(
      `/wp-json/sikshya/v1/me/assignment-feedback?assignment_id=${assignment.id}&course_id=${course.id}&_cb=${Date.now()}`,
      { headers: { 'X-WP-Nonce': learner.restNonce, 'Cache-Control': 'no-cache' } },
    );
    const fbText = await fb.text();
    test.info().attach('learner-feedback', { body: fbText.slice(0, 600) });
    expect(fb.ok(), `GET /me/assignment-feedback status=${fb.status()}`).toBeTruthy();
    expect(
      fbText,
      'the learner must receive the feedback their work was given',
    ).toContain(feedback);
    expect(
      /\b18\b/.test(fbText),
      `the learner must see the awarded grade; body=${fbText.slice(0, 300)}`,
    ).toBeTruthy();

    // A different learner must not see it.
    const otherName = slug('gradeother');
    await createUserViaRest(page, request, {
      username: otherName,
      email: `${otherName}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const other = await studentSession(browser, baseURL ?? 'http://sikshya.local', otherName, password);
    await other.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': other.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    const otherFb = await other.request.get(
      `/wp-json/sikshya/v1/me/assignment-feedback?assignment_id=${assignment.id}&course_id=${course.id}&_cb=${Date.now()}`,
      { headers: { 'X-WP-Nonce': other.restNonce, 'Cache-Control': 'no-cache' } },
    );
    expect(
      await otherFb.text(),
      "one learner must never be shown another learner's feedback",
    ).not.toContain(feedback);

    await other.context.close();
    await learner.context.close();
  });
});
