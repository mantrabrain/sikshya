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

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(!routes.some((r) => /\/pro\/bundles/.test(r)), 'Sikshya Pro bundles routes not registered');
});

test.describe('learner: learn-shell bundle-mode layout', () => {
  test('bundle landing renders cards in an auto-fit row with no account-flavoured CSV/Activity widgets', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/course_bundles/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const childA = await createCourseViaRest(page, request, {
      title: `E2E bundle child A ${Date.now()}`,
      type: 'free',
    });
    const childB = await createCourseViaRest(page, request, {
      title: `E2E bundle child B ${Date.now()}`,
      type: 'free',
    });

    const bundleTitle = `E2E Bundle ${slug('bnd')}`;
    const create = await request.post('/wp-json/sikshya/v1/pro/bundles', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { title: bundleTitle, course_ids: [childA.id, childB.id] },
    });
    const createBody = await create.json().catch(() => ({}));
    expect(create.status(), JSON.stringify(createBody).slice(0, 200)).toBeLessThan(400);
    const bundleId = Number(
      createBody?.data?.bundle_id ?? createBody?.bundle_id ?? createBody?.id ?? 0,
    );
    expect(bundleId).toBeGreaterThan(0);

    // Enroll a learner against the bundle (so the learn shell renders in
    // enrolled-state instead of redirecting back to the bundle landing).
    const username = slug('bdl');
    const password = 'BdlPass!234';
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
    const enroll = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: bundleId },
    });
    // Free bundle enroll may not be allowed; admin manual-enroll is the
    // fallback if the bundle requires payment. Either way, navigate to the
    // learn shell as the student and verify the layout.
    if (enroll.status() >= 400) {
      // Fall back to admin bypass enroll for the student so we can verify
      // the rendered shell. Use the public /me/enroll if it accepts free
      // bundles, otherwise the test still proves the layout for an enrolled
      // viewer because the storageState=admin can directly view /learn/.
      await session.context.close();
      // Use the admin storage-state page to test the layout (admin counts as
      // enrolled-for-all for the bundle hub view, per LearnPageModel).
      const res = await page.goto(`/learn/?course_id=${bundleId}`, {
        waitUntil: 'domcontentloaded',
      });
      expect(res?.status() ?? 0).toBeLessThan(500);
    } else {
      const res = await session.page.goto(`/learn/?course_id=${bundleId}`, {
        waitUntil: 'domcontentloaded',
      });
      expect(res?.status() ?? 0).toBeLessThan(500);
    }
    const targetPage = enroll.status() >= 400 ? page : session.page;

    // Bundle title must render.
    await expect(
      targetPage.locator('h1.sikshya-learnHeader__title', { hasText: bundleTitle }),
    ).toBeVisible({ timeout: 10_000 });

    // Bundle course cards render in the hub grid.
    await expect(targetPage.locator('.sikshya-learnHubGrid')).toHaveCount(1);
    await expect(targetPage.locator('.sikshya-learnHubCard')).toHaveCount(2);

    // The hub grid uses CSS Grid with auto-fit — confirm it's grid layout
    // (not floating divs or stacked rows on desktop).
    const gridDisplay = await targetPage
      .locator('.sikshya-learnHubGrid')
      .evaluate((el) => window.getComputedStyle(el).display);
    expect(gridDisplay).toBe('grid');

    // No CSS export / activity / certificate widgets render on the learn
    // shell — they're filter-gated and the default is off.
    await expect(targetPage.locator('.sikshya-learnContentExtras')).toHaveCount(0);
    expect(await targetPage.locator('body').innerText()).not.toMatch(/Download your data \(CSV\)/i);

    if (enroll.status() < 400) {
      await session.context.close();
    }
  });
});
