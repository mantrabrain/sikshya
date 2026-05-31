import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/certificates\/advanced/.test(r)),
    'certificates_advanced routes not registered',
  );
});

test.describe('addon: certificates_advanced info + verification surface', () => {
  test('GET /pro/certificates/advanced returns merge fields + verification URL template', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/certificates_advanced/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const res = await request.get('/wp-json/sikshya/v1/pro/certificates/advanced', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const body = await res.json().catch(() => ({}));
    expect(res.status(), JSON.stringify(body).slice(0, 200)).toBeLessThan(400);
    expect(body?.ok).toBe(true);

    // Merge fields are the contract surface for templates — the addon must
    // expose at least the canonical ones the docs promise.
    const merge: string[] = body?.merge_fields ?? [];
    expect(Array.isArray(merge) && merge.length).toBeGreaterThan(0);
    const required = ['{{learner_name}}', '{{course_title}}', '{{certificate_number}}', '{{verification_url}}'];
    for (const tag of required) {
      expect(merge, `merge field ${tag} should be exposed`).toContain(tag);
    }

    // Verification URL template should be a usable URL.
    expect(String(body?.verify_url_template ?? '')).toMatch(/^https?:\/\//);
  });
});
