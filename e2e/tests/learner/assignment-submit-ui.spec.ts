import { test, expect } from '@playwright/test';
import {
  createAssignmentViaRest,
  createCourseViaRest,
  createUserViaRest,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('learner: assignment submission UI', () => {
  test('enrolled student writes an essay response, submits, and sees the server accept it', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E assignment ${Date.now()}`,
      type: 'free',
    });
    const assignment = await createAssignmentViaRest(page, request, course.id, {
      type: 'essay',
      points: 5,
    });
    expect(assignment.link).toMatch(/https?:\/\//);

    const username = slug('stud');
    const password = 'StudPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });

    const session = await studentSession(
      browser,
      baseURL ?? 'http://sikshya.local',
      username,
      password,
    );

    const enrollRes = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    expect(enrollRes.status()).toBeLessThan(400);

    await session.page.goto(assignment.link, { waitUntil: 'domcontentloaded' });

    const form = session.page.locator('[data-sikshya-assignment-form]').first();
    await expect(form, 'assignment form should render for enrolled student').toBeVisible({
      timeout: 20_000,
    });

    const essay = session.page.locator('#sikshya-assignment-essay');
    await expect(essay).toBeVisible();
    await essay.fill('This is my E2E essay response demonstrating mastery of the topic.');

    // On successful submit the page JS calls window.location.reload(), which
    // means Playwright loses the network response body. Capture the response
    // body via page.route() so we can assert on the server's ok:true envelope.
    let restPayload = '';
    await session.page.route('**/me/assignment-submit*', async (route) => {
      const response = await route.fetch();
      restPayload = await response.text();
      await route.fulfill({ response });
    });

    const [submitResponse] = await Promise.all([
      session.page.waitForResponse(
        (r) => r.url().includes('/me/assignment-submit') && r.request().method() === 'POST',
        { timeout: 20_000 },
      ),
      session.page.locator('[data-sikshya-assignment-submit]').first().click(),
    ]);

    expect(submitResponse.status(), restPayload.slice(0, 200)).toBeLessThan(400);
    test.info().attach('assignment-submit-response', { body: restPayload.slice(0, 500) });

    let body: { ok?: boolean; data?: unknown } = {};
    try {
      body = JSON.parse(restPayload);
    } catch {
      /* attached */
    }
    expect(body?.ok, restPayload.slice(0, 200)).toBe(true);

    await session.context.close();
  });
});
