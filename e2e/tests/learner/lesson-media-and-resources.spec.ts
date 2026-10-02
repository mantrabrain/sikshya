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
 * Two free features that had no coverage: video lessons (`lesson_video_text`) and
 * downloadable materials.
 *
 * A note on the second one. This spec covers *course-level*
 * `_sikshya_course_resources`, rendered in the Resources tab of the lesson and
 * quiz screens. Per-lesson attachments are a separate feature with their own
 * coverage in `lesson-attachments.spec.ts`; the two render in the same panel,
 * lesson files first.
 */
test.describe('learner: lesson video and course resources', () => {
  test('a video lesson plays and course resources are listed to an enrolled learner', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);

    const stamp = Date.now();
    const resourceTitle = `RES-${stamp}`;
    const videoUrl = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    const course = await createCourseViaRest(page, request, {
      title: `E2E media ${stamp}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    // Downloadable resources live on the course, authored in the builder.
    const save = await request.post('/wp-json/sikshya/v1/course-builder/save', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        course_id: course.id,
        title: `E2E media ${stamp}`,
        description: 'Course with resources.',
        course_status: 'publish',
        course_resources: [{ title: resourceTitle, url: 'https://example.test/handout.pdf' }],
      },
    });
    expect(save.status(), (await save.text()).slice(0, 250)).toBeLessThan(400);

    // The video URL is a registered lesson meta, so it goes through wp/v2.
    const setVideo = await request.post(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { meta: { _sikshya_lesson_video_url: videoUrl } },
    });
    expect(setVideo.status(), 'the lesson video URL must be writable').toBeLessThan(400);

    const username = slug('mediastud');
    const password = 'MediaPass!234';
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

    await session.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
    const html = await session.page.content();
    test.info().attach('lesson-page', { body: html.slice(0, 3000) });

    // The player must be embedded — a bare link would mean the video never rendered.
    expect(
      /<iframe[^>]+(youtube|youtu\.be)|<video[^>]*>/i.test(html),
      'a lesson with a video URL must render an embedded player',
    ).toBeTruthy();

    // Resources are in a tab panel, hidden until selected, so assert the markup.
    expect(
      html,
      'course resources must be listed to an enrolled learner on the lesson screen',
    ).toContain(resourceTitle);

    await session.context.close();
  });
});
