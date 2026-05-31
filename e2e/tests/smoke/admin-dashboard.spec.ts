import { test, expect } from '@playwright/test';

test.describe('smoke: Sikshya React admin shell', () => {
  test('?page=sikshya renders the React app shell', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
    page.on('console', (m) => {
      if (m.type() === 'error') errors.push(`console.error: ${m.text()}`);
    });

    await page.goto('/wp-admin/admin.php?page=sikshya');
    await expect(page.locator('body.sikshya-react-shell, #sikshya-admin-app, [data-sikshya-admin-app]'))
      .toBeVisible({ timeout: 20_000 });
    await page.waitForLoadState('networkidle');
    expect(errors, errors.join('\n')).toEqual([]);
  });
});
