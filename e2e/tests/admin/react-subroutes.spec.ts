import { test, expect, type ConsoleMessage } from '@playwright/test';

/**
 * Sikshya React admin sub-routes follow `?page=sikshya&view=<name>`.
 *
 * For each view we check:
 *  - URL navigates without server-side error
 *  - React shell (`body.sikshya-react-shell`) mounts
 *  - The lazy-loaded page module finishes loading (suspense fallback gone)
 *  - No `pageerror` or `console.error` events fire (ignoring known noisy
 *    sources like 3rd-party scripts and favicon misses)
 */

const VIEWS_FREE = [
  'dashboard',
  'courses',
  'add-course',
  'lessons',
  'add-lesson',
  'quizzes',
  'coupons',
  'orders',
  'payments',
  'students',
  'instructors',
  'instructor-applications',
  'enrollments',
  'reports',
  'gradebook',
  'course-categories',
  'settings',
  'email',
  'email-templates',
  'tools',
  'addons',
  'integrations',
  'license',
  'activity-log',
  // Added after auditing ReactAdminConfig: every one of these is a real nav
  // destination (`reactAppUrl($id)`) that no spec visited, so a screen could
  // throw on mount and nothing would notice. Several are gated features whose
  // screens must still render an upgrade state rather than erroring.
  'content-library',
  'learning-rules',
  'reviews',
  'discussions',
  'course-team',
  'certificates-hub',
  'assignment-submissions',
  'grading',
  'sales',
  'subscriptions',
  'marketplace',
  'branding',
  'email-hub',
  'integrations-hub',
  'docs',
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
  // Network failures reaching *outside* the site. An admin screen pulls a font
  // or a dashboard widget from a third party, and when the machine running the
  // suite loses connectivity every one of those becomes a console error: one
  // dropped wifi connection turned a clean segment into 19 "product failures"
  // that had nothing to do with the product. A 404 on one of our own assets
  // still fails, because that is a real defect and names a path.
  /net::ERR_INTERNET_DISCONNECTED/i,
  /net::ERR_NAME_NOT_RESOLVED/i,
  /net::ERR_NETWORK_CHANGED/i,
  /net::ERR_CONNECTION_TIMED_OUT/i,
  /NS_ERROR_(?:OFFLINE|UNKNOWN_HOST|NET_TIMEOUT)/i,
];

const shouldIgnore = (msg: string) => IGNORE_PATTERNS.some((re) => re.test(msg));

for (const view of VIEWS_FREE) {
  test(`React subroute view=${view} mounts cleanly`, async ({ page }) => {
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

    // Wait for the route's lazy chunk to settle: the React app puts its main
    // content in #wpbody — once the suspense fallback resolves there is at
    // least one child element with non-trivial text.
    await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => undefined);

    const contentNonEmpty = await page.evaluate(() => {
      const root =
        document.querySelector('#wpbody-content .wrap') ||
        document.querySelector('#wpbody-content') ||
        document.body;
      const t = (root?.textContent ?? '').replace(/\s+/g, '').trim();
      return t.length > 20;
    });
    expect(contentNonEmpty, `content rendered for view=${view}`).toBe(true);

    expect(
      errors.length,
      `no console/page errors for view=${view}:\n${errors.join('\n')}`,
    ).toBe(0);
  });
}
