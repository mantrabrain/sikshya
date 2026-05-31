import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.describe('admin: settings save round-trip', () => {
  test('admin can toggle enable_offline_payment via /settings/save', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);

    const readRes = await request.get('/wp-json/sikshya/v1/settings/values?tab=payment', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const readBody = await readRes.json().catch(() => ({}));
    expect(readRes.ok(), JSON.stringify(readBody)).toBeTruthy();
    const before = String(readBody?.data?.values?.enable_offline_payment ?? '0');

    const target = before === '1' ? '0' : '1';
    const saveRes = await request.post('/wp-json/sikshya/v1/settings/save', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { tab: 'payment', values: { enable_offline_payment: target } },
    });
    const saveBody = await saveRes.json().catch(() => ({}));
    expect(saveRes.ok(), JSON.stringify(saveBody)).toBeTruthy();
    expect(String(saveBody?.data?.values?.enable_offline_payment ?? '')).toBe(target);

    // Restore so we don't leave the dev DB toggled.
    await request.post('/wp-json/sikshya/v1/settings/save', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { tab: 'payment', values: { enable_offline_payment: before } },
    });
  });
});
