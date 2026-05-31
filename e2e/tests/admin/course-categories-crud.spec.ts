import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: course categories CRUD', () => {
  test('admin can create a course category, read it, then delete it', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    const name = `E2E Cat ${slug('cat')}`;
    const create = await request.post('/wp-json/sikshya/v1/taxonomies/course-category', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { name, description: 'auto-generated', slug: '' },
    });
    const createBody = await create.json().catch(() => ({}));
    expect(create.status(), JSON.stringify(createBody).slice(0, 200)).toBeLessThan(400);
    expect(createBody?.success).toBe(true);
    // Response shape: { success, data: { category: { id, name, slug, … } } }
    const termId = Number(
      createBody?.data?.category?.id ??
        createBody?.data?.term_id ??
        createBody?.data?.id ??
        0,
    );
    expect(termId, JSON.stringify(createBody).slice(0, 200)).toBeGreaterThan(0);

    const get = await request.get(`/wp-json/sikshya/v1/taxonomies/course-category/${termId}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const getBody = await get.json().catch(() => ({}));
    expect(get.ok(), JSON.stringify(getBody).slice(0, 200)).toBeTruthy();
    expect(getBody?.success).toBe(true);
    expect(String(getBody?.data?.category?.name ?? '')).toBe(name);

    const del = await request.delete(
      `/wp-json/sikshya/v1/taxonomies/course-category/${termId}`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    expect(del.status()).toBeLessThan(400);
  });
});
