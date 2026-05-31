import type { Page } from '@playwright/test';

export const adminPath = (slug: string) => `/wp-admin/${slug.replace(/^\//, '')}`;

export const uniqueSlug = (prefix: string) =>
  `${prefix}-${Date.now()}-${Math.floor(Math.random() * 1e6)}`;

export async function loginAsAdmin(page: Page) {
  const user = process.env.WP_ADMIN_USER ?? 'admin';
  const pass = process.env.WP_ADMIN_PASS ?? 'admin';
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(user);
  await page.locator('#user_pass').fill(pass);
  await page.locator('#wp-submit').click();
  await page.waitForURL(/wp-admin/);
}

export async function logout(page: Page) {
  await page.goto('/wp-login.php?action=logout');
  const confirm = page.locator('a:has-text("log out")');
  if (await confirm.isVisible().catch(() => false)) {
    await confirm.click();
  }
}

export async function dismissWpNotices(page: Page) {
  const dismissers = page.locator('.notice-dismiss');
  const count = await dismissers.count();
  for (let i = 0; i < count; i++) {
    await dismissers.nth(i).click().catch(() => undefined);
  }
}
