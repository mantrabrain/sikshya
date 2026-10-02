import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  createUserViaRest,
  getAdminNonce,
  learnUrl,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';
import { patchSettingsTab, writeSettingsTab } from '../../utils/settings';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The money path, end to end: cart → order → fulfilment → access.
 *
 * Existing coverage stops short of this. `cart-add-paid` only asserts that the
 * admin manual-enrolment endpoint returns a 2xx (it never checks the learner
 * gains access), and the cart/checkout specs only assert the pages are
 * reachable. Nothing exercised a learner actually buying a course.
 *
 * Two properties matter here, and the second is a paywall guarantee:
 *   1. A completed, paid order grants access.
 *   2. An order that has been placed but NOT yet paid must grant nothing.
 *
 * Checkout deliberately reads the server-side cart rather than client-supplied
 * course ids, so the cart is populated through the real on-page form.
 */
let priorPaymentTab: Record<string, unknown> = {};

test.beforeAll(async ({ request, browser }) => {
  // Offline payment is off on a default install, and with no gateway enabled a
  // paid course cannot be ordered at all — the course page falls back to an
  // "Enroll" CTA and checkout refuses with "This course requires purchase".
  //
  // `patchSettingsTab` preserves the rest of the tab: `/settings/save` replaces
  // whatever tab it is handed, so a partial save silently resets its neighbours.
  const ctx = await browser.newContext({ storageState: 'e2e/.auth/admin.json' });
  const page = await ctx.newPage();
  const nonce = await getAdminNonce(page);
  priorPaymentTab = await patchSettingsTab(request, nonce, 'payment', {
    enable_offline_payment: '1',
  });
  await ctx.close();
});

test.afterAll(async ({ request, browser }) => {
  const ctx = await browser.newContext({ storageState: 'e2e/.auth/admin.json' });
  const page = await ctx.newPage();
  const nonce = await getAdminNonce(page);
  await writeSettingsTab(request, nonce, 'payment', priorPaymentTab);
  await ctx.close();
});

test.describe('checkout: paid purchase and fulfilment', () => {
  test('an unpaid order grants no access; marking it paid enrols the learner', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);

    const course = await createCourseViaRest(page, request, {
      title: `E2E purchase ${Date.now()}`,
      price: 35,
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    const username = slug('buyer');
    const password = 'BuyPass!234';
    const buyer = await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);

    // --- Add to cart through the real form on the course page.
    await session.page.goto(`/?p=${course.id}`, { waitUntil: 'domcontentloaded' });
    const addForm = session.page
      .locator('form input[name="sikshya_cart_action"][value="add"]')
      .first()
      .locator('xpath=..');
    await expect(addForm, 'a paid course must offer an add-to-cart form').toBeVisible({
      timeout: 15_000,
    });
    await Promise.all([
      session.page.waitForResponse(
        (r) => r.request().method() === 'POST' && r.request().isNavigationRequest(),
        { timeout: 20_000 },
      ),
      addForm.evaluate((f: HTMLFormElement) => f.submit()),
    ]);
    await session.page.waitForLoadState('domcontentloaded');

    // --- Create the order against the server-side cart.
    const sessionRes = await session.request.post('/wp-json/sikshya/v1/checkout/session', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { gateway: 'offline' },
    });
    const sessionText = await sessionRes.text();
    test.info().attach('checkout-session', { body: sessionText.slice(0, 900) });
    expect(
      sessionRes.status(),
      `POST /checkout/session must create an order; body=${sessionText.slice(0, 300)}`,
    ).toBeLessThan(400);

    const sessionBody = JSON.parse(sessionText) as {
      data?: { order_id?: number; public_token?: string };
      order_id?: number;
      public_token?: string;
    };
    const orderId = Number(sessionBody.order_id ?? sessionBody.data?.order_id ?? 0);
    const publicToken = String(sessionBody.public_token ?? sessionBody.data?.public_token ?? '');
    expect(orderId, 'an order id must come back from checkout').toBeGreaterThan(0);

    await session.request.post('/wp-json/sikshya/v1/checkout/confirm', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { order_id: orderId, public_token: publicToken, gateway: 'offline' },
    });

    // --- PAYWALL: the order exists but is not paid, so nothing is unlocked yet.
    const beforeProgress = await session.request.get(
      `/wp-json/sikshya/v1/me/progress?course_id=${course.id}`,
      { headers: { 'X-WP-Nonce': session.restNonce } },
    );
    expect(
      beforeProgress.status(),
      'an unpaid offline order must not enrol the learner — that is a free-course bypass',
    ).toBeGreaterThanOrEqual(400);

    await session.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
    const lockedText = (await session.page.locator('body').innerText()).toLowerCase();
    test.info().attach('lesson-before-payment', { body: lockedText.slice(0, 900) });
    expect(
      lockedText,
      'lesson content must not be readable before the order is paid',
    ).toMatch(/enrol|enroll|purchase|buy|not have access|restricted|sign in|log in/);

    // --- Admin fulfils the order.
    const markPaid = await request.post(`/wp-json/sikshya/v1/admin/orders/${orderId}/mark-paid`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {},
    });
    const markBody = await markPaid.text();
    test.info().attach('mark-paid', { body: markBody.slice(0, 400) });
    expect(markPaid.status(), `mark-paid failed: ${markBody.slice(0, 300)}`).toBeLessThan(400);

    // --- Fulfilment must grant access.
    const afterProgress = await session.request.get(
      `/wp-json/sikshya/v1/me/progress?course_id=${course.id}`,
      { headers: { 'X-WP-Nonce': session.restNonce } },
    );
    const afterText = await afterProgress.text();
    test.info().attach('progress-after-payment', { body: afterText.slice(0, 400) });
    expect(
      afterProgress.ok(),
      `a paid order must enrol the buyer; /me/progress said ${afterProgress.status()}: ${afterText.slice(0, 200)}`,
    ).toBeTruthy();

    // And the admin orders list must show the order as paid for the buyer.
    const orders = await request.get('/wp-json/sikshya/v1/admin/orders?per_page=20', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const ordersText = await orders.text();
    expect(orders.ok()).toBeTruthy();
    expect(
      ordersText,
      'the fulfilled order must be visible to staff',
    ).toContain(String(orderId));
    expect(buyer.id).toBeGreaterThan(0);

    await session.context.close();
  });
});
