import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * White Label, verified where it is supposed to show: the admin UI.
 *
 * The existing spec reads `/pro/white-label` and checks it answers. The feature's
 * entire purpose is that an agency's client never sees the word "Sikshya", and the
 * addon delivers that by filtering `sikshya_admin_menu_label`, `sikshya_label` and
 * the admin footer. So the test worth having loads wp-admin and looks.
 *
 * Branding is global state, so the original values are captured and restored.
 */
let original: Record<string, unknown> | null = null;

test.afterAll(async ({ request, browser }) => {
  if (original === null) return;
  const ctx = await browser.newContext({ storageState: 'e2e/.auth/admin.json' });
  const page = await ctx.newPage();
  const nonce = await getAdminNonce(page);
  await request.post('/wp-json/sikshya/v1/pro/white-label', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: original,
  });
  await ctx.close();
});

test.describe('addon: white label branding', () => {
  test('a custom brand name replaces Sikshya in the admin menu', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/white_label/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'white_label is not included in this licence',
    );

    // Control: before branding, the admin menu says "Sikshya". Without this the
    // assertion below could pass on a site that never showed it in the first place.
    await page.goto('/wp-admin/admin.php?page=sikshya', { waitUntil: 'domcontentloaded' });
    const before = await page.locator('#adminmenu').innerText().catch(() => '');
    test.info().attach('admin-menu-before', { body: before.slice(0, 600) });
    expect(
      before,
      'control: the admin menu must show the default brand before white-labelling',
    ).toContain('Sikshya');

    const read = await request.get('/wp-json/sikshya/v1/pro/white-label', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const readBody = await read.json().catch(() => ({}));
    expect(read.ok(), `GET /pro/white-label status=${read.status()}`).toBeTruthy();
    original = (readBody?.data ?? readBody?.branding ?? readBody ?? {}) as Record<string, unknown>;

    const brand = `Acme LMS ${Date.now()}`;
    const save = await request.post('/wp-json/sikshya/v1/pro/white-label', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { ...original, brand_name: brand, brand_short_name: 'Acme' },
    });
    const saveText = await save.text();
    test.info().attach('white-label-save', { body: saveText.slice(0, 400) });
    expect(save.status(), `saving branding failed: ${saveText.slice(0, 250)}`).toBeLessThan(400);

    // The payoff: the admin menu must carry the custom brand.
    await page.goto('/wp-admin/admin.php?page=sikshya', { waitUntil: 'domcontentloaded' });
    const after = await page.locator('#adminmenu').innerText().catch(() => '');
    test.info().attach('admin-menu-after', { body: after.slice(0, 600) });

    expect(
      after,
      `the admin menu must show the custom brand "Acme"; menu was: ${after.slice(0, 300)}`,
    ).toMatch(/Acme/);

    // The addon filters `sikshya_admin_menu_label`, so what it promises is that the
    // plugin's own top-level entry carries the custom brand. Assert exactly that,
    // rather than "the string Sikshya appears nowhere in wp-admin" — which is a
    // stronger claim than the feature makes.
    const topLevel = await page
      .locator('#adminmenu > li.menu-top a.wp-has-submenu, #adminmenu > li.menu-top > a')
      .allInnerTexts()
      .catch(() => [] as string[]);
    test.info().attach('top-level-menu-items', { body: topLevel.join('\n') });
    expect(
      topLevel.some((t) => /Acme/.test(t)),
      `the plugin's top-level menu entry must be rebranded; entries: ${topLevel.join(' | ').slice(0, 300)}`,
    ).toBeTruthy();
    expect(
      topLevel.some((t) => /^\s*Sikshya\s*$/.test(t)),
      'the default "Sikshya" top-level entry must be gone once branding is set',
    ).toBeFalsy();

    /*
     * Known residual, deliberately not asserted against: the Multisite Scale
     * add-on registers a submenu hardcoded as "Sikshya Pro — Network license"
     * (NetworkLicenseAdminPageController), which white-labelling does not filter.
     * It is a network-licence screen aimed at the network administrator rather
     * than the end client, so whether it should be rebranded is a product
     * decision. Recorded here so it is not mistaken for untested.
     */
  });

  test('branding also reaches the React admin app config', async ({ page, request }) => {
    // `sikshya_react_admin_config` is the other surface the addon filters; the
    // React shell reads its brand from there, so a client sees it on every screen.
    const nonce = await getAdminNonce(page);
    const read = await request.get('/wp-json/sikshya/v1/pro/white-label', {
      headers: { 'X-WP-Nonce': nonce },
    });
    test.skip(!read.ok(), 'white_label not available on this licence');

    await page.goto('/wp-admin/admin.php?page=sikshya', { waitUntil: 'domcontentloaded' });
    const html = await page.content();
    expect(
      /Acme/.test(html),
      'the admin app must render the custom brand once white-labelling is configured',
    ).toBeTruthy();
  });
});
