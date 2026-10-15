import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  getAdminNonce,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';
import { patchSettingsTab, writeSettingsTab } from '../../utils/settings';
import { adminContext, freshContext } from '../../utils/context';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Advanced coupon rules, enforced where the money is: the checkout quote.
 *
 * The existing spec reads the advanced-rules endpoint. The rules only matter if
 * they are applied, and the costly direction is a coupon that discounts an order it
 * should not have — so this sets a minimum-subtotal above the cart value, proves the
 * coupon is refused, then lowers it and proves the same coupon now applies.
 */
let priorPaymentTab: Record<string, unknown> = {};

test.beforeAll(async ({ request, browser }) => {
  const ctx = await adminContext(browser);
  const page = await ctx.newPage();
  const nonce = await getAdminNonce(page);
  priorPaymentTab = await patchSettingsTab(request, nonce, 'payment', {
    enable_coupons: '1',
    enable_offline_payment: '1',
  });
  await ctx.close();
});

test.afterAll(async ({ request, browser }) => {
  const ctx = await adminContext(browser);
  const page = await ctx.newPage();
  const nonce = await getAdminNonce(page);
  await writeSettingsTab(request, nonce, 'payment', priorPaymentTab);
  await ctx.close();
});

test.describe('addon: advanced coupon rules', () => {
  test('a minimum-subtotal rule blocks the coupon until the cart qualifies', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/coupons_advanced/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'coupons_advanced is not included in this licence',
    );

    const stamp = Date.now();
    const coursePrice = 50;
    const course = await createCourseViaRest(page, request, {
      title: `E2E adv coupon course ${stamp}`,
      price: coursePrice,
    });

    const code = slug('ADV').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 16);
    const couponRes = await request.post('/wp-json/sikshya/v1/admin/coupons', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { code, discount_type: 'percent', discount_value: 50, max_uses: 100, status: 'active' },
    });
    const couponText = await couponRes.text();
    expect(couponRes.status(), `coupon creation failed: ${couponText.slice(0, 250)}`).toBeLessThan(400);
    const couponBody = JSON.parse(couponText) as Record<string, unknown>;
    const couponId = Number(
      couponBody.id ??
        (couponBody.coupon as Record<string, unknown>)?.id ??
        (couponBody.data as Record<string, unknown>)?.id ??
        0,
    );
    expect(couponId, `the coupon must have an id: ${couponText.slice(0, 250)}`).toBeGreaterThan(0);

    const setRules = async (rules: Record<string, unknown>) => {
      const res = await request.post(`/wp-json/sikshya/v1/pro/coupons/${couponId}/advanced`, {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: { rules },
      });
      const text = await res.text();
      expect(res.status(), `saving advanced rules failed: ${text.slice(0, 250)}`).toBeLessThan(400);

      // Read back — a rule that did not persist would make the behaviour below
      // prove nothing.
      const check = await request.get(`/wp-json/sikshya/v1/pro/coupons/${couponId}/advanced`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      const checkBody = (await check.json().catch(() => ({}))) as {
        rules?: Record<string, unknown>;
      };
      return checkBody.rules ?? {};
    };

    // Rule: the cart must be worth at least twice this course.
    const storedHigh = await setRules({ min_subtotal: coursePrice * 2 });
    test.info().attach('rules-high', { body: JSON.stringify(storedHigh, null, 2).slice(0, 400) });
    expect(
      Number(storedHigh.min_subtotal ?? 0),
      'precondition: the minimum-subtotal rule must persist',
    ).toBe(coursePrice * 2);

    // Learner with the course in the cart.
    const username = slug('advcpn');
    const password = 'AdvPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);

    await session.page.goto(`/?p=${course.id}`, { waitUntil: 'domcontentloaded' });
    const addForm = session.page
      .locator('form input[name="sikshya_cart_action"][value="add"]')
      .first()
      .locator('xpath=..');
    await expect(addForm, 'the paid course must offer add-to-cart').toBeVisible({ timeout: 20_000 });
    await Promise.all([
      session.page.waitForResponse(
        (r) => r.request().method() === 'POST' && r.request().isNavigationRequest(),
        { timeout: 20_000 },
      ),
      addForm.evaluate((f: HTMLFormElement) => f.submit()),
    ]);
    await session.page.waitForLoadState('domcontentloaded');

    const quote = async (withCoupon: boolean) => {
      const res = await session.request.post('/wp-json/sikshya/v1/checkout/quote', {
        headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
        data: withCoupon ? { coupon_code: code } : {},
      });
      const text = await res.text();
      const body = JSON.parse(text || '{}') as {
        data?: Record<string, unknown>;
        message?: string;
        code?: string;
      };
      const d = body.data ?? {};
      const total = Number(d.total ?? d.payable ?? d.grand_total ?? d.amount ?? 0);
      return { status: res.status(), total, text, message: String(body.message ?? '') };
    };

    const base = await quote(false);
    expect(base.status, `base quote failed: ${base.text.slice(0, 200)}`).toBeLessThan(400);
    expect(base.total, `base total should be the course price; ${base.text.slice(0, 200)}`).toBeCloseTo(
      coursePrice,
      2,
    );

    // The rule is not met. The implementation refuses the quote outright and says
    // why ("This coupon requires a minimum order of 100.00"), which is better than
    // silently dropping the coupon — a learner who pasted a code deserves to know
    // it did not apply. Accept either shape, but require that the order is NOT
    // discounted, since that is the costly direction.
    const blocked = await quote(true);
    test.info().attach('quote-rule-not-met', {
      body: `status=${blocked.status}\n${blocked.text.slice(0, 600)}`,
    });
    if (blocked.status >= 400) {
      expect(
        blocked.message.toLowerCase(),
        `a refused coupon must explain itself; got: ${blocked.text.slice(0, 200)}`,
      ).toMatch(/minimum|requires/);
    } else {
      expect(
        blocked.total,
        `a coupon whose minimum-subtotal rule is not met must not discount the order ` +
          `(expected ${coursePrice}, got ${blocked.total})`,
      ).toBeCloseTo(coursePrice, 2);
    }

    // Relax the rule; the same coupon must now apply.
    const storedLow = await setRules({ min_subtotal: 1 });
    expect(Number(storedLow.min_subtotal ?? 0), 'precondition: the relaxed rule must persist').toBe(1);

    const applied = await quote(true);
    test.info().attach('quote-rule-met', {
      body: `status=${applied.status}\n${applied.text.slice(0, 600)}`,
    });
    expect(
      applied.status,
      `once the cart qualifies the quote must succeed: ${applied.text.slice(0, 200)}`,
    ).toBeLessThan(400);
    expect(
      applied.total,
      `once the cart qualifies, the 50% coupon must apply (expected ~${coursePrice / 2}, got ${applied.total})`,
    ).toBeLessThan(coursePrice);

    await session.context.close();
  });
});
