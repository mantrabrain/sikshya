import { test, expect } from '@playwright/test';
import { createCourseViaRest, createLessonViaRest } from '../../utils/factories';

test.describe('admin: lesson CRUD via REST', () => {
  test('admin can attach a sik_lesson to a sik_course', async ({ page, request }) => {
    const course = await createCourseViaRest(page, request);
    const lesson = await createLessonViaRest(page, request, course.id);
    expect(lesson.id).toBeGreaterThan(0);

    await page.goto(`/wp-admin/edit.php?post_type=sik_lesson&s=${encodeURIComponent(lesson.title)}`);
    await expect(page.locator('body')).toContainText(lesson.title);
  });
});
