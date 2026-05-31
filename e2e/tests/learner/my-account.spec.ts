import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('learner: My Account / dashboard', () => {
  test('logged-in student sees their enrolled course on the My Learning page', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    // Seed: one free course.
    const course = await createCourseViaRest(page, request, {
      title: `E2E my-learning course ${Date.now()}`,
      type: 'free',
    });

    const username = slug('myacc');
    const password = 'MyAccPass!234';
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

    // Enroll first.
    const enrollRes = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    expect(enrollRes.status()).toBeLessThan(400);

    // Default permalink for the account page is 'my-learning'.
    const res = await session.page.goto('/my-learning/', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0, 'GET /my-learning/').toBeLessThan(400);

    // The dashboard renders an "Overview" heading + sidebar with "Learning",
    // "Profile & security", "My orders & payments" links — assert on the
    // sidebar marker which is stable across dashboard variations.
    await expect(
      session.page.getByRole('heading', { name: /Overview/i }).first(),
    ).toBeVisible({ timeout: 10_000 });

    // The enrolled course title should appear somewhere on the dashboard
    // (recent-enrollment widget / "Continue learning" tile).
    await expect(session.page.locator('body')).toContainText(course.title);

    await session.context.close();
  });
});
