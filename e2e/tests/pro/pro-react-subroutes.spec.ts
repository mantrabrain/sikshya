import { test, expect, type ConsoleMessage } from '@playwright/test';

/**
 * Pro-gated React admin views. Same shape as `react-subroutes.spec.ts` but
 * targets the views that only render meaningfully when Sikshya Pro is active.
 * If Pro is not active the whole file is skipped so the suite stays green
 * for users without a Pro install.
 */

const VIEWS_PRO = [
  'subscriptions',
  'bundles',
  'bundle-builder',
  'certificates',
  'issued-certificates',
  'reviews',
  'discussions',
  'prerequisites',
  'content-drip',
  'social-login',
  'white-label',
  'course-team',
  'marketplace',
  'calendar',
  'crm-automation',
  'email-marketing',
  'assignment-submissions',
  'grading',
] as const;

const IGNORE_PATTERNS: RegExp[] = [
  /favicon/i,
  /Failed to load resource.*404.*\.png/i,
  /Tracking Prevention/i,
  /Quirks Mode/i,
  /DevTools failed to load source map/i,
  /Astra/i,
  /wp-emoji/i,
  /third[- ]party/i,
];
const shouldIgnore = (msg: string) => IGNORE_PATTERNS.some((re) => re.test(msg));

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  const hasPro = routes.some((r) => /\/(pro|scale)\//.test(r));
  test.skip(!hasPro, 'Sikshya Pro not active — skipping Pro view smokes');
});

for (const view of VIEWS_PRO) {
  test(`Pro React subroute view=${view} mounts cleanly`, async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => {
      const m = `pageerror: ${e.message}`;
      if (!shouldIgnore(m)) errors.push(m);
    });
    page.on('console', (msg: ConsoleMessage) => {
      if (msg.type() !== 'error') return;
      const m = `console.error: ${msg.text()}`;
      if (!shouldIgnore(m)) errors.push(m);
    });

    const response = await page.goto(
      `/wp-admin/admin.php?page=sikshya&view=${encodeURIComponent(view)}`,
      { waitUntil: 'domcontentloaded', timeout: 30_000 },
    );
    expect(response?.status() ?? 0, `HTTP for view=${view}`).toBeLessThan(500);

    const shell = page.locator('body.sikshya-react-shell, #sikshya-admin-app, [data-sikshya-admin-app]').first();
    await expect(shell, `shell mount for view=${view}`).toBeVisible({ timeout: 20_000 });

    await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => undefined);

    const contentNonEmpty = await page.evaluate(() => {
      const root =
        document.querySelector('#wpbody-content .wrap') ||
        document.querySelector('#wpbody-content') ||
        document.body;
      return ((root?.textContent ?? '').replace(/\s+/g, '').trim().length) > 20;
    });
    expect(contentNonEmpty, `content rendered for view=${view}`).toBe(true);

    expect(
      errors.length,
      `no console/page errors for view=${view}:\n${errors.join('\n')}`,
    ).toBe(0);
  });
}
