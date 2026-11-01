import { test, expect } from '@playwright/test';
import { createCourseViaRest, getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The shared course picker's search box.
 *
 * This exists because the picker shipped with a search box that silently did
 * nothing. Nearly every call site builds the `value`/`excludeIds` prop in
 * render (`value={id > 0 ? [id] : []}`), so the array was a new object on every
 * render even when the ids had not changed; the effect that resets the modal
 * was keyed on that array and so ran on every render, clearing the query
 * between keystrokes. The request went out with no search term and the list
 * showed the unfiltered first page ordered by title, which on a site with more
 * courses than `perPage` left most of them unreachable.
 *
 * The end-to-end symptom only appears once a site has more courses than one
 * page, so asserting "the course I typed is listed" is not enough on a small
 * fixture. These assertions are on the mechanism instead: the box keeps what
 * was typed, and the request carries it.
 */
test.describe('admin: course picker search', () => {
  test('typing in the picker survives re-renders and reaches the query', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const stamp = Date.now();
    const title = `Picker Search Course ${stamp}`;
    await createCourseViaRest(page, request, { title });

    const searches: string[] = [];
    page.on('request', (r) => {
      const url = r.url();
      if (url.includes('/courses/search') || url.includes('sik_course?')) {
        searches.push(new URL(url).searchParams.get('search') ?? '');
      }
    });

    await page.goto('/wp-admin/admin.php?page=sikshya&view=discussions', { waitUntil: 'domcontentloaded' });

    const trigger = page
      .getByRole('button', { name: /choose a course|select a course/i })
      .first();
    await expect(trigger).toBeVisible({ timeout: 30_000 });
    await trigger.click();

    const dialog = page.getByRole('dialog');
    const box = dialog.getByPlaceholder(/Search courses/i);
    await expect(box).toBeVisible({ timeout: 15_000 });

    searches.length = 0;
    await box.fill(title);

    // Long enough for the 200ms debounce plus any renders the fill provokes.
    await expect(box, 'the search box must keep what was typed').toHaveValue(title, { timeout: 5_000 });
    await expect
      .poll(() => searches.filter((s) => s === title).length, { timeout: 15_000 })
      .toBeGreaterThan(0);

    // The termless search the modal issues when it opens is correct — the list
    // starts as an unfiltered page. The regression is a termless search landing
    // *after* the user has typed, because that is what replaced their results.
    // So the assertion is on the last search, not on the absence of empty ones:
    // WebKit can deliver the open-search after the fill, and that is harmless.
    await page.waitForTimeout(1_000);
    expect(
      searches[searches.length - 1],
      `the last search dropped the term; terms seen: ${JSON.stringify(searches)}`,
    ).toBe(title);

    await expect(dialog.getByText(title, { exact: false }).first()).toBeVisible({ timeout: 15_000 });
  });
});
