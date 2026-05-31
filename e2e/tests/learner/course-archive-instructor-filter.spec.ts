import { test, expect } from '@playwright/test';
import { createCourseViaRest } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('public: course archive instructor filter', () => {
  test('?sikshya_instructor narrows the archive to the chosen author', async ({
    page,
    request,
  }) => {
    // Both courses are authored by the admin (id 1) — verifying the filter
    // narrows the result set is enough; we don't need a second author role.
    const courseTitle = `E2E inst-filter course ${Date.now()}`;
    const course = await createCourseViaRest(page, request, {
      title: courseTitle,
      type: 'free',
    });
    expect(course.id).toBeGreaterThan(0);

    // Sanity: with no instructor filter, the course appears.
    let res = await page.goto(`/?post_type=sik_course`, { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0).toBeLessThan(400);
    await expect(page.locator('body')).toContainText(courseTitle);

    // Narrow to admin (ID 1) — should still appear.
    res = await page.goto(`/?post_type=sik_course&sikshya_instructor=1`, {
      waitUntil: 'domcontentloaded',
    });
    expect(res?.status() ?? 0).toBeLessThan(400);
    await expect(page.locator('body')).toContainText(courseTitle);

    // Narrow to a definitely-non-existent author — course must not appear.
    res = await page.goto(`/?post_type=sik_course&sikshya_instructor=999999`, {
      waitUntil: 'domcontentloaded',
    });
    expect(res?.status() ?? 0).toBeLessThan(400);
    await expect(page.locator('body')).not.toContainText(courseTitle);
  });
});
