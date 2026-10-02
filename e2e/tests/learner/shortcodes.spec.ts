import { test, expect } from '@playwright/test';
import { createCourseViaRest, getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The three registered shortcodes had no coverage.
 *
 * They are how the plugin drops its UI into a page built with the block editor or
 * a page builder, so the failure that matters is a shortcode that does not
 * execute: WordPress then prints the raw `[sikshya_…]` text to visitors. Every
 * case below therefore asserts both that the expected markup appeared AND that
 * the literal shortcode did not survive into the output.
 */
const SHORTCODES = [
  {
    tag: 'sikshya_courses',
    // The catalogue grid; a published course title proves it queried and rendered.
    expect: (html: string, courseTitle: string) => html.includes(courseTitle),
    describe: 'renders the course catalogue',
  },
  {
    tag: 'sikshya_login',
    expect: (html: string) => /name=["']log["']|name=["']username["']|type=["']password["']/.test(html),
    describe: 'renders a login form',
  },
  {
    tag: 'sikshya_registration',
    expect: (html: string) =>
      /name=["']user_login["']|name=["']username["']|name=["']email["']|type=["']password["']/.test(html),
    describe: 'renders a registration form',
  },
];

test.describe('public: shortcodes', () => {
  for (const sc of SHORTCODES) {
    test(`[${sc.tag}] ${sc.describe} and does not leak its own tag`, async ({
      page,
      request,
      browser,
      baseURL,
    }) => {
      const nonce = await getAdminNonce(page);

      // A course so the catalogue shortcode has something to show.
      const course = await createCourseViaRest(page, request, {
        title: `E2E shortcode ${slug('sc')}`,
        type: 'free',
      });

      const pageRes = await request.post('/wp-json/wp/v2/pages', {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: {
          title: `E2E shortcode host ${sc.tag} ${Date.now()}`,
          status: 'publish',
          content: `<p>before</p>[${sc.tag}]<p>after</p>`,
        },
      });
      const pageBody = await pageRes.json().catch(() => ({}));
      expect(pageRes.status(), JSON.stringify(pageBody).slice(0, 200)).toBeLessThan(400);
      const link = String(pageBody?.link ?? '');
      expect(link, 'the host page must have a permalink').toBeTruthy();

      // View as an anonymous visitor — the realistic audience for these.
      const ctx = await browser.newContext({
        baseURL: baseURL ?? 'http://sikshya.local',
        storageState: { cookies: [], origins: [] },
      });
      const anon = await ctx.newPage();
      const res = await anon.goto(link, { waitUntil: 'domcontentloaded' });
      expect(res?.status() ?? 0, 'the host page must load').toBeLessThan(400);

      const html = await anon.content();
      test.info().attach(`${sc.tag}-render`, { body: html.slice(0, 3000) });

      expect(
        html,
        `the raw [${sc.tag}] tag reached the page, so the shortcode never executed`,
      ).not.toContain(`[${sc.tag}]`);
      expect(html, 'the surrounding page content must still render').toContain('before');
      expect(
        sc.expect(html, course.title),
        `[${sc.tag}] did not render the expected markup`,
      ).toBeTruthy();

      await ctx.close();
    });
  }
});
