import { test, expect } from '@playwright/test';

test.describe('admin: lessons post type', () => {
  test('sik_lesson list screen loads', async ({ page }) => {
    const response = await page.goto('/wp-admin/edit.php?post_type=sik_lesson');
    expect(response?.status()).toBeLessThan(400);
    await expect(page.locator('#wpadminbar')).toBeVisible();
  });
});
