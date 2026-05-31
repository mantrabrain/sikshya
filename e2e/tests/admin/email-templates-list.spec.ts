import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: email templates', () => {
  test('GET /admin/email-templates returns the merged template catalog', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    const res = await request.get('/wp-json/sikshya/v1/admin/email-templates', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const body = await res.json().catch(() => ({}));
    expect(res.ok(), JSON.stringify(body).slice(0, 200)).toBeTruthy();
    const templates: { id?: string; key?: string; label?: string }[] = body?.templates ?? [];
    expect(Array.isArray(templates) && templates.length).toBeGreaterThan(0);

    // Pick the first available template, GET it individually, then preview.
    const first = templates[0];
    const id = String(first.id ?? first.key ?? '');
    expect(id).not.toBe('');

    const get = await request.get(`/wp-json/sikshya/v1/admin/email-templates/${id}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const getBody = await get.json().catch(() => ({}));
    expect(get.ok(), JSON.stringify(getBody).slice(0, 200)).toBeTruthy();
    expect(typeof (getBody?.subject ?? getBody?.body ?? getBody?.label) === 'string').toBe(true);

    const preview = await request.post(`/wp-json/sikshya/v1/admin/email-templates/${id}/preview`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {},
    });
    const previewBody = await preview.json().catch(() => ({}));
    expect(preview.status(), JSON.stringify(previewBody).slice(0, 200)).toBeLessThan(400);
  });
});
