import { test, expect } from '@playwright/test';
import { createCourseViaRest, getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: global search endpoint', () => {
  test('GET /admin/search?q=<title> returns a course match in the courses bucket', async ({
    page,
    request,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E global-search course ${Date.now()}`,
      type: 'free',
    });

    const nonce = await getAdminNonce(page);
    // Use a distinctive token from the course title.
    const q = `global-search`;
    const res = await request.get(`/wp-json/sikshya/v1/admin/search?q=${encodeURIComponent(q)}&limit=5`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const body = await res.json().catch(() => ({}));
    expect(res.ok(), JSON.stringify(body).slice(0, 200)).toBeTruthy();
    expect(body?.ok).toBe(true);
    expect(Array.isArray(body?.results?.courses)).toBe(true);
    const rows: Array<{ id: number; url: string }> = body?.results?.courses ?? [];
    const matched = rows.find((c) => Number(c.id) === course.id);
    expect(matched, `course ${course.id} should appear in results`).toBeTruthy();

    // REGRESSION: the search result URL must route to the Sikshya React
    // admin (page=sikshya), NOT to wp-admin's core post.php editor.
    expect(matched?.url, `course URL must route to Sikshya admin; got ${matched?.url}`).toMatch(
      /admin\.php\?page=sikshya/,
    );
    expect(matched?.url).not.toMatch(/post\.php\?/);

    // User-bucket URLs must also route to the Sikshya React admin.
    const userRows: Array<{ url: string }> = body?.results?.users ?? [];
    for (const u of userRows) {
      expect(u.url, `user URL must route to Sikshya admin; got ${u.url}`).toMatch(
        /admin\.php\?page=sikshya/,
      );
      expect(u.url).not.toMatch(/user-edit\.php/);
    }
  });

  test('GET /admin/search with empty q returns ok envelope with empty buckets', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);
    const res = await request.get('/wp-json/sikshya/v1/admin/search?q=', { headers: { 'X-WP-Nonce': nonce } });
    const body = await res.json().catch(() => ({}));
    expect(res.ok(), JSON.stringify(body).slice(0, 200)).toBeTruthy();
    expect(body?.ok).toBe(true);
    expect(body?.results?.courses?.length ?? -1).toBe(0);
    expect(body?.results?.users?.length ?? -1).toBe(0);
    expect(body?.results?.orders?.length ?? -1).toBe(0);
  });
});
