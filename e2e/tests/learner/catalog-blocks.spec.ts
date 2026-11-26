import { test, expect, type Page } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';
import { freshContext } from '../../utils/context';

/**
 * The catalog blocks, as a visitor's browser actually computes them.
 *
 * These assertions exist because the PHP suite cannot make them. A
 * server-side render test sees the markup and the class names, and every one
 * of those was correct while the page looked broken: the call to action
 * shipped in 1.0.21 as bare underlined text with no padding or background,
 * because a CSS rule meant to stand aside for block themes was keyed on
 * `body.wp-embed-responsive` — a class WordPress also puts on classic themes.
 * Nothing but a real browser reading `getComputedStyle` can catch that class
 * of defect, so that is what this spec does.
 *
 * It creates its own page, so it does not depend on the site's content.
 */

const BLOCKS = `
<!-- wp:sikshya/course-search {"showCategoryFilter":true} /-->
<!-- wp:sikshya/course-categories {"columns":4,"limit":4} /-->
<!-- wp:sikshya/course-grid {"perPage":6,"columns":3} /-->
<!-- wp:sikshya/course-grid {"perPage":2,"layout":"list"} /-->
`;

async function createBlockPage(page: Page): Promise<{ id: number; link: string }> {
  const nonce = await getAdminNonce(page);

  const response = await page.request.post('/wp-json/wp/v2/pages', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: {
      title: `E2E Catalog Blocks ${Date.now()}`,
      status: 'publish',
      content: BLOCKS,
    },
  });

  expect(response.ok(), `Creating the block page failed: ${response.status()}`).toBeTruthy();
  const body = await response.json();

  return { id: body.id as number, link: body.link as string };
}

test.describe('catalog blocks render for a visitor', () => {
  let pageId = 0;
  let link = '';

  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext();
    const admin = await ctx.newPage();
    const created = await createBlockPage(admin);
    pageId = created.id;
    link = created.link;
    await ctx.close();
  });

  test.afterAll(async ({ browser }) => {
    if (!pageId) return;
    const ctx = await browser.newContext();
    const admin = await ctx.newPage();
    const nonce = await getAdminNonce(admin);
    await admin.request.delete(`/wp-json/wp/v2/pages/${pageId}?force=true`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    await ctx.close();
  });

  test('the call to action is a real button, not bare text', async ({ browser }) => {
    // Signed out on purpose: this is what a prospective student sees, and the
    // project's storageState would otherwise make it the administrator.
    const ctx = await freshContext(browser);
    const page = await ctx.newPage();

    await page.goto(link, { waitUntil: 'networkidle' });

    const cta = page.locator('.sikshya-cg-card__cta').first();
    await expect(cta).toBeVisible();

    const style = await cta.evaluate((el) => {
      const s = getComputedStyle(el);
      return {
        background: s.backgroundColor,
        paddingTop: parseFloat(s.paddingTop),
        paddingLeft: parseFloat(s.paddingLeft),
        display: s.display,
      };
    });

    // A transparent background with no padding is the 1.0.21 regression.
    expect(style.background, 'the button has no background colour').not.toBe('rgba(0, 0, 0, 0)');
    expect(style.background).not.toBe('transparent');
    expect(style.paddingTop, 'the button has no vertical padding').toBeGreaterThan(2);
    expect(style.paddingLeft, 'the button has no horizontal padding').toBeGreaterThan(6);
    expect(style.display).not.toBe('inline');

    await ctx.close();
  });

  test('cards are styled, laid out in columns, and do not overflow', async ({ browser }) => {
    const ctx = await freshContext(browser);
    const page = await ctx.newPage();
    await page.setViewportSize({ width: 1440, height: 1000 });

    const consoleErrors: string[] = [];
    page.on('pageerror', (e) => consoleErrors.push(`pageerror: ${e.message}`));
    page.on('console', (m) => {
      if (m.type() === 'error') consoleErrors.push(`console.error: ${m.text()}`);
    });

    await page.goto(link, { waitUntil: 'networkidle' });

    const cards = page.locator('.sikshya-cg-card');
    expect(await cards.count(), 'no course cards rendered').toBeGreaterThan(0);

    const facts = await page.evaluate(() => {
      const card = document.querySelector('.sikshya-cg-card') as HTMLElement | null;
      const list = document.querySelector('.sikshya-cg__list') as HTMLElement | null;
      const cs = card ? getComputedStyle(card) : null;

      return {
        // The stylesheet is attached per block; if it never loaded, the card
        // has no background and no radius and this is how we find out.
        cardBackground: cs ? cs.backgroundColor : '',
        cardRadius: cs ? parseFloat(cs.borderTopLeftRadius) : 0,
        columns: list ? getComputedStyle(list).gridTemplateColumns.split(' ').length : 0,
        cardWidth: card ? card.getBoundingClientRect().width : 0,
        horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
      };
    });

    expect(facts.cardBackground, 'the catalog stylesheet did not load').not.toBe('rgba(0, 0, 0, 0)');
    expect(facts.cardRadius, 'the catalog stylesheet did not load').toBeGreaterThan(0);
    expect(facts.columns, 'the grid did not lay out in columns').toBeGreaterThan(1);
    // A column narrower than this is the "three columns in a 490px theme
    // container" problem the container query exists to prevent.
    expect(facts.cardWidth, 'columns are too narrow to read').toBeGreaterThan(220);
    expect(facts.horizontalOverflow, 'the page scrolls sideways').toBeFalsy();
    expect(consoleErrors, consoleErrors.join('\n')).toEqual([]);

    await ctx.close();
  });

  test('the grid collapses to one column on a phone', async ({ browser }) => {
    const ctx = await freshContext(browser);
    const page = await ctx.newPage();
    await page.setViewportSize({ width: 390, height: 844 });

    await page.goto(link, { waitUntil: 'networkidle' });

    const facts = await page.evaluate(() => {
      const list = document.querySelector('.sikshya-cg__list') as HTMLElement | null;
      return {
        columns: list ? getComputedStyle(list).gridTemplateColumns.split(' ').length : 0,
        horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
      };
    });

    expect(facts.columns).toBe(1);
    expect(facts.horizontalOverflow, 'the page scrolls sideways on a phone').toBeFalsy();

    await ctx.close();
  });

  test('the companion blocks render', async ({ browser }) => {
    const ctx = await freshContext(browser);
    const page = await ctx.newPage();

    await page.goto(link, { waitUntil: 'networkidle' });

    await expect(page.locator('.sikshya-course-search__form')).toBeVisible();
    await expect(page.locator('.sikshya-course-search__input')).toBeVisible();
    // The archive reads `sikshya_cat`; any other name silently filters nothing.
    await expect(page.locator('select[name="sikshya_cat"]')).toBeVisible();

    await ctx.close();
  });
});
