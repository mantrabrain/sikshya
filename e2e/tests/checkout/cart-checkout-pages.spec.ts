import { test, expect } from '@playwright/test';

test.describe('checkout: cart and checkout pages', () => {
  test('cart page reachable', async ({ page }) => {
    const res = await page.goto('/cart/');
    expect(res?.status()).toBeLessThan(500);
    await expect(page.locator('body')).toBeVisible();
  });

  test('checkout page reachable', async ({ page }) => {
    const res = await page.goto('/checkout/');
    expect(res?.status()).toBeLessThan(500);
    await expect(page.locator('body')).toBeVisible();
  });
});
