import { test, expect } from '@playwright/test';

test.describe('admin: setup wizard', () => {
  test('?page=sikshya-setup renders without server error', async ({ page }) => {
    const response = await page.goto('/wp-admin/admin.php?page=sikshya-setup');
    expect(response?.status()).toBeLessThan(500);
    await expect(page.locator('body')).toBeVisible();
  });
});
