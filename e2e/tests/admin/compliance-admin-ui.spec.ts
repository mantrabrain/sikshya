import { test, expect } from '@playwright/test';
import { createCourseViaRest, createUserViaRest, getAdminNonce, slug, STUDENT_ROLE } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The Compliance training admin screen.
 *
 * Compliance shipped as a REST API with no screen, so the reports that are the
 * entire reason to buy the addon could not be read anywhere. This drives it as a
 * compliance officer would: mandate a course for a role, confirm learners were
 * assigned immediately, and read the figures back off the screen.
 *
 * The assignment count is asserted because a requirement that assigns nobody
 * reports 100% compliant with zero learners — the most misleading number the
 * product can produce, and indistinguishable from real compliance unless the
 * screen shows the denominator.
 */
test.describe('admin: compliance training screen', () => {
  test('an administrator can mandate a course for a role and read the figures back', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/compliance_training/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'compliance_training is not included in this licence',
    );

    const stamp = Date.now();
    const course = await createCourseViaRest(page, request, { title: `Compliance UI Course ${stamp}` });

    // A learner in the audience, so the requirement has somebody to assign to.
    const learner = slug('cmpui');
    await createUserViaRest(page, request, {
      username: learner,
      email: `${learner}@example.com`,
      password: 'ComplianceUiPass!2026',
      role: STUDENT_ROLE,
    });

    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto('/wp-admin/admin.php?page=sikshya&view=compliance', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle').catch(() => undefined);

    await expect(
      page.locator('[data-sikshya-new-requirement]'),
      'the compliance screen must offer a way to add a requirement',
    ).toBeVisible({ timeout: 30_000 });

    // The headline figures must render before anything is created — an empty
    // report is a legitimate state, not an error.
    await expect(page.locator('[data-sikshya-compliance-overall]')).toBeVisible();
    await expect(page.locator('[data-sikshya-stat-overdue]')).toBeVisible();

    await page.locator('[data-sikshya-new-requirement] button').click();
    await page.locator('[data-sikshya-requirement-course]').fill(String(course.id));
    await page.locator('[data-sikshya-requirement-title]').fill(`Annual safety ${stamp}`);
    await page.locator('[data-sikshya-requirement-audience]').selectOption('role');
    await page.locator('[data-sikshya-requirement-audience-key]').fill(STUDENT_ROLE);
    await page.locator('[data-sikshya-requirement-due-days]').fill('30');
    await page.locator('[data-sikshya-requirement-recurs]').fill('365');
    await page.locator('[data-sikshya-requirement-save] button').click();

    const row = page.locator('[data-sikshya-requirements-table] tr', { hasText: `Annual safety ${stamp}` });
    await expect(row, 'the requirement must appear in the table').toBeVisible({ timeout: 30_000 });

    await expect(row, 'the audience must be shown, not just stored').toContainText(STUDENT_ROLE);
    await expect(row, 'the deadline must be shown').toContainText('30');
    await expect(row, 'and the renewal period').toContainText('365');

    // The denominator: learners were actually assigned. Without this the
    // percentage is meaningless.
    await expect(row, 'the requirement must report learners assigned to it').toContainText(/\/\s*[1-9]/, {
      timeout: 30_000,
    });

    // Re-checking the audience must work from the screen — new hires are the
    // normal case, and a requirement that never picks them up is a silent gap.
    await row.locator('[data-sikshya-requirement-sync] button').click();
    await expect(page.locator('body')).toContainText(/assigned|re-checked/i, { timeout: 30_000 });

    expect(errors, 'the screen must not throw').toEqual([]);
  });

  test('a requirement naming a role but no role slug is refused, visibly', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/compliance_training/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'compliance_training is not included in this licence',
    );

    const course = await createCourseViaRest(page, request, { title: `Compliance guard ${Date.now()}` });

    await page.goto('/wp-admin/admin.php?page=sikshya&view=compliance', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-sikshya-new-requirement]')).toBeVisible({ timeout: 30_000 });

    await page.locator('[data-sikshya-new-requirement] button').click();
    await page.locator('[data-sikshya-requirement-course]').fill(String(course.id));
    await page.locator('[data-sikshya-requirement-audience]').selectOption('role');
    await page.locator('[data-sikshya-requirement-save] button').click();

    // An audience that names nothing would assign to nobody and report 100%
    // compliant. The screen must stop it rather than store it.
    await expect(page.locator('body'), 'the refusal must be on screen').toContainText(/role/i, {
      timeout: 30_000,
    });
  });
});
