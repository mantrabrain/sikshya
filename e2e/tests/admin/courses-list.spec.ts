import { test, expect } from '@playwright/test';

test.describe('admin: courses post type', () => {
  test('sik_course edit.php list screen loads', async ({ page }) => {
    const response = await page.goto('/wp-admin/edit.php?post_type=sik_course');
    expect(response?.status()).toBeLessThan(400);
    await expect(page.locator('#wpadminbar')).toBeVisible();
    await expect(page.locator('body')).toContainText(/Course/i);
  });

  test('sik_course new post screen loads the editor', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php?post_type=sik_course');
    const editorChrome = page
      .getByRole('heading', { name: /Add New Course/i })
      .or(page.getByRole('region', { name: /Editor top bar/i }))
      .or(page.locator('input#title'))
      .first();
    await expect(editorChrome).toBeVisible({ timeout: 30_000 });
  });
});
