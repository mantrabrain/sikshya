import { test, expect } from '@playwright/test';
import { createCourseViaRest } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: TopBar global search palette', () => {
  test('clicking the TopBar search button opens the palette and searches a course', async ({
    page,
    request,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E palette course ${Date.now()}`,
      type: 'free',
    });

    // Mount the React admin (TopBar lives inside the React shell).
    const res = await page.goto('/wp-admin/admin.php?page=sikshya', { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0).toBeLessThan(400);

    // Trigger the palette.
    await page.locator('[data-testid="topbar-global-search-trigger"]').first().click();
    await expect(page.locator('[data-testid="topbar-global-search-panel"]')).toBeVisible();

    // Type a query token that's in the seeded course title.
    await page.locator('[data-testid="topbar-global-search-input"]').fill('palette course');

    // The course title should appear in the Courses bucket.
    await expect(page.locator('[data-testid="topbar-global-search-panel"]')).toContainText(course.title, {
      timeout: 10_000,
    });
  });
});
