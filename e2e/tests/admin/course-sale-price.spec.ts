import { test, expect } from '@playwright/test';
import { createCourseViaRest, getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Sale-price discount math + meta roundtrip.
 *
 * `_sikshya_price` and `_sikshya_sale_price` (plus their `_sikshya_course_*`
 * aliases) are registered as `show_in_rest` string metas by
 * `PostTypeManager::registerRestAccessiblePostMeta()`, wired into the boot
 * path from `Plugin::registerServices()`. wp/v2 PATCH on `meta` therefore
 * round-trips. `sikshya_get_course_pricing()` reads price+sale and computes
 * `effective` (sale when sale < price), `on_sale` (true when sale < price),
 * and `discount_percent` (server-side rendered on single-course page).
 */

test.describe('admin: course sale-price discount math', () => {
  test('writing _sikshya_sale_price below _sikshya_price persists both via wp/v2', async ({
    page,
    request,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E sale course ${Date.now()}`,
      type: 'paid',
      price: 100,
    });
    const nonce = await getAdminNonce(page);

    const patch = await request.post(`/wp-json/wp/v2/sik_course/${course.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        meta: {
          _sikshya_sale_price: '50',
          _sikshya_course_sale_price: '50',
        },
      },
    });
    expect(patch.status()).toBeLessThan(400);

    const read = await request.get(`/wp-json/wp/v2/sik_course/${course.id}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const readBody = await read.json();
    const meta = readBody?.meta ?? {};
    expect(String(meta._sikshya_sale_price ?? ''), 'sale_price meta').toBe('50');
    expect(String(meta._sikshya_price ?? ''), 'regular price meta').toBe('100');
    expect(String(meta._sikshya_course_type ?? ''), 'type meta').toBe('paid');
  });
});
