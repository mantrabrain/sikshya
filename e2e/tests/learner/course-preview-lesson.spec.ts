import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  getAdminNonce,
} from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('public: course landing page preview lesson affordance', () => {
  test('a course with a previewable lesson surfaces a "Free preview" pill + hero CTA to logged-out visitors', async ({
    page,
    request,
  }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E preview course ${Date.now()}`,
      type: 'paid',
      price: 19,
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    // Mark the lesson as previewable by setting _sikshya_is_free=1.
    const nonce = await getAdminNonce(page);
    const meta = await request.post(`/wp-json/wp/v2/sik_lesson/${lesson.id}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { meta: { _sikshya_is_free: '1' } },
    });
    expect(meta.ok(), `failed to set _sikshya_is_free: ${meta.status()}`).toBeTruthy();

    // Visit the course landing page as a logged-out visitor.
    const anon = await page.context().browser()!.newContext();
    const anonPage = await anon.newPage();
    const res = await anonPage.goto(`/?p=${course.id}`, { waitUntil: 'domcontentloaded' });
    expect(res?.status() ?? 0).toBeLessThan(400);

    // "Free preview" pill should be rendered into the curriculum outline (the
    // chapter accordion may be collapsed initially — assert presence, not
    // viewport visibility).
    await expect(anonPage.locator('.sikshya-course-lp__pill--preview')).toHaveCount(1);

    // The hero "Watch a free preview" CTA sits in the buy block and should be
    // visible without expanding any accordion.
    await expect(anonPage.locator('.sikshya-course-lp__preview-link')).toBeVisible();
    await expect(anonPage.locator('.sikshya-course-lp__preview-link')).toContainText(/free preview/i);

    await anon.close();
  });
});
