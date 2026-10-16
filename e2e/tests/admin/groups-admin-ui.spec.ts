import { test, expect } from '@playwright/test';
import { createUserViaRest, getAdminNonce, slug, STUDENT_ROLE } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The Groups admin screen.
 *
 * Groups shipped as a REST API with no screen at all, which meant a site owner
 * could not sell a single seat — the feature was in the catalogue and unusable.
 * This drives the screen the way an administrator would: create a group with a
 * seat count, add a learner by email, and watch the seat counter move.
 *
 * It also asserts the refusal is visible. The API stops a learner beyond the
 * purchased count, and if the screen swallowed that error the administrator
 * would be left wondering why nothing happened.
 */
test.describe('admin: groups and seats screen', () => {
  test('an administrator can create a group, fill its seats, and see the cap enforced', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/groups_seats/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'groups_seats is not included in this licence',
    );

    const stamp = Date.now();
    const password = 'GroupUiPass!2026';
    const first = slug('guia');
    const second = slug('guib');
    for (const u of [first, second]) {
      await createUserViaRest(page, request, {
        username: u,
        email: `${u}@example.com`,
        password,
        role: STUDENT_ROLE,
      });
    }

    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto('/wp-admin/admin.php?page=sikshya&view=groups', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle').catch(() => undefined);

    // The screen must actually render, not fall through to a placeholder.
    await expect(
      page.locator('[data-sikshya-new-group]'),
      'the groups screen must offer a way to create one',
    ).toBeVisible({ timeout: 30_000 });

    // Create a group with exactly one seat, so the cap is reachable in one step.
    await page.locator('[data-sikshya-new-group] button').click();
    await page.locator('[data-sikshya-group-name]').fill(`Acme UI ${stamp}`);
    await page.locator('[data-sikshya-group-seats]').fill('1');
    await page.locator('[data-sikshya-group-save] button').click();

    const row = page.locator('[data-sikshya-groups-table] tr', { hasText: `Acme UI ${stamp}` });
    await expect(row, 'the new group must appear in the table').toBeVisible({ timeout: 30_000 });
    await expect(row, 'a fresh group shows no seats used').toContainText('0 / 1');

    // Fill the single seat from the screen.
    await row.getByRole('button', { name: /Manage roster/i }).click();
    const roster = page.locator('[data-sikshya-group-roster]');
    await expect(roster).toBeVisible();

    await roster.locator('[data-sikshya-member-email]').fill(`${first}@example.com`);
    await roster.locator('[data-sikshya-member-add] button').click();

    await expect(
      roster.locator('[data-sikshya-members-table]'),
      'the learner must appear on the roster',
    ).toContainText(`${first}@example.com`, { timeout: 30_000 });

    // The seat counter on the list must have moved — the figure the customer pays for.
    await expect(row, 'the seat count must reflect the new member').toContainText('1 / 1', {
      timeout: 30_000,
    });

    // The cap: a second learner on one seat must be refused, visibly.
    await roster.locator('[data-sikshya-member-email]').fill(`${second}@example.com`);
    await roster.locator('[data-sikshya-member-add] button').click();

    await expect(
      page.locator('body'),
      'the screen must surface the refusal rather than failing silently',
    ).toContainText(/seat/i, { timeout: 30_000 });

    await expect(
      roster.locator('[data-sikshya-members-table]'),
      'and the refused learner must not be on the roster',
    ).not.toContainText(`${second}@example.com`);

    expect(errors, 'the screen must not throw').toEqual([]);
  });
});
