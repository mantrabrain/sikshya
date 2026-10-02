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

test.describe('checkout: admin manual fulfillment', () => {
  test('admin can manually enroll a student in a paid course (offline fulfillment path)', async ({
    page,
    request,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E paid course ${Date.now()}`,
      price: 25,
    });

    const username = slug('stud');
    const userRes = await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password: 'StudPass!234',
      role: STUDENT_ROLE,
    });
    expect(userRes.id).toBeGreaterThan(0);

    const nonce = await getAdminNonce(page);
    const enrollRes = await request.post('/wp-json/sikshya/v1/admin/enrollments/manual', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { user_id: userRes.id, course_id: course.id },
    });
    const enrollBody = await enrollRes.json().catch(() => ({}));
    expect(enrollRes.status(), JSON.stringify(enrollBody)).toBeLessThan(400);
  });

  test('a manually enrolled student can actually reach the paid course content', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    // The test above only proves the endpoint answers 2xx. For `manual_enrollment`
    // to be worth anything, the learner must end up with real access to a course
    // they never paid for — which is the whole point of the feature, and also the
    // thing that would silently break if fulfilment stopped writing enrolments.
    const course = await createCourseViaRest(page, request, {
      title: `E2E manual access ${Date.now()}`,
      price: 40,
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    const username = slug('manstud');
    const password = 'ManPass!234';
    const student = await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });

    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);

    // Before enrolment the paid content must be closed.
    //
    // Both calls carry a unique `_cb` value. WebKit will serve the first
    // response from its HTTP cache for an identical later GET, which made the
    // post-enrolment check replay the earlier 403 and look like a product bug.
    const before = await session.request.get(
      `/wp-json/sikshya/v1/me/progress?course_id=${course.id}&_cb=${Date.now()}`,
      { headers: { 'X-WP-Nonce': session.restNonce, 'Cache-Control': 'no-cache' } },
    );
    expect(
      before.status(),
      'control: a non-enrolled learner must not have progress on a paid course',
    ).toBeGreaterThanOrEqual(400);

    const nonce = await getAdminNonce(page);
    const manual = await request.post('/wp-json/sikshya/v1/admin/enrollments/manual', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { user_id: student.id, course_id: course.id },
    });
    expect(manual.status(), (await manual.text()).slice(0, 200)).toBeLessThan(400);

    // After enrolment the same learner must get in.
    const after = await session.request.get(
      `/wp-json/sikshya/v1/me/progress?course_id=${course.id}&_cb=${Date.now()}-after`,
      { headers: { 'X-WP-Nonce': session.restNonce, 'Cache-Control': 'no-cache' } },
    );
    expect(
      after.ok(),
      `manual enrolment must grant access; /me/progress said ${after.status()}`,
    ).toBeTruthy();

    // And the lesson must render rather than showing a purchase gate.
    await session.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
    const text = (await session.page.locator('body').innerText()).toLowerCase();
    test.info().attach('lesson-after-manual-enrolment', { body: text.slice(0, 900) });
    expect(
      text,
      'a manually enrolled learner must not be shown a purchase gate',
    ).not.toMatch(/buy now|purchase this course|enrol now to/);

    await session.context.close();
  });
});
