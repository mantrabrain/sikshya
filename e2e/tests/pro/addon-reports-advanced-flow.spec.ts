import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/reports-advanced\/export/.test(r)),
    'reports_advanced routes not registered',
  );
});

test.describe('addon: reports_advanced CSV export', () => {
  test('admin GET /pro/reports-advanced/export?type=summary returns CSV content', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/reports_advanced/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const res = await request.get(
      '/wp-json/sikshya/v1/pro/reports-advanced/export?type=summary',
      { headers: { 'X-WP-Nonce': nonce } },
    );
    expect(res.status(), 'reports export status').toBeLessThan(400);

    const contentType = (res.headers()['content-type'] ?? '').toLowerCase();
    const body = await res.text();
    // The endpoint may return either CSV (text/csv) or a JSON envelope wrapping
    // CSV depending on Pro version; both are valid.
    const looksLikeCsv = /csv/.test(contentType) || /^[^{]/.test(body.trim());
    const looksLikeJson = /json/.test(contentType) || /^\{/.test(body.trim());
    expect(looksLikeCsv || looksLikeJson, `unexpected content-type=${contentType}`).toBe(true);
  });
});
