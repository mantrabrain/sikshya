import { test, expect } from '@playwright/test';
import { createCourseViaRest, createLessonViaRest, getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(!routes.some((r) => /\/pro\//.test(r)), 'Sikshya Pro not active');
});

test.describe('addon: live_classes lesson meta round-trip', () => {
  test('admin can configure a lesson as a live session via wp/v2 meta + it persists', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/live_classes/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const course = await createCourseViaRest(page, request, {
      title: `E2E live course ${Date.now()}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    const meetingUrl = 'https://example.test/meeting/e2e';
    const startAt = '2027-01-01T15:00:00Z';
    const durationMinutes = 45;
    const provider = 'zoom';
    const sessionTitle = 'E2E Live session';

    const patch = await request.post(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        meta: {
          _sikshya_live_meeting_url: meetingUrl,
          _sikshya_live_start_at: startAt,
          _sikshya_live_duration_minutes: durationMinutes,
          _sikshya_live_provider: provider,
          _sikshya_live_session_title: sessionTitle,
        },
      },
    });
    expect(patch.status()).toBeLessThan(400);

    const read = await request.get(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const readBody = await read.json();
    const meta = readBody?.meta ?? {};
    expect(meta?._sikshya_live_meeting_url ?? '').toBe(meetingUrl);
    expect(meta?._sikshya_live_start_at ?? '').toBe(startAt);
    expect(Number(meta?._sikshya_live_duration_minutes ?? 0)).toBe(durationMinutes);
    expect(meta?._sikshya_live_provider ?? '').toBe(provider);
    expect(meta?._sikshya_live_session_title ?? '').toBe(sessionTitle);
  });
});
