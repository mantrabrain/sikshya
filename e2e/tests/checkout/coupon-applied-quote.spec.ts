import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  getAdminNonce,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('checkout: coupon discount round-trip', () => {
  test('a percent coupon discounts a paid course in /checkout/quote', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const adminNonce = await getAdminNonce(page);

    // `CheckoutService::computePricingForCourses` silently drops coupon_code
    // unless `enable_coupons` is truthy. Toggle on for this test, restore at end.
    const readSettings = await request.get(
      '/wp-json/sikshya/v1/settings/values?tab=payment',
      { headers: { 'X-WP-Nonce': adminNonce } },
    );
    const settingsBody = await readSettings.json().catch(() => ({}));
    const prevCoupons = String(settingsBody?.data?.values?.enable_coupons ?? '0');
    if (prevCoupons !== '1') {
      await request.post('/wp-json/sikshya/v1/settings/save', {
        headers: { 'X-WP-Nonce': adminNonce, 'Content-Type': 'application/json' },
        data: { tab: 'payment', values: { enable_coupons: '1' } },
      });
    }

    // Paid course at 100 (price meta now persists via wp/v2 thanks to the
    // PostTypeManager fix).
    const course = await createCourseViaRest(page, request, {
      title: `E2E coupon course ${Date.now()}`,
      type: 'paid',
      price: 100,
    });

    // 50% off coupon.
    const code = slug('CPN50').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 16);
    const couponRes = await request.post('/wp-json/sikshya/v1/admin/coupons', {
      headers: { 'X-WP-Nonce': adminNonce, 'Content-Type': 'application/json' },
      data: {
        code,
        discount_type: 'percent',
        discount_value: 50,
        max_uses: 100,
        status: 'active',
      },
    });
    expect(couponRes.status()).toBeLessThan(400);

    // Student session.
    const username = slug('cpn');
    const password = 'CpnPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(
      browser,
      baseURL ?? 'http://sikshya.local',
      username,
      password,
    );

    // Populate the server cart by posting the course's cart form.
    await session.page.goto(course.link, { waitUntil: 'domcontentloaded' });
    const addForm = session.page
      .locator('form input[name="sikshya_cart_action"][value="add"]')
      .first()
      .locator('xpath=..');
    await expect(addForm, 'paid course should render the "Add to cart" form').toBeVisible({
      timeout: 20_000,
    });
    await Promise.all([
      session.page.waitForLoadState('domcontentloaded'),
      addForm.evaluate((f: HTMLFormElement) => f.submit()),
    ]);

    // Baseline quote (no coupon).
    const baseQuote = await session.request.post('/wp-json/sikshya/v1/checkout/quote', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: {},
    });
    const baseBody = await baseQuote.json().catch(() => ({}));
    expect(baseQuote.status(), JSON.stringify(baseBody).slice(0, 300)).toBeLessThan(400);
    const pickTotal = (d: Record<string, unknown> = {}): number => {
      const candidates = [d.total, d.amount, d.grand_total, d.payable];
      for (const v of candidates) {
        if (typeof v === 'number' && v > 0) return v;
        if (typeof v === 'string' && parseFloat(v) > 0) return parseFloat(v);
      }
      return 0;
    };
    const baseTotal = pickTotal(baseBody?.data ?? {});
    expect(baseTotal, `base total > 0; body=${JSON.stringify(baseBody).slice(0, 300)}`).toBeGreaterThan(0);

    // With coupon.
    const withCoupon = await session.request.post('/wp-json/sikshya/v1/checkout/quote', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { coupon_code: code },
    });
    const couponBody = await withCoupon.json().catch(() => ({}));
    expect(withCoupon.status(), JSON.stringify(couponBody).slice(0, 300)).toBeLessThan(400);
    const totalAfter = pickTotal(couponBody?.data ?? {});
    expect(totalAfter, `discounted total should be < base ${baseTotal}; got ${totalAfter}`).toBeLessThan(
      baseTotal,
    );

    // Restore `enable_coupons` to its prior state.
    if (prevCoupons !== '1') {
      await request.post('/wp-json/sikshya/v1/settings/save', {
        headers: { 'X-WP-Nonce': adminNonce, 'Content-Type': 'application/json' },
        data: { tab: 'payment', values: { enable_coupons: prevCoupons } },
      });
    }

    await session.context.close();
  });
});
