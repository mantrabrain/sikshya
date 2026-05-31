import { test, expect } from '@playwright/test';
import { createUserViaRest, getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: sikshya_auditor read-only role', () => {
  test('the sikshya_auditor role is registered and can be assigned via wp/v2/users', async ({
    page,
    request,
  }) => {
    // Confirm the role appears as a valid WP role by attempting to create a
    // user with `roles: ['sikshya_auditor']`. If the role doesn't exist, WP
    // rejects with rest_invalid_param.
    const username = slug('auditor');
    const created = await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password: 'AuditorPass!234',
      role: 'sikshya_auditor',
    });
    expect(created.id, `failed to create auditor user ${username}`).toBeGreaterThan(0);

    // Verify role membership via wp/v2/users/<id>.
    const nonce = await getAdminNonce(page);
    const fetched = await request.get(`/wp-json/wp/v2/users/${created.id}?context=edit`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const body = await fetched.json().catch(() => ({}));
    expect(fetched.ok(), JSON.stringify(body).slice(0, 200)).toBeTruthy();
    // WP exposes `roles` only in context=edit. Confirm the role is present.
    expect(Array.isArray(body?.roles)).toBe(true);
    expect(body.roles).toContain('sikshya_auditor');
  });
});
