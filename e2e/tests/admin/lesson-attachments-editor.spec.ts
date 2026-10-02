import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  getAdminNonce,
  slug,
} from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The authoring half of lesson attachments.
 *
 * The learner-facing path is covered in `learner/lesson-attachments.spec.ts`,
 * which writes the meta over REST. That would keep passing if the editor field
 * were missing, which is exactly the state the feature was in before — storage
 * and rendering with no way for an author to reach it. This asserts the control
 * exists, and that a value saved through the editor's own meta contract loads
 * back into it.
 */
test.describe('admin: lesson attachments editor field', () => {
  test('the lesson editor exposes an Attachments control that round-trips a saved file', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    const stamp = Date.now();

    const course = await createCourseViaRest(page, request, {
      title: `E2E att editor ${stamp}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    const upload = await request.post('/wp-json/wp/v2/media', {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: `editor-${stamp}.txt`,
          mimeType: 'text/plain',
          buffer: Buffer.from(`editor attachment ${stamp}`),
        },
        title: `Editor handout ${stamp}`,
      },
    });
    expect(upload.status(), (await upload.text()).slice(0, 200)).toBeLessThan(400);
    const mediaId = Number(((await upload.json()) as { id?: number }).id ?? 0);
    expect(mediaId).toBeGreaterThan(0);

    const editorUrl = `/wp-admin/admin.php?page=sikshya&view=edit-content&post_type=sik_lesson&post_id=${lesson.id}`;

    // 1. The control must be present on a lesson with no attachments yet.
    await page.goto(editorUrl, { waitUntil: 'domcontentloaded' });
    const field = page.locator('#sik-lesson-attachments');
    await expect(
      field,
      'the lesson editor must offer an Attachments control',
    ).toBeVisible({ timeout: 30_000 });
    await expect(
      page.getByText('Attachments', { exact: true }).first(),
      'the control must be labelled',
    ).toBeVisible();
    await expect(
      page.locator('[data-sikshya-lesson-attachments] li'),
      'a lesson with no attachments must list none',
    ).toHaveCount(0);

    // 2. Attach the file the way the editor does, then reload: the editor must
    //    read its own stored value back, by name rather than by raw id.
    const attach = await request.post(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { meta: { _sikshya_lesson_attachments: [mediaId] } },
    });
    expect(attach.status(), (await attach.text()).slice(0, 200)).toBeLessThan(400);

    await page.goto(editorUrl, { waitUntil: 'domcontentloaded' });
    const rows = page.locator('[data-sikshya-lesson-attachments] li');
    await expect(rows, 'the saved attachment must load into the editor').toHaveCount(1, {
      timeout: 30_000,
    });
    await expect(
      rows.first(),
      'the attachment must be shown by its media library title, not its id',
    ).toContainText(`Editor handout ${stamp}`, { timeout: 30_000 });

    // 3. Removing it must be possible without leaving the page.
    await rows.first().getByRole('button', { name: 'Remove' }).click();
    await expect(
      page.locator('[data-sikshya-lesson-attachments] li'),
      'removing an attachment must drop it from the list',
    ).toHaveCount(0);
  });
});
