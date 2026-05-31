import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  createQuizViaRest,
  getAdminNonce,
  slug,
} from '../../utils/factories';

test.describe('admin: edit/update flows', () => {
  test('admin can rename a course via wp/v2/sik_course PATCH and the change persists', async ({
    page,
    request,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E rename src ${Date.now()}`,
      type: 'free',
    });
    const nonce = await getAdminNonce(page);

    const newTitle = `E2E renamed course ${slug('rn')}`;
    const patch = await request.post(`/wp-json/wp/v2/sik_course/${course.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { title: newTitle },
    });
    expect(patch.status()).toBeLessThan(400);

    const read = await request.get(`/wp-json/wp/v2/sik_course/${course.id}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const readBody = await read.json();
    expect(readBody?.title?.rendered ?? readBody?.title?.raw ?? '').toContain('renamed');
  });

  test('admin can update lesson content via wp/v2/sik_lesson PATCH', async ({ page, request }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E edit-lesson course ${Date.now()}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);
    const nonce = await getAdminNonce(page);

    const newContent = `<p>Updated lesson body ${slug('ed')}</p>`;
    const patch = await request.post(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { content: newContent },
    });
    expect(patch.status()).toBeLessThan(400);

    const read = await request.get(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const readBody = await read.json();
    const renderedHtml = String(readBody?.content?.rendered ?? readBody?.content?.raw ?? '');
    expect(renderedHtml).toContain('Updated lesson body');
  });

  test('admin can change quiz passing_score meta via wp/v2/sik_quiz PATCH', async ({
    page,
    request,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E edit-quiz course ${Date.now()}`,
      type: 'free',
    });
    const quiz = await createQuizViaRest(page, request, course.id);
    const nonce = await getAdminNonce(page);

    const patch = await request.post(`/wp-json/wp/v2/sik_quiz/${quiz.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { meta: { _sikshya_quiz_passing_score: 80 } },
    });
    expect(patch.status()).toBeLessThan(400);

    const read = await request.get(`/wp-json/wp/v2/sik_quiz/${quiz.id}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const readBody = await read.json();
    expect(Number(readBody?.meta?._sikshya_quiz_passing_score ?? 0)).toBe(80);
  });

  test('admin can update course title meta + verify in edit.php listing', async ({
    page,
    request,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E listing pre ${Date.now()}`,
      type: 'free',
    });
    const newTitle = `E2E listing post ${slug('lpx')}`;
    const nonce = await getAdminNonce(page);
    await request.post(`/wp-json/wp/v2/sik_course/${course.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { title: newTitle },
    });

    await page.goto('/wp-admin/edit.php?post_type=sik_course&s=' + encodeURIComponent(newTitle));
    await expect(page.locator('body')).toContainText(newTitle);
  });
});
