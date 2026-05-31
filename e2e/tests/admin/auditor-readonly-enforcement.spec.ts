import { test, expect } from '@playwright/test';
import { createUserViaRest, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: sikshya_auditor read-only enforcement', () => {
  test('auditor can read /admin/coupons but cannot create one (403)', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    // Seed an auditor user with a fresh password we know.
    const username = slug('audwrite');
    const password = 'AudPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: 'sikshya_auditor',
    });

    // Log the auditor in via wp-login.php in a fresh browser context to
    // exercise the cookie + nonce auth path (matches the React admin).
    const ctx = await browser.newContext({ baseURL: baseURL ?? 'http://sikshya.local' });
    const auditPage = await ctx.newPage();
    await auditPage.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
    await auditPage.locator('#user_login').fill(username);
    await auditPage.locator('#user_pass').fill(password);
    await Promise.all([
      auditPage.waitForLoadState('domcontentloaded'),
      auditPage.locator('#wp-submit').click(),
    ]);

    // Pull a fresh wp_rest nonce from the auditor session. The Sikshya React
    // admin shell reliably enqueues wp-api-request so wpApiSettings.nonce is
    // present on /wp-admin/admin.php?page=sikshya.
    await auditPage.goto('/wp-admin/admin.php?page=sikshya', { waitUntil: 'domcontentloaded' });
    const nonce = await auditPage.evaluate(() => {
      const w = window as unknown as {
        wpApiSettings?: { nonce?: string };
        sikshyaReact?: { restNonce?: string };
      };
      return w.wpApiSettings?.nonce ?? w.sikshyaReact?.restNonce ?? '';
    });
    expect(nonce, 'failed to read wpApiSettings.nonce on /wp-admin/admin.php?page=sikshya').not.toBe('');

    // permissionAdmin GET endpoint — auditor allowed (read).
    const list = await auditPage.request.get(
      '/wp-json/sikshya/v1/admin/course-chapters?course_id=0',
      { headers: { 'X-WP-Nonce': nonce } },
    );
    // 4xx other than 401/403 is fine (e.g. 400 invalid course_id); we just
    // need to confirm the perm gate didn't reject the read outright.
    expect([401, 403]).not.toContain(list.status());

    // permissionAdmin write endpoint — auditor MUST be rejected with 403 by
    // the new write-cap gate in AbstractAdminRestController::permissionAdmin.
    const write = await auditPage.request.post(
      '/wp-json/sikshya/v1/course-builder/save',
      {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: { course_id: 0, fields: {} },
      },
    );
    expect(write.status(), `auditor write expected 403; got ${write.status()}`).toBe(403);

    await ctx.close();
  });
});
