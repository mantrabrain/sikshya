import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  getAdminNonce,
} from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('admin: enroll without purchase (admin bypass)', () => {
  test('admin can enroll into a paid course directly from the landing page CTA', async ({
    page,
    request,
  }) => {
    // Seed a PAID course + lesson so the bypass CTA renders (it's only shown
    // when the course price > 0 and the user isn't already enrolled).
    const course = await createCourseViaRest(page, request, {
      title: `E2E admin-bypass course ${Date.now()}`,
      type: 'paid',
      price: 49,
    });
    await createLessonViaRest(page, request, course.id);

    // Visit the course landing page as the admin (storageState provides login).
    const res = await page.goto(`/?p=${course.id}`, { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0, 'GET course landing').toBeLessThan(400);

    // The bypass form should be present.
    const bypassForm = page
      .locator('form input[name="sikshya_cart_action"][value="admin_enroll_bypass"]')
      .first()
      .locator('xpath=..');
    await expect(bypassForm, '"Enroll without purchase" form must render for admin').toBeVisible({
      timeout: 10_000,
    });

    // Submit the form and follow the redirect.
    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      bypassForm.evaluate((f: HTMLFormElement) => f.submit()),
    ]);

    // After admin bypass, the success path lands on the learn shell. If
    // enrollment failed, the page would redirect back with an error flash
    // and the URL would still be the course permalink (containing the
    // sikshya_cart_flash query param). Verify we DID NOT bounce back with
    // an error.
    const finalUrl = page.url();
    expect(
      finalUrl,
      `expected to leave the course landing page; finalUrl=${finalUrl}`,
    ).not.toMatch(/sikshya_cart_flash/);

    // Confirm enrollment row was created by querying the admin enrollments
    // endpoint, filtered to this course. If the bypass failed, the row never
    // gets written and the list is empty.
    const nonce = await getAdminNonce(page);
    const enr = await request.get(
      `/wp-json/sikshya/v1/admin/enrollments?course_id=${course.id}&per_page=10`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    expect(enr.ok(), `GET /admin/enrollments status=${enr.status()}`).toBeTruthy();
    const enrBody = await enr.json().catch(() => ({}));
    const rows: Array<{ course_id?: number }> =
      enrBody?.enrollments ?? enrBody?.data?.enrollments ?? enrBody?.data?.rows ?? [];
    const matched = rows.find((r) => Number(r.course_id) === course.id);
    expect(matched, `enrollment row for course ${course.id} not found`).toBeTruthy();
  });
});
