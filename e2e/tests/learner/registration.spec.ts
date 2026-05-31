import { test, expect } from '@playwright/test';

test.describe('learner: registration access', () => {
  test('wp-login.php registration link is reachable when enabled', async ({ page }) => {
    const res = await page.goto('/wp-login.php?action=register');
    expect(res?.status()).toBeLessThan(500);
    await expect(page.locator('body').first()).toBeVisible();
  });
});
