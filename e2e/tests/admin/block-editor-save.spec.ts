import { test, expect } from '@playwright/test';
import { slug } from '../../utils/factories';

/**
 * Drive the Gutenberg post editor for sik_course / sik_lesson / sik_quiz:
 * type a title, click "Save draft", and verify the post lands in the listing.
 *
 * The modern editor mounts an iframe — Playwright's role selectors traverse it
 * automatically since Playwright 1.40+, so the title button works without a
 * frame locator dance.
 */

const cases = [
  { postType: 'sik_course', label: 'Course' },
  { postType: 'sik_lesson', label: 'Lesson' },
  { postType: 'sik_quiz', label: 'Quiz' },
] as const;

for (const c of cases) {
  test(`block editor save draft for ${c.postType}`, async ({ page }) => {
    const title = `E2E ${c.label} ${slug('be')}`;

    await page.goto(`/wp-admin/post-new.php?post_type=${c.postType}`);
    await expect(page.getByRole('heading', { name: new RegExp(`Add New ${c.label}`, 'i') })).toBeVisible({
      timeout: 30_000,
    });

    // The title input lives inside the editor canvas iframe. There is exactly
    // one iframe inside the editor region — target it directly.
    const editorFrame = page.locator('region[name="Editor content"], [aria-label="Editor content"]')
      .first()
      .frameLocator('iframe')
      .first();
    const titleField = editorFrame.getByRole('textbox', { name: /Add title/i }).first();
    await titleField.click({ timeout: 30_000 });
    await page.keyboard.type(title, { delay: 5 });

    // "Save draft" lives on the outer top bar (outside the iframe).
    const saveDraft = page.getByRole('button', { name: /Save draft/i }).first();
    await expect(saveDraft).toBeEnabled({ timeout: 30_000 });
    await saveDraft.click();
    await expect(
      page
        .getByText(/^Saved$/i)
        .or(page.getByText(/Draft saved/i))
        .first(),
    ).toBeVisible({ timeout: 30_000 });

    await page.goto(`/wp-admin/edit.php?post_type=${c.postType}&s=${encodeURIComponent(title)}`);
    await expect(page.locator('body')).toContainText(title);
  });
}
