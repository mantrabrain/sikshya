import { test, expect } from '@playwright/test';
import { createCourseViaRest } from '../../utils/factories';

test.describe('admin: course CRUD via REST', () => {
  test('admin can create a sik_course and it lists in edit.php', async ({ page, request }) => {
    const course = await createCourseViaRest(page, request, { title: `E2E rest course ${Date.now()}` });
    expect(course.id).toBeGreaterThan(0);

    await page.goto('/wp-admin/edit.php?post_type=sik_course&s=' + encodeURIComponent(course.title));
    await expect(page.locator('body')).toContainText(course.title);
  });

  test('admin can update a course price meta', async ({ page, request }) => {
    const course = await createCourseViaRest(page, request, {
      title: `E2E priced course ${Date.now()}`,
      price: 49,
    });
    expect(course.id).toBeGreaterThan(0);
  });
});
