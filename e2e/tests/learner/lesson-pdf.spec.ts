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

/**
 * PDF lessons, end to end.
 *
 * The part no PHPUnit test can reach is the bit this exists for: the proxy's
 * `stream()` emits binary and calls `exit`, so the only way to find out what it
 * actually puts on the wire is to ask it over HTTP. That is also where the
 * feature's security boundary lives — inline delivery is allowed for a PDF and
 * must stay refused for anything the browser would parse as markup — and where
 * byte ranges either work or silently return the wrong bytes.
 */
test.describe('learner: PDF lessons', () => {
  test('a PDF lesson is framed from a signed URL that serves inline and honours ranges', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const stamp = Date.now();

    /*
     * A 300-byte file whose content is known byte by byte, because a wrong seek
     * returns a plausible-looking body and only the exact bytes catch it.
     *
     * It has to begin `%PDF-` for real: `wp/v2/media` runs the upload through
     * `wp_check_filetype_and_ext()`, which asks libmagic what the bytes
     * actually are and rejects a `.pdf` that is not one ("you are not allowed
     * to upload this file type"). Writing the attachment row directly would
     * dodge that check — and dodge the path every author uses.
     */
    const header = '%PDF-1.4\n';
    const pdfBytes = Buffer.concat([
      Buffer.from(header, 'latin1'),
      Buffer.from(
        Array.from({ length: 300 - header.length }, (_, i) => String.fromCharCode(65 + (i % 26))).join(''),
        'latin1',
      ),
    ]);
    expect(pdfBytes.length).toBe(300);

    const upload = await request.post('/wp-json/wp/v2/media', {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: `handbook-${stamp}.pdf`,
          mimeType: 'application/pdf',
          buffer: pdfBytes,
        },
        title: `E2E handbook ${stamp}`,
      },
    });
    expect(upload.status(), (await upload.text()).slice(0, 200)).toBeLessThan(400);
    const mediaId = Number(((await upload.json()) as { id?: number }).id ?? 0);
    expect(mediaId).toBeGreaterThan(0);

    const course = await createCourseViaRest(page, request, {
      title: `E2E pdf course ${stamp}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    const save = await request.post(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        meta: {
          _sikshya_lesson_type: 'pdf',
          _sikshya_lesson_pdf_id: mediaId,
        },
      },
    });
    const saveText = await save.text();
    expect(save.status(), `saving the PDF lesson failed: ${saveText.slice(0, 250)}`).toBeLessThan(400);
    const saved = JSON.parse(saveText) as { meta?: Record<string, unknown> };
    expect(String(saved.meta?._sikshya_lesson_type ?? '')).toBe('pdf');
    expect(Number(saved.meta?._sikshya_lesson_pdf_id ?? 0)).toBe(mediaId);

    const username = slug('pdfstud');
    const password = 'PdfPass!2026';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);
    try {
      const enroll = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
        headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
        data: { course_id: course.id },
      });
      expect(enroll.status(), 'precondition: the learner must enrol').toBeLessThan(400);

      await session.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });

      const frame = session.page.locator('iframe.sikshya-lesson-pdf__frame');
      await expect(frame, 'a PDF lesson must render a viewer').toBeVisible({ timeout: 30_000 });

      const src = String(await frame.getAttribute('src'));
      // The raw uploads path must never reach the page: that URL works for
      // anyone who has it, enrolled or not.
      expect(src, 'the viewer must point at the signed proxy').toContain('/sikshya/v1/file/');
      expect(src, 'the raw uploads path must not reach the page').not.toContain('/uploads/');

      // The frame is never the only way in — embedded PDF support is uneven and
      // fails silently, so the link row is permanent rather than an error state.
      await expect(
        session.page.getByRole('link', { name: /Open the document in a new tab/i }),
      ).toBeVisible();

      // Now the part PHPUnit cannot see: what the proxy actually puts on the
      // wire. Fetched from the learner's own session, because the token is
      // bound to their user id.
      const whole = await session.request.get(src);
      expect(whole.status(), 'the signed URL must serve the learner it was issued to').toBe(200);
      expect(whole.headers()['content-type']).toContain('application/pdf');
      expect(
        whole.headers()['content-disposition'],
        'a PDF lesson needs inline delivery or the viewer downloads instead of rendering',
      ).toContain('inline');
      expect(whole.headers()['accept-ranges']).toBe('bytes');
      expect((await whole.body()).length).toBe(300);

      const ranged = await session.request.get(src, { headers: { Range: 'bytes=10-19' } });
      expect(ranged.status(), 'PDF viewers fetch a document a range at a time').toBe(206);
      expect(ranged.headers()['content-range']).toBe('bytes 10-19/300');
      expect(
        (await ranged.body()).toString('latin1'),
        'a 206 carrying the wrong bytes is a corrupt download',
      ).toBe(pdfBytes.subarray(10, 20).toString('latin1'));

      const suffix = await session.request.get(src, { headers: { Range: 'bytes=-20' } });
      expect(suffix.status()).toBe(206);
      expect(suffix.headers()['content-range']).toBe('bytes 280-299/300');

      const past = await session.request.get(src, { headers: { Range: 'bytes=99999-' } });
      expect(past.status(), 'an unsatisfiable range gets 416 plus the real length').toBe(416);
      expect(past.headers()['content-range']).toBe('bytes */300');
    } finally {
      await session.context.close();
    }
  });

  /**
   * The outer half of the security boundary: an `.html` upload never becomes a
   * document in the first place, so there is no frame and no signed inline URL
   * minted for it. (The inner half — that even a validly signed inline token
   * for `text/html` still serves `Content-Disposition: attachment` — is pinned
   * in `--group pdf_lessons`, which can mint such a token directly.)
   */
  test('an HTML upload never becomes a PDF lesson document', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const stamp = Date.now();

    const upload = await request.post('/wp-json/wp/v2/media', {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: {
          name: `payload-${stamp}.html`,
          mimeType: 'text/html',
          buffer: Buffer.from('<script>window.__sikshyaXss = 1;</script>'),
        },
        title: `E2E payload ${stamp}`,
      },
    });
    expect(upload.status(), (await upload.text()).slice(0, 200)).toBeLessThan(400);
    const mediaId = Number(((await upload.json()) as { id?: number }).id ?? 0);

    const course = await createCourseViaRest(page, request, {
      title: `E2E xss course ${stamp}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);
    await request.post(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        meta: { _sikshya_lesson_type: 'pdf', _sikshya_lesson_pdf_id: mediaId },
      },
    });

    // An `.html` attachment is not a PDF, so the lesson must resolve to no
    // document at all — there is no frame to point anywhere.
    await page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('iframe.sikshya-lesson-pdf__frame')).toHaveCount(0);
    expect(await page.content()).not.toContain('__sikshyaXss');
  });
});
