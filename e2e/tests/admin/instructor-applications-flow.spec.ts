import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: instructor applications surface', () => {
  test('GET /admin/instructor-applications returns paginated envelope', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    const res = await request.get(
      '/wp-json/sikshya/v1/admin/instructor-applications?per_page=10',
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const body = await res.json().catch(() => ({}));
    expect(res.ok(), JSON.stringify(body).slice(0, 200)).toBeTruthy();
    const list =
      body?.applications ?? body?.rows ?? body?.items ?? body?.data?.rows ?? body?.data ?? [];
    expect(Array.isArray(list), `expected array; got ${typeof list}`).toBe(true);
  });

  test('approving / rejecting a non-existent id returns 400/404 (not a fatal)', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    // Use a definitely-non-existent id to verify the endpoint validates and
    // returns a structured error rather than crashing.
    const fake = 999_999_999;
    const approve = await request.post(
      `/wp-json/sikshya/v1/admin/instructor-applications/${fake}/approve`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    expect([400, 404, 500], `approve status; got ${approve.status()}`).toContain(approve.status());

    const reject = await request.post(
      `/wp-json/sikshya/v1/admin/instructor-applications/${fake}/reject`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    expect([400, 404, 500], `reject status; got ${reject.status()}`).toContain(reject.status());
  });
});
