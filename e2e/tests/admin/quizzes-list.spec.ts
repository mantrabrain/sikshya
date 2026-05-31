import { test, expect } from '@playwright/test';

test.describe('admin: quizzes post type', () => {
  test('sik_quiz list screen loads', async ({ page }) => {
    const response = await page.goto('/wp-admin/edit.php?post_type=sik_quiz');
    expect(response?.status()).toBeLessThan(400);
    await expect(page.locator('#wpadminbar')).toBeVisible();
  });
});
