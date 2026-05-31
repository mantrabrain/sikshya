import { test, expect } from '@playwright/test';

test.describe('smoke: admin login', () => {
  test('admin storage state loads wp-admin without re-login', async ({ page }) => {
    await page.goto('/wp-admin/');
    await expect(page).toHaveURL(/wp-admin/);
    await expect(page.locator('#wpadminbar')).toBeVisible();
    await expect(page.locator('#wpadminbar #wp-admin-bar-my-account')).toContainText(/admin/i);
  });
});
