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

test.describe('learner: assignment file_upload submission UI', () => {
  test('enrolled student uploads a file via the dropzone and the server accepts it', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E file-upload assignment ${Date.now()}`,
      type: 'free',
    });
    const assignment = await createAssignmentViaRest(page, request, course.id, {
      type: 'file_upload',
      points: 10,
    });

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
    await expect(form).toBeVisible({ timeout: 20_000 });

    // Dropzone wraps a real <input type="file"> with a CSS-hidden style; Playwright
    // can still drive it via setInputFiles regardless of visibility.
    const fileInput = session.page.locator('input[type="file"].sikshya-assignmentDropzone__native').first();
    await fileInput.setInputFiles({
      name: 'e2e-submission.txt',
      mimeType: 'text/plain',
      buffer: Buffer.from('Auto-generated E2E file upload contents.\n'),
    });

    // Intercept the POST so we can read the JSON body even after the page reloads.
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

    test.info().attach('assignment-file-upload-response', { body: restPayload.slice(0, 500) });
    expect(submitResponse.status(), restPayload.slice(0, 200)).toBeLessThan(400);

    let body: { ok?: boolean; data?: { attachments?: unknown[] } } = {};
    try {
      body = JSON.parse(restPayload);
    } catch {
      /* attached above */
    }
    expect(body?.ok, restPayload.slice(0, 200)).toBe(true);
    // The submission should carry the attached file in the response payload.
    expect(Array.isArray(body?.data?.attachments) && (body?.data?.attachments ?? []).length).toBeGreaterThan(0);

    await session.context.close();
  });
});
