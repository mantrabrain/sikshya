import { test, expect } from '@playwright/test';
import { createCourseViaRest, createUserViaRest, getAdminNonce, slug, STUDENT_ROLE } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The Cohorts admin screen.
 *
 * Cohorts shipped as a REST API with no screen, so a site owner could not run a
 * single scheduled intake. This drives it the way a training manager would:
 * create a dated intake for a course, add a learner by email, and confirm the
 * learner count on the list moves.
 *
 * The start date is asserted on screen because it is the entire point of the
 * feature — drip schedules count from it, and an intake whose date the screen
 * loses would silently fall back to per-learner drip, which looks like the
 * feature not working at all.
 */
test.describe('admin: cohorts screen', () => {
  test('an administrator can create a dated intake and add a learner to it', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/cohorts/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(enable.status() === 402 || enable.status() === 403, 'cohorts is not included in this licence');

    const stamp = Date.now();
    const course = await createCourseViaRest(page, request, { title: `Cohort UI Course ${stamp}` });

    const learner = slug('cohui');
    await createUserViaRest(page, request, {
      username: learner,
      email: `${learner}@example.com`,
      password: 'CohortUiPass!2026',
      role: STUDENT_ROLE,
    });

    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto('/wp-admin/admin.php?page=sikshya&view=cohorts', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle').catch(() => undefined);

    await expect(
      page.locator('[data-sikshya-new-cohort]'),
      'the cohorts screen must offer a way to create an intake',
    ).toBeVisible({ timeout: 30_000 });

    await page.locator('[data-sikshya-new-cohort] button').click();
    await page.locator('[data-sikshya-cohort-name]').fill(`January UI ${stamp}`);
    await page.locator('[data-sikshya-cohort-course]').fill(String(course.id));
    await page.locator('[data-sikshya-cohort-starts]').fill('2026-02-02');
    await page.locator('[data-sikshya-cohort-save] button').click();

    const row = page.locator('[data-sikshya-cohorts-table] tr', { hasText: `January UI ${stamp}` });
    await expect(row, 'the new intake must appear in the table').toBeVisible({ timeout: 30_000 });

    // The date drives every unlock, so it must survive the round trip.
    await expect(row, 'the intake must show the start date it was given').toContainText('2026-02-02');
    await expect(row, 'and the course it runs').toContainText(`Cohort UI Course ${stamp}`);

    await row.getByRole('button', { name: /Manage learners/i }).click();
    const roster = page.locator('[data-sikshya-cohort-roster]');
    await expect(roster).toBeVisible();

    // The panel must state the anchor, because that is what the manager is
    // promising their learners.
    await expect(roster, 'the roster must state what schedules count from').toContainText('2026-02-02');

    await roster.locator('[data-sikshya-cohort-member-email]').fill(`${learner}@example.com`);
    await roster.locator('[data-sikshya-cohort-member-add] button').click();

    await expect(
      roster.locator('[data-sikshya-cohort-members-table]'),
      'the learner must appear in the intake',
    ).toContainText(`${learner}@example.com`, { timeout: 30_000 });

    // The list count is the figure the manager reads at a glance. Asserted on
    // the cell, not the row — the row also carries a timestamped title full of
    // digits, which would match almost any number.
    await expect(
      row.locator('[data-sikshya-cohort-count]'),
      'the learner count must move',
    ).toHaveText('1', { timeout: 30_000 });

    expect(errors, 'the screen must not throw').toEqual([]);
  });

  test('an undated intake says so, instead of silently anchoring nothing', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/cohorts/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(enable.status() === 402 || enable.status() === 403, 'cohorts is not included in this licence');

    const stamp = Date.now();
    const course = await createCourseViaRest(page, request, { title: `Undated UI Course ${stamp}` });

    await page.goto('/wp-admin/admin.php?page=sikshya&view=cohorts', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-sikshya-new-cohort]')).toBeVisible({ timeout: 30_000 });

    await page.locator('[data-sikshya-new-cohort] button').click();
    await page.locator('[data-sikshya-cohort-name]').fill(`No date UI ${stamp}`);
    await page.locator('[data-sikshya-cohort-course]').fill(String(course.id));
    await page.locator('[data-sikshya-cohort-save] button').click();

    const row = page.locator('[data-sikshya-cohorts-table] tr', { hasText: `No date UI ${stamp}` });
    await expect(row).toBeVisible({ timeout: 30_000 });

    // Without this warning the manager would assume the intake anchors drip and
    // never find out why learners unlock on different days.
    await expect(row, 'a dateless intake must warn that drip falls back per learner').toContainText(
      /not set/i,
    );
  });
});
