import { test, expect } from '@playwright/test';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('public: /sikshya/v1/health endpoint', () => {
  test('GET returns a structured health envelope with status / version / checks', async ({ request }) => {
    const res = await request.get('/wp-json/sikshya/v1/health');
    // Endpoint is public — must not require auth.
    expect(res.status(), 'health endpoint must be reachable without auth').toBeLessThan(400);
    const body = await res.json().catch(() => ({}));
    expect(typeof body?.status === 'string').toBe(true);
    expect(['ok', 'degraded', 'down']).toContain(body.status);
    expect(typeof body?.version === 'string').toBe(true);
    expect(body?.checks?.db === 'ok' || body?.checks?.db === 'down').toBe(true);
    expect(typeof body?.checks?.tables === 'object').toBe(true);
    // Core tables should be present on a healthy install.
    expect(body.checks.tables.enrollments).toBe(true);
    expect(body.checks.tables.progress).toBe(true);
    expect(body.checks.tables.orders).toBe(true);
  });
});
