import { test, expect } from '@playwright/test';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Pro plugin is shipped in this same Local site but may or may not be active.
 * These specs document the detection signal so future Pro-addon smokes can
 * conditionally skip.
 */
test.describe('pro: Sikshya Pro presence detection', () => {
  test('plugins.php lists sikshya-pro and reports its active state', async ({ page }) => {
    await page.goto('/wp-admin/plugins.php?s=sikshya');
    await expect(page.locator('body')).toContainText(/Sikshya/i);

    const proRow = page.locator('tr[data-plugin*="sikshya-pro"]').first();
    if (await proRow.count()) {
      const rowClass = (await proRow.getAttribute('class')) ?? '';
      const isActive = /\\bactive\\b/.test(rowClass) && !/inactive/.test(rowClass);
      test.info().annotations.push({
        type: 'pro-active',
        description: isActive ? 'yes' : 'no',
      });
    } else {
      test.info().annotations.push({ type: 'pro-active', description: 'plugin not installed' });
    }
  });

  test('REST namespace check exposes sikshya-pro when active, omits when not', async ({ request }) => {
    const res = await request.get('/wp-json/');
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    const namespaces: string[] = body?.namespaces ?? [];
    const hasPro = namespaces.some((n) => /sikshya[-_]?pro|sikshyaPro/i.test(n));
    test.info().annotations.push({
      type: 'pro-namespace',
      description: hasPro ? 'present' : 'absent',
    });
  });
});
