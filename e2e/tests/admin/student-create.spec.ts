import { test, expect } from '@playwright/test';
import { createUserViaRest, STUDENT_ROLE, slug } from '../../utils/factories';

test.describe('admin: student user creation', () => {
  test('admin can create a sikshya_student user via REST', async ({ page, request }) => {
    const username = slug('stud');
    const result = await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password: 'TestPass!234',
      role: STUDENT_ROLE,
    });
    expect(result).toBeDefined();

    await page.goto('/wp-admin/users.php?s=' + encodeURIComponent(username));
    await expect(page.locator('body')).toContainText(username);
  });
});
