import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('learner: account achievements panel', () => {
  test('first enrollment earns the "First enrollment" badge and surfaces it on /my-learning/', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E achievement course ${Date.now()}`,
      type: 'free',
    });

    const username = slug('ach');
    const password = 'AchPass!234';
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

    const enroll = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    expect(enroll.status()).toBeLessThan(400);

    const res = await session.page.goto('/my-learning/', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0).toBeLessThan(400);

    // The achievements panel should show the "First enrollment" badge.
    await expect(session.page.locator('.sik-acc-dash-badges')).toBeVisible({ timeout: 10_000 });
    await expect(session.page.locator('.sik-acc-dash-badge__name')).toContainText(/first enrollment/i);

    await session.context.close();
  });
});
