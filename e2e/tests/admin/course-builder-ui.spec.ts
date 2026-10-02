import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The course builder driven as a UI, not as an endpoint.
 *
 * Every existing builder test posts to `/course-builder/save` directly. That
 * leaves the actual authoring journey — the create dialog, the four step tabs,
 * Save draft vs Publish — unexercised, even though it is the screen a customer
 * spends most of their time in and the one most likely to break on a React
 * refactor.
 *
 * It also pins a correctness property worth having: "Create & continue" makes a
 * DRAFT, and a draft must not be publicly readable until Publish is pressed.
 */
test.describe('admin: course builder UI', () => {
  test('creating, editing and publishing a course through the builder screens', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const stamp = Date.now();
    const title = `E2E Builder UI ${stamp}`;
    const teaser = `TEASER-${stamp}`;

    await page.goto('/wp-admin/admin.php?page=sikshya&view=add-course', {
      waitUntil: 'domcontentloaded',
    });

    // --- Create dialog: pick a format, name it, continue.
    await page.getByRole('button', { name: /create course/i }).first().click();
    await page.getByRole('button', { name: /Regular Course/i }).first().click();
    await page.locator('input[name="title"]').fill(title);
    await page.getByRole('button', { name: /Create & continue/i }).click();

    // --- We should be inside the builder, on a real draft.
    await page.waitForURL(/course_id=\d+/, { timeout: 30_000 });
    const courseId = Number(new URL(page.url()).searchParams.get('course_id'));
    expect(courseId, 'the builder must open on a concrete course id').toBeGreaterThan(0);

    // The URL changes as soon as the draft exists, which is before the builder
    // view has mounted — so wait on the builder's own chrome, not the URL.
    await expect(
      page.getByRole('button', { name: /^Publish$/ }).first(),
      'the builder must finish mounting',
    ).toBeVisible({ timeout: 30_000 });

    // Collect every button label on the screen. Matching by accessible name is
    // ambiguous here (the admin sidebar also has "Settings"), and the step nav
    // and the inner section nav share an aria pattern — so assert against the
    // whole set, which is unambiguous.
    const buttonLabels = await page.evaluate(() =>
      Array.from(document.querySelectorAll('button'))
        .map((el) => (el.textContent || el.getAttribute('aria-label') || '').trim())
        .filter(Boolean),
    );
    test.info().attach('builder-buttons', { body: buttonLabels.join('\n') });
    for (const step of ['Course details', 'Pricing & access', 'Curriculum', 'Course options']) {
      expect(
        buttonLabels.some((l) => l.includes(step)),
        `the "${step}" builder step must be reachable; saw: ${buttonLabels.slice(0, 20).join(' | ')}`,
      ).toBeTruthy();
    }
    for (const action of ['Save draft', 'Publish']) {
      expect(
        buttonLabels.some((l) => l === action || l.startsWith(action)),
        `the builder must offer "${action}"`,
      ).toBeTruthy();
    }

    const nonce = await getAdminNonce(page);
    const draft = await request.get(`/wp-json/wp/v2/sik_course/${courseId}?context=edit`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const draftBody = await draft.json().catch(() => ({}));
    expect(
      String(draftBody?.status ?? ''),
      '"Create & continue" must leave the course as a draft, not publish it',
    ).toBe('draft');

    // A draft must not be readable by the public.
    const anonCtx = await browser.newContext({
      baseURL: baseURL ?? 'http://sikshya.local',
      storageState: { cookies: [], origins: [] },
    });
    const anon = await anonCtx.newPage();
    const draftView = await anon.goto(`/?p=${courseId}`, { waitUntil: 'domcontentloaded' });
    const draftHtml = await anon.content();
    expect(
      draftView?.status() === 404 || !draftHtml.includes(title),
      'an unpublished draft course must not be visible to anonymous visitors',
    ).toBeTruthy();

    // --- Back in the builder: edit a field on the Course step.
    await page.goto(
      `/wp-admin/admin.php?page=sikshya&view=add-course&course_id=${courseId}&tab=course`,
      { waitUntil: 'domcontentloaded' },
    );
    const teaserField = page.getByLabel(/Short teaser/i).first();
    await expect(teaserField).toBeVisible({ timeout: 20_000 });
    await teaserField.fill(teaser);

    // --- Publish from the builder chrome.
    await page.getByRole('button', { name: /^Publish$/ }).first().click();

    // The publish round-trip is what we actually care about: poll the API until
    // the server agrees, rather than trusting a toast.
    await expect
      .poll(
        async () => {
          const res = await request.get(`/wp-json/wp/v2/sik_course/${courseId}?context=edit`, {
            headers: { 'X-WP-Nonce': nonce },
          });
          const body = await res.json().catch(() => ({}));
          return String(body?.status ?? '');
        },
        { timeout: 30_000, message: 'Publish must change the course status server-side' },
      )
      .toBe('publish');

    // --- And the edits must have been persisted, not just the status.
    const saved = await request.get(`/wp-json/wp/v2/sik_course/${courseId}?context=edit`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const savedBody = await saved.json().catch(() => ({}));
    expect(String(savedBody?.title?.raw ?? savedBody?.title?.rendered ?? '')).toContain(
      `Builder UI ${stamp}`,
    );

    // --- Now it must be publicly visible, teaser included.
    const liveView = await anon.goto(`/?p=${courseId}`, { waitUntil: 'domcontentloaded' });
    expect(liveView?.status() ?? 0, 'a published course must load for the public').toBeLessThan(400);
    const liveHtml = await anon.content();
    test.info().attach('published-course-page', { body: liveHtml.slice(0, 2000) });
    expect(liveHtml, 'the published course must render its title').toContain(title);
    expect(
      liveHtml,
      'the teaser edited in the builder must reach the public course page',
    ).toContain(teaser);

    await anonCtx.close();
  });
});
