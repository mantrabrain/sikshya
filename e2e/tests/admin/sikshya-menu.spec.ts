import { test, expect } from '@playwright/test';

test.describe('admin: Sikshya menu', () => {
  test('Sikshya top-level menu is present in WP admin sidebar', async ({ page }) => {
    await page.goto('/wp-admin/');
    const sikshyaMenu = page.locator('#adminmenu a[href*="page=sikshya"]').first();
    await expect(sikshyaMenu).toBeVisible();
  });

  test('clicking Sikshya menu navigates to React app', async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.locator('#adminmenu a[href*="page=sikshya"]').first().click();
    await expect(page).toHaveURL(/page=sikshya/);
  });
});
