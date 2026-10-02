import { test, expect } from '@playwright/test';
import {
  createUserViaRest,
  getAdminNonce,
  loginAs,
  slug,
  INSTRUCTOR_ROLE,
  STUDENT_ROLE,
} from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Marketplace vendor surfaces: who sees whose money.
 *
 * The existing spec reads the admin overview as an administrator. The risk in a
 * multivendor marketplace is not that the admin screen renders — it is that one
 * vendor can read another vendor's earnings, or that a learner can reach the payout
 * surfaces at all. Those are the same class of leak as the enrolment and quiz-attempt
 * endpoints that were exposing learner data.
 *
 * Earnings rows need paid orders with commissions, which is heavy to stage, so this
 * asserts the scoping of the surfaces themselves: each vendor's own endpoint answers
 * for *them*, the admin-only endpoints refuse a vendor, and a plain learner is
 * refused outright.
 */
const VENDOR_SELF = '/wp-json/sikshya/v1/pro/marketplace/vendor/me';
const VENDOR_EARNINGS = '/wp-json/sikshya/v1/pro/marketplace/vendor/earnings';
const ADMIN_VENDORS = '/wp-json/sikshya/v1/pro/marketplace/admin/vendors';
const ADMIN_WITHDRAWALS = '/wp-json/sikshya/v1/pro/marketplace/admin/withdrawals';

async function restNonceFor(page: import('@playwright/test').Page): Promise<string> {
  await page.goto('/wp-admin/profile.php', { waitUntil: 'domcontentloaded' });
  return page.evaluate(() => {
    const w = window as unknown as { wpApiSettings?: { nonce?: string } };
    return w.wpApiSettings?.nonce ?? '';
  });
}

test.describe('addon: marketplace vendor scoping', () => {
  test('each vendor sees only their own surface; admin screens refuse vendors and learners', async ({
    page,
    request,
    browser,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post(
      '/wp-json/sikshya/v1/admin/addons/marketplace_multivendor/enable',
      { headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' } },
    );
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'marketplace_multivendor is not included in this licence',
    );

    const password = 'VendPass!234';
    const nameA = slug('vendA');
    const nameB = slug('vendB');
    const vendorA = await createUserViaRest(page, request, {
      username: nameA,
      email: `${nameA}@example.com`,
      password,
      role: INSTRUCTOR_ROLE,
    });
    const vendorB = await createUserViaRest(page, request, {
      username: nameB,
      email: `${nameB}@example.com`,
      password,
      role: INSTRUCTOR_ROLE,
    });
    const learnerName = slug('vendLearner');
    await createUserViaRest(page, request, {
      username: learnerName,
      email: `${learnerName}@example.com`,
      password,
      role: STUDENT_ROLE,
    });

    // Admin baseline: the admin surfaces must work for an administrator, otherwise
    // the 403s below would prove nothing — they could just mean the routes are broken.
    const adminVendors = await request.get(ADMIN_VENDORS, { headers: { 'X-WP-Nonce': nonce } });
    expect(
      adminVendors.ok(),
      `control: an administrator must be able to list vendors (got ${adminVendors.status()})`,
    ).toBeTruthy();

    // --- Vendor A
    const ctxA = await browser.newContext();
    const pageA = await loginAs(ctxA, nameA, password);
    const nonceA = await restNonceFor(pageA);
    expect(nonceA, 'vendor A must obtain a REST nonce').toBeTruthy();

    const meA = await ctxA.request.get(VENDOR_SELF, { headers: { 'X-WP-Nonce': nonceA } });
    const meAText = await meA.text();
    test.info().attach('vendorA-me', { body: meAText.slice(0, 400) });
    expect(meA.ok(), `a vendor must be able to read their own profile (got ${meA.status()})`).toBeTruthy();
    const meABody = JSON.parse(meAText) as { user?: { id?: number } };
    expect(
      Number(meABody.user?.id ?? 0),
      'the vendor endpoint must answer for the requesting vendor, not someone else',
    ).toBe(vendorA.id);

    // Vendor A must not reach the marketplace admin surfaces.
    for (const [label, url] of [
      ['vendors list', ADMIN_VENDORS],
      ['withdrawals queue', ADMIN_WITHDRAWALS],
    ] as const) {
      const res = await ctxA.request.get(url, { headers: { 'X-WP-Nonce': nonceA } });
      const text = await res.text();
      expect(
        res.status(),
        `a vendor must not reach the marketplace ${label} — it exposes every vendor's money ` +
          `(got ${res.status()}: ${text.slice(0, 160)})`,
      ).toBe(403);
    }
    await ctxA.close();

    // --- Vendor B: the same endpoint must answer for B, not A.
    const ctxB = await browser.newContext();
    const pageB = await loginAs(ctxB, nameB, password);
    const nonceB = await restNonceFor(pageB);
    const meB = await ctxB.request.get(VENDOR_SELF, { headers: { 'X-WP-Nonce': nonceB } });
    const meBBody = (await meB.json().catch(() => ({}))) as { user?: { id?: number } };
    expect(meB.ok(), 'vendor B must read their own profile').toBeTruthy();
    expect(
      Number(meBBody.user?.id ?? 0),
      "vendor B's request must return vendor B — a shared or cached identity here would " +
        'cross-wire two vendors\' payout data',
    ).toBe(vendorB.id);
    expect(Number(meBBody.user?.id ?? 0)).not.toBe(vendorA.id);

    const earningsB = await ctxB.request.get(VENDOR_EARNINGS, { headers: { 'X-WP-Nonce': nonceB } });
    expect(earningsB.ok(), 'a vendor must be able to read their own earnings').toBeTruthy();
    await ctxB.close();

    // --- A plain learner must be refused the payout surfaces entirely.
    const ctxL = await browser.newContext();
    const pageL = await loginAs(ctxL, learnerName, password);
    const nonceL = await restNonceFor(pageL);
    for (const [label, url] of [
      ['vendor profile', VENDOR_SELF],
      ['vendor earnings', VENDOR_EARNINGS],
    ] as const) {
      const res = await ctxL.request.get(url, { headers: { 'X-WP-Nonce': nonceL } });
      const text = await res.text();
      expect(
        res.status(),
        `a learner must not reach the ${label} surface (got ${res.status()}: ${text.slice(0, 160)})`,
      ).toBeGreaterThanOrEqual(400);
    }
    await ctxL.close();
  });
});
