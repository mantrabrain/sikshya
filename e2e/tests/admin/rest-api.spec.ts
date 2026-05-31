import { test, expect } from '@playwright/test';

test.describe('admin: Sikshya REST API surface', () => {
  test('/wp-json discovery exposes a sikshya namespace', async ({ request }) => {
    const res = await request.get('/wp-json/');
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    const namespaces: string[] = Array.isArray(body?.namespaces) ? body.namespaces : [];
    const hasSikshya = namespaces.some((n) => /sikshya/i.test(n));
    expect(hasSikshya, `namespaces=${JSON.stringify(namespaces)}`).toBe(true);
  });

  test('GET sik_course REST collection returns array', async ({ request }) => {
    const res = await request.get('/wp-json/wp/v2/sik_course');
    expect([200, 401, 403]).toContain(res.status());
    if (res.ok()) {
      const json = await res.json();
      expect(Array.isArray(json)).toBe(true);
    }
  });
});
