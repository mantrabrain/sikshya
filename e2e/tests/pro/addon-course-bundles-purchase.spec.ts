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
import { adminContext, freshContext } from '../../utils/context';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Buying a bundle, through the browser, and checking it unlocks its components.
 *
 * The existing bundles spec is API-only: it creates a bundle and reads back its
 * course list. Nothing proved the thing a customer actually pays for — that
 * purchasing the bundle grants access to every course inside it. This drives the
 * real cart and checkout, then verifies access per component course.
 *
 * Only reachable with a licence that includes `course_bundles`.
 */
let priorPaymentTab: Record<string, unknown> = {};

test.beforeAll(async ({ request, browser }) => {
  const ctx = await adminContext(browser);
  const page = await ctx.newPage();
  const nonce = await getAdminNonce(page);
  priorPaymentTab = await patchSettingsTab(request, nonce, 'payment', {
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

test.describe('addon: course bundles purchase', () => {
  test('buying a bundle unlocks every course inside it', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/course_bundles/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'course_bundles is not included in this licence',
    );

    const stamp = Date.now();

    // Two paid components, so access genuinely has to be granted.
    const courseA = await createCourseViaRest(page, request, {
      title: `E2E bundled A ${stamp}`,
      price: 30,
    });
    const lessonA = await createLessonViaRest(page, request, courseA.id);
    const courseB = await createCourseViaRest(page, request, {
      title: `E2E bundled B ${stamp}`,
      price: 30,
    });
    const lessonB = await createLessonViaRest(page, request, courseB.id);

    const bundleTitle = `E2E Bundle buy ${slug('bnd')}`;
    const create = await request.post('/wp-json/sikshya/v1/pro/bundles', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { title: bundleTitle, course_ids: [courseA.id, courseB.id] },
    });
    const createBody = await create.json().catch(() => ({}));
    expect(create.status(), JSON.stringify(createBody).slice(0, 250)).toBeLessThan(400);
    const bundleId = Number(
      createBody?.data?.bundle_id ?? createBody?.bundle_id ?? createBody?.id ?? 0,
    );
    expect(bundleId, 'the bundle must have an id').toBeGreaterThan(0);

    // Price the bundle below the sum of its parts — the usual reason to sell one.
    const priceRes = await request.post(`/wp-json/wp/v2/sik_course/${bundleId}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { status: 'publish', meta: { _sikshya_price: '45', _sikshya_course_price: '45' } },
    });
    expect(priceRes.status(), 'the bundle price must be writable').toBeLessThan(400);

    const username = slug('bndbuyer');
    const password = 'BndPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);

    // Control: before buying, neither component is accessible.
    for (const [label, id] of [['A', courseA.id], ['B', courseB.id]] as const) {
      const res = await session.request.get(
        `/wp-json/sikshya/v1/me/progress?course_id=${id}&_cb=${Date.now()}-pre${label}`,
        { headers: { 'X-WP-Nonce': session.restNonce, 'Cache-Control': 'no-cache' } },
      );
      expect(
        res.status(),
        `control: component ${label} must be closed before purchase`,
      ).toBeGreaterThanOrEqual(400);
    }

    // --- Buy the bundle through the real cart form on its course page.
    await session.page.goto(`/?p=${bundleId}`, { waitUntil: 'domcontentloaded' });
    const addForm = session.page
      .locator('form input[name="sikshya_cart_action"][value="add"]')
      .first()
      .locator('xpath=..');
    await expect(
      addForm,
      'a priced bundle must offer an add-to-cart form on its own page',
    ).toBeVisible({ timeout: 20_000 });
    await Promise.all([
      session.page.waitForResponse(
        (r) => r.request().method() === 'POST' && r.request().isNavigationRequest(),
        { timeout: 20_000 },
      ),
      addForm.evaluate((f: HTMLFormElement) => f.submit()),
    ]);
    await session.page.waitForLoadState('domcontentloaded');

    const sessionRes = await session.request.post('/wp-json/sikshya/v1/checkout/session', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { gateway: 'offline' },
    });
    const sessionText = await sessionRes.text();
    test.info().attach('bundle-checkout-session', { body: sessionText.slice(0, 900) });
    expect(
      sessionRes.status(),
      `bundle checkout must create an order: ${sessionText.slice(0, 300)}`,
    ).toBeLessThan(400);
    const sBody = JSON.parse(sessionText) as {
      data?: { order_id?: number; public_token?: string };
      order_id?: number;
      public_token?: string;
    };
    const orderId = Number(sBody.order_id ?? sBody.data?.order_id ?? 0);
    const publicToken = String(sBody.public_token ?? sBody.data?.public_token ?? '');
    expect(orderId, 'an order id must come back').toBeGreaterThan(0);

    await session.request.post('/wp-json/sikshya/v1/checkout/confirm', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { order_id: orderId, public_token: publicToken, gateway: 'offline' },
    });

    const markPaid = await request.post(`/wp-json/sikshya/v1/admin/orders/${orderId}/mark-paid`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {},
    });
    expect(markPaid.status(), (await markPaid.text()).slice(0, 250)).toBeLessThan(400);

    // --- The payoff: both component courses must now be open.
    for (const [label, courseId, lesson] of [
      ['A', courseA.id, lessonA],
      ['B', courseB.id, lessonB],
    ] as const) {
      const res = await session.request.get(
        `/wp-json/sikshya/v1/me/progress?course_id=${courseId}&_cb=${Date.now()}-post${label}`,
        { headers: { 'X-WP-Nonce': session.restNonce, 'Cache-Control': 'no-cache' } },
      );
      const body = await res.text();
      test.info().attach(`progress-component-${label}`, { body: body.slice(0, 300) });
      expect(
        res.ok(),
        `buying the bundle must unlock component ${label}; /me/progress said ${res.status()}: ${body.slice(0, 200)}`,
      ).toBeTruthy();

      // And the lesson must render rather than a purchase gate.
      await session.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
      const text = (await session.page.locator('body').innerText()).toLowerCase();
      expect(
        text,
        `component ${label} lesson must be readable after buying the bundle`,
      ).not.toMatch(/buy now|purchase this course/);
    }

    await session.context.close();
  });
});
