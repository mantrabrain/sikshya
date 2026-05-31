import { test, expect } from '@playwright/test';
import { createCourseViaRest, createLessonViaRest, getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: lesson meta roundtrip via wp/v2', () => {
  test('admin can set video URL, duration, and free-preview meta on a lesson', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    const course = await createCourseViaRest(page, request, {
      title: `E2E lesson-meta course ${Date.now()}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    const videoUrl = 'https://example.test/lesson-video.mp4';
    const duration = '12:34';

    const patch = await request.post(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        meta: {
          _sikshya_lesson_video_url: videoUrl,
          _sikshya_lesson_duration: duration,
          _sikshya_is_free: '1',
          _sikshya_lesson_type: 'video',
        },
      },
    });
    expect(patch.status()).toBeLessThan(400);

    const read = await request.get(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const readBody = await read.json();
    const meta = readBody?.meta ?? {};
    expect(String(meta._sikshya_lesson_video_url ?? '')).toBe(videoUrl);
    expect(String(meta._sikshya_lesson_duration ?? '')).toBe(duration);
    expect(String(meta._sikshya_is_free ?? '')).toBe('1');
    expect(String(meta._sikshya_lesson_type ?? '')).toBe('video');
  });
});
