import { test, expect } from '@playwright/test';
import { expectNoA11yViolations } from '../../../utils/a11y';

/**
 * WCAG 2.1 AA gate over the pages a learner actually uses.
 *
 * Driven entirely by navigation, with no REST fixtures: these pages have to be
 * reachable by clicking, so if a link is missing the spec fails rather than
 * quietly skipping. Only Sikshya's own markup is scanned — the surrounding
 * theme is the site owner's, and a plugin cannot be held to its contrast
 * choices.
 */

test('course archive meets WCAG 2.1 AA', async ({ page }) => {
  await page.goto('/?post_type=sik_course', { waitUntil: 'domcontentloaded' });
  await expectNoA11yViolations(page, 'course archive');
});

test('single course page meets WCAG 2.1 AA', async ({ page }) => {
  await page.goto('/?post_type=sik_course', { waitUntil: 'domcontentloaded' });

  const firstCourse = page.locator('a[href*="/courses/"], .sikshya-course-card a').first();
  await expect(firstCourse, 'the archive should link to at least one course').toBeVisible();
  await firstCourse.click();
  await page.waitForLoadState('domcontentloaded');

  await expectNoA11yViolations(page, `single course (${page.url()})`);
});

for (const [label, path] of [
  ['cart', '/cart/'],
  ['checkout', '/checkout/'],
  ['account dashboard', '/my-learning/'],
  ['account learning list', '/my-learning/learning/'],
  ['account quiz attempts', '/my-learning/quiz-attempts/'],
  ['account profile', '/my-learning/profile/'],
  ['account certificates', '/my-learning/certificates/'],
  ['account payments', '/my-learning/payments/'],
  ['learn shell', '/learn/'],
] as const) {
  test(`${label} meets WCAG 2.1 AA`, async ({ page }) => {
    await page.goto(path, { waitUntil: 'domcontentloaded' });
    await expectNoA11yViolations(page, label);
  });
}

test('registration form meets WCAG 2.1 AA', async ({ page }) => {
  await page.goto('/wp-login.php?action=register', { waitUntil: 'domcontentloaded' });
  await expectNoA11yViolations(page, 'registration');
});

test('a lesson inside the learn shell meets WCAG 2.1 AA', async ({ page }) => {
  await page.goto('/learn/', { waitUntil: 'domcontentloaded' });

  // Reached by clicking, like a learner would, rather than by building a
  // fixture: the shell is only worth scanning with real content in it.
  const lesson = page.locator('a[href*="/learn/lessons/"]').first();
  await expect(lesson, 'the learn shell should link to at least one lesson').toBeVisible();
  await lesson.click();
  await page.waitForLoadState('domcontentloaded');
  // Pro add-ons inject their own tabs into the learner chrome and finish
  // wiring them up after load, so scanning any earlier reads a half-built
  // ARIA tree and reports it as the product's.
  await page.waitForLoadState('networkidle').catch(() => undefined);

  await expectNoA11yViolations(page, `learn lesson (${page.url()})`);
});
