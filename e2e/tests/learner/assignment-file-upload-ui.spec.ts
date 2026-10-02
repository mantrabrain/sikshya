import { test, expect } from '@playwright/test';
import {
  learnUrl,
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

    await session.page.goto(learnUrl('assignment', assignment.slug), { waitUntil: 'domcontentloaded' });

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

    // Wait for the submit POST, then verify the outcome against the server
    // rather than the response body.
    //
    // Reading the body is not portable here. The original version routed the
    // request and called `route.fetch()` to replay it, but that rebuilds the
    // body from `request.postData()`, which is lossy for a multipart upload: in
    // WebKit the file was dropped and the server answered `ok: true` with zero
    // attachments. Reading `response.text()` instead fails on Chromium, which
    // discards the body once the page navigates away. Asking the API what was
    // actually stored is engine-independent, and a better assertion anyway.
    // Wait for the control to be ready before clicking it. A click dispatched
    // while the form is still wiring itself up is silently dropped, and the
    // failure then surfaces 20 seconds later as "no POST arrived", which reads
    // like a server problem rather than a too-early click. Seen once under the
    // load of a full-suite run.
    const submitButton = session.page.locator('[data-sikshya-assignment-submit]').first();
    await expect(submitButton).toBeVisible({ timeout: 20_000 });
    await expect(submitButton).toBeEnabled({ timeout: 20_000 });

    const [submitResponse] = await Promise.all([
      session.page.waitForResponse(
        (r) => r.url().includes('/me/assignment-submit') && r.request().method() === 'POST',
        { timeout: 45_000 },
      ),
      submitButton.click(),
    ]);

    expect(submitResponse.status(), 'POST /me/assignment-submit').toBeLessThan(400);

    const listed = await session.request.get(
      `/wp-json/sikshya/v1/me/assignments?course_id=${course.id}`,
      { headers: { 'X-WP-Nonce': session.restNonce } },
    );
    const listPayload = await listed.text();
    test.info().attach('me-assignments-response', { body: listPayload.slice(0, 800) });
    expect(listed.ok(), `GET /me/assignments status=${listed.status()}`).toBeTruthy();

    let listBody: { data?: { assignments?: Array<Record<string, unknown>> } } = {};
    try {
      listBody = JSON.parse(listPayload);
    } catch {
      /* attached above */
    }

    const row = (listBody?.data?.assignments ?? []).find(
      (a) => Number((a as { id?: number }).id ?? (a as { assignment_id?: number }).assignment_id) === assignment.id,
    );
    expect(row, `assignment ${assignment.id} missing from /me/assignments`).toBeTruthy();

    const submission = (row as { submission?: { attachments?: unknown[] } })?.submission ?? {};
    const attachments = Array.isArray(submission.attachments) ? submission.attachments : [];
    expect(
      attachments.length,
      `the stored submission must carry the uploaded file; row=${JSON.stringify(row).slice(0, 300)}`,
    ).toBeGreaterThan(0);

    await session.context.close();
  });
});
