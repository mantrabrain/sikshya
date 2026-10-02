import { test, expect } from '@playwright/test';
import {
  createAssignmentViaRest,
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
 * `calendar` is entitled at the starter tier and had no coverage at all. It
 * builds learner and staff schedules from assignment due dates, so the thing
 * worth pinning is that a due date actually reaches the learner's feed — and
 * that one learner's schedule never contains another's course.
 */
test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/learner\/calendar/.test(r)),
    'calendar addon not active',
  );
});

test.describe('addon: calendar schedules', () => {
  test('an assignment due date reaches the enrolled learner calendar, and is scoped to them', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    await request.post('/wp-json/sikshya/v1/admin/addons/calendar/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const marker = `E2E calendar ${Date.now()}`;
    const course = await createCourseViaRest(page, request, { title: marker, type: 'free' });
    await createLessonViaRest(page, request, course.id);
    const assignment = await createAssignmentViaRest(page, request, course.id, {
      title: `${marker} assignment`,
      type: 'essay',
    });

    // Due in a week, so it lands inside any sane feed window.
    const due = new Date(Date.now() + 7 * 86_400_000).toISOString().slice(0, 10);
    const setDue = await request.post(`/wp-json/wp/v2/sik_assignment/${assignment.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { meta: { _sikshya_assignment_due_date: due } },
    });
    expect(setDue.status(), 'due date must be writable via REST').toBeLessThan(400);

    // Enrolled learner.
    const username = slug('calstud');
    const password = 'CalPass!234';
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

    // Learner feed.
    const mine = await session.request.get('/wp-json/sikshya/v1/pro/learner/calendar?limit=50', {
      headers: { 'X-WP-Nonce': session.restNonce },
    });
    const minePayload = await mine.text();
    test.info().attach('learner-calendar', { body: minePayload.slice(0, 1200) });
    expect(mine.ok(), `GET /pro/learner/calendar status=${mine.status()}`).toBeTruthy();

    const mineBody = JSON.parse(minePayload) as {
      ok?: boolean;
      scope?: string;
      events?: Array<Record<string, unknown>>;
    };
    expect(mineBody.ok).toBe(true);
    expect(mineBody.scope).toBe('me');

    const serialised = JSON.stringify(mineBody.events ?? []);
    expect(
      serialised,
      'the learner calendar must surface the due assignment from their enrolled course',
    ).toContain(marker);

    // Staff feed must answer for an administrator.
    const staff = await request.get('/wp-json/sikshya/v1/pro/extended/calendar?limit=50', {
      headers: { 'X-WP-Nonce': nonce },
    });
    expect(staff.ok(), `GET /pro/extended/calendar status=${staff.status()}`).toBeTruthy();

    // A second learner, enrolled in nothing, must not see the first one's course.
    const otherName = slug('calother');
    await createUserViaRest(page, request, {
      username: otherName,
      email: `${otherName}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const other = await studentSession(browser, baseURL ?? 'http://sikshya.local', otherName, password);
    const theirs = await other.request.get('/wp-json/sikshya/v1/pro/learner/calendar?limit=50', {
      headers: { 'X-WP-Nonce': other.restNonce },
    });
    const theirsBody = await theirs.text();
    expect(
      theirsBody,
      'a learner calendar must never contain a course the learner is not enrolled in',
    ).not.toContain(marker);

    await other.context.close();
    await session.context.close();
  });
});
