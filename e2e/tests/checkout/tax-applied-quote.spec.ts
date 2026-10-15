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
import { adminContext } from '../../utils/context';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Tax on a real checkout quote.
 *
 * Settings → Commerce has offered a **Tax rate (%)** field and a **Prices
 * already include tax** switch, and nothing read either one: a store owner could
 * configure 8.25% and the store charged nothing extra. That is the owner's
 * financial and legal exposure, not a cosmetic bug.
 *
 * The test is a three-way contrast, because any single reading could pass for
 * the wrong reason: no tax, exclusive tax, and inclusive tax on the *same* cart.
 * Inclusive is the one that costs money if it is wrong — treating an inclusive
 * price as exclusive bills every customer the tax twice.
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

test.describe('checkout: tax on the quote', () => {
  test('a configured rate reaches the quote, and an inclusive price stays what was advertised', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const stamp = Date.now();

    const course = await createCourseViaRest(page, request, {
      title: `E2E tax course ${stamp}`,
      price: 100,
    });

    const username = slug('taxstud');
    const password = 'TaxPass!2026';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);

    // Remember whatever the tax tab held, and restore it at the end — a dev site
    // left charging 10% tax would quietly break other specs.
    let priorTax: Record<string, unknown> = {};

    try {
      // Fill the server cart through the course page's own form.
      await session.page.goto(course.link, { waitUntil: 'domcontentloaded' });
      const addForm = session.page
        .locator('form input[name="sikshya_cart_action"][value="add"]')
        .first()
        .locator('xpath=..');
      await expect(addForm, 'a paid course must offer an add-to-cart form').toBeVisible({
        timeout: 20_000,
      });
      await Promise.all([
        session.page.waitForResponse(
          (r) => r.request().method() === 'POST' && r.request().isNavigationRequest(),
          { timeout: 20_000 },
        ),
        addForm.evaluate((f: HTMLFormElement) => f.submit()),
      ]);
      await session.page.waitForLoadState('domcontentloaded');

      const quote = async (): Promise<Record<string, unknown>> => {
        const res = await session.request.post('/wp-json/sikshya/v1/checkout/quote', {
          headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
          data: {},
        });
        const body = (await res.json().catch(() => ({}))) as { data?: Record<string, unknown> };
        expect(res.status(), JSON.stringify(body).slice(0, 300)).toBeLessThan(400);

        return body.data ?? {};
      };
      const num = (v: unknown): number =>
        typeof v === 'number' ? v : parseFloat(String(v ?? '0')) || 0;

      // 1. No tax configured — the shipped default. Establishes the baseline so
      //    the later readings are a contrast rather than an absolute.
      // The tax fields live in the `payment` tab — "Pricing & Taxes" is a
      // section within it, not a tab of its own.
      priorTax = await patchSettingsTab(request, nonce, 'payment', {
        tax_rate: 0,
        tax_inclusive: '0',
      });
      const untaxed = await quote();
      test.info().attach('quote-untaxed', { body: JSON.stringify(untaxed).slice(0, 400) });
      const baseTotal = num(untaxed.total);
      expect(baseTotal, 'the baseline cart must have a total').toBeGreaterThan(0);
      expect(num(untaxed.tax), 'no tax must be charged when the rate is zero').toBe(0);

      // 2. Exclusive tax — added on top.
      await patchSettingsTab(request, nonce, 'payment', { tax_rate: 10, tax_inclusive: '0' });
      const exclusive = await quote();
      test.info().attach('quote-exclusive', { body: JSON.stringify(exclusive).slice(0, 400) });
      expect(
        num(exclusive.tax),
        'a configured rate must actually reach the quote — this setting used to do nothing',
      ).toBeCloseTo(baseTotal * 0.1, 2);
      expect(
        num(exclusive.total),
        'exclusive tax is added on top of the cart',
      ).toBeCloseTo(baseTotal * 1.1, 2);

      // 3. Inclusive tax — the total must not move.
      await patchSettingsTab(request, nonce, 'payment', { tax_rate: 10, tax_inclusive: '1' });
      const inclusive = await quote();
      test.info().attach('quote-inclusive', { body: JSON.stringify(inclusive).slice(0, 400) });
      expect(
        num(inclusive.total),
        'an inclusive price is what the customer was advertised and must be what they pay — ' +
          'charging the tax on top as well would bill everyone twice',
      ).toBeCloseTo(baseTotal, 2);
      expect(
        num(inclusive.tax),
        'the tax portion of an inclusive price must still be reported',
      ).toBeGreaterThan(0);
      expect(
        num(inclusive.tax),
        'the inclusive portion is smaller than the exclusive addition on the same cart',
      ).toBeLessThan(num(exclusive.tax));
    } finally {
      if (Object.keys(priorTax).length > 0) {
        await writeSettingsTab(request, nonce, 'payment', priorTax);
      }
      await session.context.close();
    }
  });
});
