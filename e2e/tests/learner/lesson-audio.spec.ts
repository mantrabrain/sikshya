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
 * Audio lessons, end to end.
 *
 * This type was two-thirds built: the learn shell already rendered an `audio`
 * lesson from `_sikshya_lesson_audio_url`, but the key was never registered for
 * REST, the type picker offered no such option, and the server allowlist turned
 * an audio lesson into a text one. So the renderer worked and nothing could
 * reach it.
 *
 * The test therefore covers the part that was missing rather than the part that
 * already worked: the type must survive a save, the URL must persist, and the
 * learner must get a player.
 */
test.describe('learner: audio lessons', () => {
  test('an audio lesson can be authored and plays for an enrolled learner', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const stamp = Date.now();
    const audioUrl = `https://example.test/lesson-${stamp}.mp3`;

    const course = await createCourseViaRest(page, request, {
      title: `E2E audio course ${stamp}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    // Both writes go through registered post meta, exactly as the editor does.
    const save = await request.post(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        meta: {
          _sikshya_lesson_type: 'audio',
          _sikshya_lesson_audio_url: audioUrl,
        },
      },
    });
    const saveText = await save.text();
    test.info().attach('lesson-save', { body: saveText.slice(0, 600) });
    expect(save.status(), `saving the audio lesson failed: ${saveText.slice(0, 250)}`).toBeLessThan(400);

    // The type must survive. Before this fix the allowlist rewrote it to `text`,
    // and the audio URL had no registered meta key to land in at all.
    const saved = JSON.parse(saveText) as { meta?: Record<string, unknown> };
    expect(
      String(saved.meta?._sikshya_lesson_type ?? ''),
      'the lesson must stay an audio lesson rather than falling back to text',
    ).toBe('audio');
    expect(
      String(saved.meta?._sikshya_lesson_audio_url ?? ''),
      'the audio URL must persist; the meta key has to be REST-registered',
    ).toBe(audioUrl);

    // Read it back on its own, so this cannot pass on the write response alone.
    const reread = await request.get(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const rereadBody = (await reread.json().catch(() => ({}))) as { meta?: Record<string, unknown> };
    expect(
      String(rereadBody.meta?._sikshya_lesson_audio_url ?? ''),
      'the audio URL must still be there on a fresh read',
    ).toBe(audioUrl);

    // And the learner must actually get a player.
    const username = slug('audiostud');
    const password = 'AudioPass!2026';
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
      const html = await session.page.content();
      test.info().attach('lesson-page', { body: html.slice(0, 2500) });

      expect(
        /<audio[^>]*>/i.test(html) || html.includes(audioUrl),
        'an audio lesson must render a player or at least its source for the learner',
      ).toBeTruthy();
    } finally {
      await session.context.close();
    }
  });
});
