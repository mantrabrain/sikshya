import { test } from '@playwright/test';
import { expectNoA11yViolations } from '../../../utils/a11y';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * WCAG 2.1 AA gate over every admin screen.
 *
 * This is the companion to `screen-health-sweep.spec.ts`: that one asks whether
 * a screen renders, this one asks whether a person using a keyboard and a
 * screen reader can operate it. Both are deliberately wide and shallow —
 * depth belongs in the per-feature specs.
 *
 * Only Sikshya's own markup is scanned (see `utils/a11y.ts`). WordPress core's
 * admin chrome has violations of its own that we cannot fix and must not be
 * blamed for.
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
  'integrations-hub', 'docs', 'groups', 'cohorts', 'compliance', 'ai-studio',
];

for (const view of ADMIN_VIEWS) {
  test(`admin screen meets WCAG 2.1 AA: ${view}`, async ({ page }) => {
    await page.goto(`/wp-admin/admin.php?page=sikshya&view=${encodeURIComponent(view)}`, {
      waitUntil: 'domcontentloaded',
    });
    await page.waitForLoadState('networkidle').catch(() => undefined);

    /*
     * Scanned by an explicit selector rather than by auto-detection, because
     * every Sikshya admin screen lives inside this one root — and if the
     * session has expired the request lands on `wp-login.php`, where
     * auto-detection would happily find the social-login box, scan four
     * elements and report a clean pass. axe throws when an `include`
     * selector matches nothing, which is the loud failure we want.
     */
    await expectNoA11yViolations(page, `admin view=${view}`, { include: ['#sikshya-admin-root'] });
  });
}
