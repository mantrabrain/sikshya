import { test, expect } from '@playwright/test';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The migration panel on Tools.
 *
 * Page-driven and deliberately tolerant about whether another LMS's data is
 * present: on a site with none, the panel's job is to say so clearly, and that
 * is worth asserting too. The field-level fidelity of an import is covered by
 * PHPUnit, which can seed a Tutor-shaped library; a browser test cannot.
 */
test.describe('admin: move from another LMS', () => {
  test('the panel explains itself and reports what it finds', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=sikshya&view=tools', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle').catch(() => undefined);

    await page.getByRole('button', { name: /Move from another LMS/i }).click();

    const panel = page.locator('[data-sikshya-import-panel]');
    await expect(panel).toBeVisible();

    // The two promises the whole feature rests on are on the screen, not just
    // in the docs: nothing is written to the other plugin, and a second run
    // does not duplicate.
    await expect(panel).toContainText(/never changes or deletes anything/i);
    await expect(panel).toContainText(/running an import twice is safe/i);

    await page.getByRole('button', { name: /Check this site/i }).click();

    const tutor = panel.locator('[data-sikshya-import-source="tutor"]');
    const none = panel.getByText(/No supported LMS was found/i);

    await expect(tutor.or(none).first()).toBeVisible({ timeout: 30_000 });

    if (!(await tutor.isVisible().catch(() => false))) {
      // Nothing to import on this site: the clear "nothing found" answer is
      // the correct behaviour, so the test ends here rather than skipping.
      return;
    }

    await expect(tutor).toContainText(/Tutor LMS/i);

    await panel.locator('[data-sikshya-import-preview="tutor"] button').click();

    const result = panel.locator('[data-sikshya-import-result]');
    await expect(result).toBeVisible({ timeout: 60_000 });
    await expect(result).toContainText(/nothing was created/i);
  });
});
