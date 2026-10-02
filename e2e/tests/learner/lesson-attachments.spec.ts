import { readFileSync } from 'node:fs';
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
 * `lesson_attachments` is advertised as a free feature and now has a delivery
 * path: a media file attached to a lesson appears under Downloads on that
 * lesson, above the course-wide resources.
 *
 * This asserts the whole chain in a browser — upload, attach, enrol, render,
 * download — because each link had been broken independently: the meta was
 * written under one key and read under another, no authoring surface wrote it,
 * and no template rendered it.
 */
test.describe('learner: lesson attachments', () => {
  test('a file attached to a lesson is listed and downloadable by an enrolled learner', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const stamp = Date.now();
    const fileName = `handout-${stamp}.txt`;
    const fileBody = `Sikshya lesson handout ${stamp}`;

    // 1. Upload a real file to the media library.
    const upload = await request.post('/wp-json/wp/v2/media', {
      headers: { 'X-WP-Nonce': nonce },
      multipart: {
        file: { name: fileName, mimeType: 'text/plain', buffer: Buffer.from(fileBody) },
        title: `Lesson handout ${stamp}`,
      },
    });
    const uploadText = await upload.text();
    expect(upload.status(), `media upload failed: ${uploadText.slice(0, 300)}`).toBeLessThan(400);
    const media = JSON.parse(uploadText) as { id?: number; source_url?: string };
    const mediaId = Number(media.id ?? 0);
    const rawUrl = String(media.source_url ?? '');
    expect(mediaId, 'the upload must return an attachment id').toBeGreaterThan(0);

    const course = await createCourseViaRest(page, request, {
      title: `E2E attachments ${stamp}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    // 2. Attach it to the lesson the way the lesson editor does — registered
    //    post meta over wp/v2.
    const attach = await request.post(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { meta: { _sikshya_lesson_attachments: [mediaId] } },
    });
    const attachText = await attach.text();
    expect(
      attach.status(),
      `attaching the file to the lesson failed: ${attachText.slice(0, 300)}`,
    ).toBeLessThan(400);

    // Precondition: the meta must have survived the write, otherwise a missing
    // Downloads entry below would be ambiguous.
    expect(
      JSON.stringify((JSON.parse(attachText) as { meta?: unknown }).meta ?? {}),
      'the attachment id must be persisted on the lesson',
    ).toContain(String(mediaId));

    // 3. Enrol a learner.
    const username = slug('attstud');
    const password = 'AttachPass!234';
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

    try {
      const enroll = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
        headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
        data: { course_id: course.id },
      });
      expect(
        enroll.status(),
        `precondition: the learner must enrol: ${(await enroll.text()).slice(0, 200)}`,
      ).toBeLessThan(400);

      // 4. The lesson page must list it under Downloads.
      await session.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
      const html = await session.page.content();
      test.info().attach('lesson-page', { body: html.slice(0, 4000) });

      expect(
        html,
        'a lesson attachment must be listed to an enrolled learner',
      ).toContain(`Lesson handout ${stamp}`);
      expect(html, 'the lesson group heading must be rendered').toContain('For this lesson');

      // The empty-state copy must be gone, otherwise the panel is contradicting
      // itself while listing a file.
      expect(
        html,
        'the Downloads panel must not claim there are no resources while listing one',
      ).not.toContain('No resources available for this lesson yet.');

      // 5. The link must be a protected URL, not the bare uploads path — the
      //    same guarantee course resources get.
      const link = session.page.locator(
        `.sikshya-resList--lesson a[href]:has-text("Lesson handout ${stamp}")`,
      );
      await expect(link).toHaveCount(1);
      const href = String(await link.getAttribute('href'));
      expect(href, 'a download URL must be present').not.toBe('');
      if (rawUrl !== '') {
        expect(
          href,
          'the raw uploads URL must not be handed to the learner; it should be a signed URL',
        ).not.toBe(rawUrl);
      }

      // 6. And it must actually serve the file to that learner.
      const download = await session.request.get(href);
      expect(
        download.status(),
        `the learner could not download their lesson attachment: ${download.status()}`,
      ).toBeLessThan(400);
      expect(
        await download.text(),
        'the download must return the uploaded file contents',
      ).toContain(fileBody);

      // 7. Clicking the link in the page must work too, not just fetching the
      //    URL. A click is a top-level navigation that carries cookies but no
      //    REST nonce, which is the case that used to be refused outright.
      //    The list lives in the Resources tab panel, hidden until selected.
      await session.page.locator('#sikshya-tab-resources').click();
      await expect(link).toBeVisible();
      const [downloadEvent] = await Promise.all([
        session.page.waitForEvent('download', { timeout: 20_000 }),
        link.click(),
      ]);
      expect(
        await downloadEvent.failure(),
        'clicking the download link must not fail for the learner it was issued to',
      ).toBeNull();
      const savedPath = await downloadEvent.path();
      expect(savedPath, 'the clicked link must deliver a file').toBeTruthy();
      expect(
        readFileSync(String(savedPath), 'utf8'),
        'the clicked download must contain the uploaded file',
      ).toContain(fileBody);

      // 8. The protection must still hold. The token names one account, so a
      //    different logged-in learner presenting the same URL gets nothing,
      //    and neither does a logged-out visitor.
      const other = slug('attother');
      await createUserViaRest(page, request, {
        username: other,
        email: `${other}@example.com`,
        password,
        role: STUDENT_ROLE,
      });
      const otherSession = await studentSession(
        browser,
        baseURL ?? 'http://sikshya.local',
        other,
        password,
      );
      try {
        const stolen = await otherSession.request.get(href);
        expect(
          stolen.status(),
          'a download link issued to one learner must not work for another',
        ).toBeGreaterThanOrEqual(400);
        expect(
          await stolen.text(),
          'another learner must not receive the file contents',
        ).not.toContain(fileBody);

        const stolenNav = await otherSession.page.goto(href, { waitUntil: 'domcontentloaded' });
        expect(
          stolenNav?.status() ?? 0,
          'the same must hold for a browser navigation, which is how a shared link is opened',
        ).toBeGreaterThanOrEqual(400);
      } finally {
        await otherSession.context.close();
      }

      // A genuinely cookie-less context: the ambient `request` fixture carries
      // the admin session, so clearing a header would not make it anonymous.
      const guestContext = await browser.newContext({
        baseURL: baseURL ?? 'http://sikshya.local',
        ignoreHTTPSErrors: true,
      });
      try {
        const anonymous = await guestContext.request.get(href);
        expect(
          anonymous.status(),
          'a logged-out visitor with the URL must be refused',
        ).toBeGreaterThanOrEqual(400);
        expect(
          await anonymous.text(),
          'a logged-out visitor must not receive the file contents',
        ).not.toContain(fileBody);
      } finally {
        await guestContext.close();
      }
    } finally {
      await session.context.close();
    }
  });
});
