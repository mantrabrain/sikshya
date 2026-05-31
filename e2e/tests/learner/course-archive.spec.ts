import { test, expect } from '@playwright/test';

test.describe('learner: course archive', () => {
  test('course archive URL is reachable', async ({ page }) => {
    const candidates = ['/courses/', '/course/', '/?post_type=sik_course'];
    let lastStatus = 0;
    for (const path of candidates) {
      const res = await page.goto(path);
      lastStatus = res?.status() ?? 0;
      if (lastStatus < 400) break;
    }
    expect(lastStatus, 'no archive candidate returned <400').toBeLessThan(400);
    await expect(page.locator('body')).toBeVisible();
  });
});
