import { test, expect } from '@playwright/test';

test.describe('admin: course block editor', () => {
  test('the editor loads with sidebar metaboxes and breadcrumb shows Course', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php?post_type=sik_course');
    await expect(page.getByRole('heading', { name: /Add New Course/i })).toBeVisible({
      timeout: 30_000,
    });
    await expect(
      page.getByRole('tab', { name: /Course/i }).or(page.locator('button:has-text("Document")')),
    ).toBeVisible();
    await expect(page.getByRole('listitem').filter({ hasText: /^Course$/ }).first()).toBeVisible();
  });
});
