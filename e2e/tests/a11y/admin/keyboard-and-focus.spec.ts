import { test, expect } from '@playwright/test';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The part of accessibility that axe cannot see.
 *
 * A static scan reads the DOM at rest: it can tell that a dialog is marked
 * `aria-modal`, not whether Tab stays inside it, and not whether closing it
 * leaves a keyboard user stranded at the top of the document. Both of those
 * were broken, in both dialogs, and no automated rule reported either.
 */

const COURSES = '/wp-admin/admin.php?page=sikshya&view=courses';

/** What the browser would read out as the focused element. */
const focusedDescription = (page: import('@playwright/test').Page) =>
  page.evaluate(() => {
    const el = document.activeElement as HTMLElement | null;
    if (!el) return 'none';
    return [
      el.tagName.toLowerCase(),
      el.getAttribute('data-testid') ?? '',
      (el.getAttribute('aria-label') ?? el.textContent ?? '').trim().slice(0, 40),
    ]
      .filter(Boolean)
      .join(' · ');
  });

test.describe('global search palette', () => {
  test('opens on the keyboard shortcut and puts focus in the field', async ({ page }) => {
    await page.goto(COURSES, { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle').catch(() => undefined);

    await page.keyboard.press('ControlOrMeta+KeyK');

    const panel = page.getByTestId('topbar-global-search-panel');
    await expect(panel).toBeVisible();
    await expect(panel.locator('input')).toBeFocused();
  });

  test('Escape closes it and gives focus back to the trigger', async ({ page }) => {
    await page.goto(COURSES, { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle').catch(() => undefined);

    const trigger = page.getByTestId('topbar-global-search-trigger');
    await trigger.click();
    await expect(page.getByTestId('topbar-global-search-panel')).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(page.getByTestId('topbar-global-search-panel')).toHaveCount(0);

    // Not "somewhere plausible": the exact control that opened it.
    await expect(trigger).toBeFocused();
  });

  test('Tab cannot leave the open palette', async ({ page }) => {
    await page.goto(COURSES, { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle').catch(() => undefined);

    await page.getByTestId('topbar-global-search-trigger').click();
    const panel = page.getByTestId('topbar-global-search-panel');
    await expect(panel).toBeVisible();

    // Far more presses than the palette has stops, so a leak shows up.
    for (let i = 0; i < 12; i++) {
      await page.keyboard.press('Tab');
      const inside = await page.evaluate(() => {
        const p = document.querySelector('[data-testid="topbar-global-search-panel"]');
        return Boolean(p && document.activeElement && p.contains(document.activeElement));
      });
      expect(inside, `focus left the palette after ${i + 1} Tab press(es) (on ${await focusedDescription(page)})`).toBe(true);
    }

    await page.keyboard.press('Shift+Tab');
    const stillInside = await page.evaluate(() => {
      const p = document.querySelector('[data-testid="topbar-global-search-panel"]');
      return Boolean(p && document.activeElement && p.contains(document.activeElement));
    });
    expect(stillInside, 'Shift+Tab left the palette').toBe(true);
  });
});

test.describe('shared modal', () => {
  test('traps Tab, closes on Escape, and returns focus to the button that opened it', async ({ page }) => {
    await page.goto(COURSES, { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle').catch(() => undefined);

    const addNew = page.getByRole('button', { name: /add new/i }).first();
    await expect(addNew).toBeVisible();
    await addNew.click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();

    // Focus starts inside the dialog rather than on the page behind it.
    await expect
      .poll(async () =>
        page.evaluate(() => {
          const d = document.querySelector('[role="dialog"]');
          return Boolean(d && document.activeElement && d.contains(document.activeElement));
        })
      )
      .toBe(true);

    for (let i = 0; i < 15; i++) {
      await page.keyboard.press('Tab');
      const inside = await page.evaluate(() => {
        const d = document.querySelector('[role="dialog"]');
        return Boolean(d && document.activeElement && d.contains(document.activeElement));
      });
      expect(inside, `focus left the dialog after ${i + 1} Tab press(es) (on ${await focusedDescription(page)})`).toBe(true);
    }

    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(addNew).toBeFocused();
  });
});
