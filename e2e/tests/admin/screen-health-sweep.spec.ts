import { test, expect } from '@playwright/test';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * A health sweep over every admin view and the main learner pages.
 *
 * The rest of the suite is API-heavy: it proves endpoints answer correctly, and
 * it proved all of that while a Pro admin screen was returning an empty list
 * from a broken SQL statement and admin requests were logging "headers already
 * sent" seventy times a day. Asserting a 200 is not the same as asserting a
 * screen works.
 *
 * So this checks the things a person would notice, on every screen:
 *
 *  - the page renders and does not 4xx/5xx;
 *  - the browser console reports no errors;
 *  - no PHP notice, warning, fatal or database error is rendered into the page;
 *  - the view actually mounts something, rather than leaving an empty shell.
 *
 * It is deliberately shallow and wide. Depth is what the per-feature specs are
 * for; this is the net that catches a screen nobody thought to test.
 */
const ADMIN_VIEWS = [
  'dashboard', 'courses', 'add-course', 'lessons', 'add-lesson', 'quizzes',
  'coupons', 'orders', 'payments', 'students', 'instructors',
  'instructor-applications', 'enrollments', 'reports', 'gradebook',
  'course-categories', 'settings', 'email', 'email-templates', 'tools',
  'addons', 'integrations', 'license', 'activity-log', 'content-library',
  'learning-rules', 'reviews', 'discussions', 'course-team',
  'certificates-hub', 'assignment-submissions', 'grading', 'sales',
  'subscriptions', 'marketplace', 'branding', 'email-hub',
  'integrations-hub', 'docs',
];

/** Text that should never reach a rendered page. */
const PHP_ERROR_PATTERNS = [
  /PHP (?:Warning|Notice|Fatal error|Deprecated|Parse error)/i,
  /WordPress database error/i,
  /Uncaught (?:Error|Exception|TypeError|ArgumentCountError)/i,
  /Cannot modify header information/i,
  /There has been a critical error on this website/i,
  /wpdb::prepare was called/i,
];

function phpErrorsIn(html: string): string[] {
  const found: string[] = [];
  for (const pattern of PHP_ERROR_PATTERNS) {
    const match = html.match(pattern);
    if (match) {
      const at = html.indexOf(match[0]);
      found.push(html.slice(at, at + 180).replace(/\s+/g, ' '));
    }
  }

  return found;
}

for (const view of ADMIN_VIEWS) {
  test(`admin screen is healthy: ${view}`, async ({ page }) => {
    const consoleErrors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text().slice(0, 200));
      }
    });
    page.on('pageerror', (err) => {
      consoleErrors.push(`uncaught: ${err.message.slice(0, 200)}`);
    });

    const response = await page.goto(
      `/wp-admin/admin.php?page=sikshya&view=${encodeURIComponent(view)}`,
      { waitUntil: 'domcontentloaded' },
    );

    expect(response?.status() ?? 0, `HTTP status for view=${view}`).toBeLessThan(400);

    // Let the React view mount before judging it.
    await page.waitForLoadState('networkidle').catch(() => undefined);

    const html = await page.content();
    const phpErrors = phpErrorsIn(html);
    if (phpErrors.length > 0) {
      test.info().attach(`php-errors-${view}`, { body: phpErrors.join('\n---\n') });
    }
    expect(
      phpErrors,
      `view=${view} rendered a PHP or database error into the page`,
    ).toEqual([]);

    // The shell must contain a mounted view, not an empty container. Judged on
    // rendered text so a blank-but-present root still fails.
    const bodyText = (await page.locator('body').innerText()).trim();
    expect(
      bodyText.length,
      `view=${view} rendered an essentially empty screen (${bodyText.length} chars)`,
    ).toBeGreaterThan(120);

    // Console errors are filtered to things the product controls. Browsers emit
    // noise for blocked third-party requests and favicons that no amount of
    // product correctness removes.
    const ours = consoleErrors.filter(
      (e) =>
        !/favicon|net::ERR_|Failed to load resource|ResizeObserver loop|googleapis|gravatar/i.test(e),
    );
    if (ours.length > 0) {
      test.info().attach(`console-${view}`, { body: ours.join('\n') });
    }
    expect(ours, `view=${view} logged JavaScript errors`).toEqual([]);
  });
}
