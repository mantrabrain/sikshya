import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  createUserViaRest,
  getAdminNonce,
  loginAs,
  slug,
  INSTRUCTOR_ROLE,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';
import { freshContext } from '../../utils/context';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * `instructor_dashboard` is entitled at the starter tier and had no coverage.
 * It renders earnings and learner stats onto the account page for instructors —
 * which means it reads other people's enrolment data, so the scoping matters as
 * much as the rendering.
 */
test.describe('addon: instructor dashboard panels', () => {
  test('an instructor sees their own learner stats on the account page; a student does not', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enableRes = await request.post(
      '/wp-json/sikshya/v1/admin/addons/instructor_dashboard/enable',
      { headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' } },
    );
    test.skip(
      enableRes.status() === 402 || enableRes.status() === 403,
      'instructor_dashboard not entitled on this licence tier',
    );

    const marker = `E2E instrdash ${Date.now()}`;
    const instructorName = slug('instr');
    const password = 'InstrPass!234';
    const instructor = await createUserViaRest(page, request, {
      username: instructorName,
      email: `${instructorName}@example.com`,
      password,
      role: INSTRUCTOR_ROLE,
    });

    // A course owned by that instructor, with one enrolled learner.
    const course = await createCourseViaRest(page, request, {
      title: marker,
      type: 'free',
      authorId: instructor.id,
    });
    await createLessonViaRest(page, request, course.id);

    const studentName = slug('idstud');
    await createUserViaRest(page, request, {
      username: studentName,
      email: `${studentName}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const learner = await studentSession(browser, baseURL ?? 'http://sikshya.local', studentName, password);
    await learner.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': learner.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });

    // A student must not reach the instructor view, which exposes earnings and
    // other learners' enrolment activity.
    await learner.page.goto('/my-learning/instructor/', { waitUntil: 'domcontentloaded' });
    const learnerBody = (await learner.page.locator('body').innerText()).toLowerCase();
    expect(
      learnerBody,
      'a student must never be shown instructor earnings',
    ).not.toContain('lifetime earnings');
    expect(
      learnerBody,
      "a student must not be shown another instructor's learner list",
    ).not.toContain('recent learners');
    await learner.context.close();

    // The instructor's account page must show them.
    const instructorCtx = await freshContext(browser, baseURL);
    const instructorPage = await loginAs(instructorCtx, instructorName, password);
    // The addon renders onto the instructor sub-view, not the account root.
    await instructorPage.goto('/my-learning/instructor/', { waitUntil: 'domcontentloaded' });

    const text = (await instructorPage.locator('body').innerText()).toLowerCase();
    test.info().attach('instructor-dashboard-text', { body: text.slice(0, 2000) });

    expect(
      text,
      'the instructor dashboard must render its stat tiles on the account page',
    ).toContain('lifetime earnings');
    expect(text).toContain('recent learners');

    await instructorCtx.close();
  });
});
