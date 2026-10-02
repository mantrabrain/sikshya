import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  createUserViaRest,
  getAdminNonce,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

interface Snapshot {
  published_courses: number;
  total_enrollments: number;
  distinct_learners: number;
  completed_enrollments: number;
  completion_rate: number;
}

async function readSnapshot(request: import('@playwright/test').APIRequestContext, nonce: string) {
  const res = await request.get('/wp-json/sikshya/v1/admin/reports-snapshot', {
    headers: { 'X-WP-Nonce': nonce },
  });
  const text = await res.text();
  expect(res.ok(), `GET /admin/reports-snapshot status=${res.status()}: ${text.slice(0, 200)}`).toBeTruthy();
  const body = JSON.parse(text) as { stats?: Snapshot; chart?: unknown[] };
  expect(body.stats, 'the snapshot must carry a stats object').toBeTruthy();
  return body;
}

/**
 * `basic_reports` is a free feature and had no coverage.
 *
 * Asserting the endpoint merely answers 200 would be close to worthless — a
 * hard-coded or stale snapshot passes that. What matters to someone running a
 * course business is that the numbers move with reality, so this takes a reading,
 * changes the underlying data, and requires the reading to follow.
 */
test.describe('admin: reports snapshot', () => {
  test('enrolment and completion counts follow real activity', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const before = (await readSnapshot(request, nonce)).stats as Snapshot;

    const course = await createCourseViaRest(page, request, {
      title: `E2E report ${Date.now()}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    const username = slug('repstud');
    const password = 'RepPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);

    await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });

    const afterEnrol = (await readSnapshot(request, nonce)).stats as Snapshot;
    expect(
      afterEnrol.total_enrollments,
      'a new enrolment must raise total_enrollments, or the report is stale',
    ).toBe(before.total_enrollments + 1);
    expect(
      afterEnrol.published_courses,
      'the new published course must be counted',
    ).toBeGreaterThan(before.published_courses);
    expect(
      afterEnrol.distinct_learners,
      'a first-time learner must raise distinct_learners',
    ).toBe(before.distinct_learners + 1);

    // Completing the only lesson completes the course.
    const complete = await session.request.post('/wp-json/sikshya/v1/me/lesson-complete', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, lesson_id: lesson.id },
    });
    expect(complete.ok(), `completion failed: ${(await complete.text()).slice(0, 200)}`).toBeTruthy();

    const afterComplete = (await readSnapshot(request, nonce)).stats as Snapshot;
    expect(
      afterComplete.completed_enrollments,
      'finishing a course must raise completed_enrollments',
    ).toBe(afterEnrol.completed_enrollments + 1);
    expect(
      afterComplete.completion_rate,
      'completion_rate must stay a sane percentage',
    ).toBeGreaterThanOrEqual(0);
    expect(afterComplete.completion_rate).toBeLessThanOrEqual(100);

    await session.context.close();

    // And the admin screen must actually render a figure, not an error state.
    await page.goto('/wp-admin/admin.php?page=sikshya&view=reports', {
      waitUntil: 'domcontentloaded',
    });
    await expect(
      page.locator('text=/enrol|enroll/i').first(),
      'the Reports view must render its enrolment panel',
    ).toBeVisible({ timeout: 20_000 });
    const screen = await page.locator('body').innerText();
    test.info().attach('reports-screen', { body: screen.slice(0, 1500) });
    expect(
      screen.toLowerCase(),
      'the Reports view must not render a failure state',
    ).not.toMatch(/something went wrong|failed to load|error loading/);
  });
});
