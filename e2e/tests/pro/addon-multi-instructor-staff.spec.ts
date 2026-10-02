import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  getAdminNonce,
  loginAs,
  slug,
  INSTRUCTOR_ROLE,
} from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Course staff: who may add a co-instructor, and to whose course.
 *
 * `CourseStaffPolicy::canEditCourseStaff()` decides this, and it now routes through
 * the shared ownership helper. Two things therefore need pinning: the course owner
 * can add staff to a course they published (the case that used to fail outright,
 * because the old `edit_post` check is false for an instructor's own published
 * course), and an unrelated instructor cannot add themselves — or anyone — as staff
 * on somebody else's course, which would be a self-service route into another
 * instructor's content and revenue share.
 */
const STAFF = '/wp-json/sikshya/v1/pro/multi-instructor/course-staff';

async function restNonceFor(page: import('@playwright/test').Page): Promise<string> {
  await page.goto('/wp-admin/profile.php', { waitUntil: 'domcontentloaded' });
  return page.evaluate(() => {
    const w = window as unknown as { wpApiSettings?: { nonce?: string } };
    return w.wpApiSettings?.nonce ?? '';
  });
}

test.describe('addon: multi-instructor course staff', () => {
  test('an owner can add staff to their own published course; a stranger cannot', async ({
    page,
    request,
    browser,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post(
      '/wp-json/sikshya/v1/admin/addons/multi_instructor/enable',
      { headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' } },
    );
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'multi_instructor is not included in this licence',
    );

    const password = 'StaffPass!234';
    const ownerName = slug('owner');
    const strangerName = slug('stranger');
    const coName = slug('coteach');

    const owner = await createUserViaRest(page, request, {
      username: ownerName,
      email: `${ownerName}@example.com`,
      password,
      role: INSTRUCTOR_ROLE,
    });
    await createUserViaRest(page, request, {
      username: strangerName,
      email: `${strangerName}@example.com`,
      password,
      role: INSTRUCTOR_ROLE,
    });
    const coTeacher = await createUserViaRest(page, request, {
      username: coName,
      email: `${coName}@example.com`,
      password,
      role: INSTRUCTOR_ROLE,
    });

    // Published on purpose: this is the state where the previous capability check
    // refused the course's own author.
    // --- The owner creates their own course.
    //
    // The author cannot be assigned through REST: `sik_course` does not declare
    // `supports: 'author'`, so wp/v2 silently ignores an `author` field (the
    // factory's `authorId` option has no effect here, and a course created by the
    // admin stays owned by the admin — which quietly turns an ownership test into a
    // no-op). The realistic path is the one used here: the instructor creates it,
    // so WordPress sets them as the author.
    const ownerCtx = await browser.newContext();
    const ownerPage = await loginAs(ownerCtx, ownerName, password);
    const ownerNonce = await restNonceFor(ownerPage);
    expect(ownerNonce, 'the owner must obtain a REST nonce').toBeTruthy();

    // wp/v2 cannot be used for this either: publishing a `sik_course` there needs
    // `publish_posts` (the post type registers `capability_type => 'post'`), and the
    // instructor role holds `publish_sikshya_courses` instead — so wp/v2 answers
    // `rest_cannot_publish`. Instructor authoring works through Sikshya's own
    // course-builder endpoints, which is what the admin UI uses.
    const draft = await ownerCtx.request.post('/wp-json/sikshya/v1/course-builder/create-draft', {
      headers: { 'X-WP-Nonce': ownerNonce, 'Content-Type': 'application/json' },
      data: { title: `E2E staff course ${Date.now()}` },
    });
    const draftText = await draft.text();
    expect(
      draft.status(),
      `the instructor must be able to create a course draft: ${draftText.slice(0, 250)}`,
    ).toBeLessThan(400);
    const draftBody = JSON.parse(draftText) as Record<string, unknown>;
    const courseId = Number(
      draftBody.course_id ??
        (draftBody.data as Record<string, unknown>)?.course_id ??
        (draftBody.data as Record<string, unknown>)?.id ??
        draftBody.id ??
        0,
    );
    expect(courseId, `the draft must have an id: ${draftText.slice(0, 250)}`).toBeGreaterThan(0);

    const publish = await ownerCtx.request.post('/wp-json/sikshya/v1/course-builder/save', {
      headers: { 'X-WP-Nonce': ownerNonce, 'Content-Type': 'application/json' },
      data: {
        course_id: courseId,
        title: `E2E staff course ${Date.now()}`,
        description: 'Owned by the instructor under test.',
        course_status: 'publish',
      },
    });
    expect(
      publish.status(),
      `the instructor must be able to publish their own course: ${(await publish.text()).slice(0, 250)}`,
    ).toBeLessThan(400);

    const course = { id: courseId };

    // Authorship cannot be asserted through REST: because `sik_course` does not
    // declare `supports: 'author'`, wp/v2 omits the field from the response as well
    // as ignoring it on write. The course was created by the instructor's own
    // session, so WordPress set them as the author; the behavioural check below —
    // they can manage staff on it, a stranger cannot — is what actually matters and
    // would fail if ownership had not landed.

    const add = await ownerCtx.request.post(STAFF, {
      headers: { 'X-WP-Nonce': ownerNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, user_id: coTeacher.id, role: 'co_instructor', revenue_share: 25 },
    });
    const addText = await add.text();
    test.info().attach('owner-add-staff', { body: `${add.status()} ${addText.slice(0, 300)}` });
    expect(
      add.status(),
      `the course owner must be able to add staff to their own published course; ` +
        `got ${add.status()}: ${addText.slice(0, 200)}`,
    ).toBeLessThan(400);

    // And the co-instructor must show up on the course.
    const list = await ownerCtx.request.get(`${STAFF}?course_id=${course.id}`, {
      headers: { 'X-WP-Nonce': ownerNonce },
    });
    const listText = await list.text();
    expect(list.ok(), `listing staff failed: ${listText.slice(0, 200)}`).toBeTruthy();
    expect(
      listText,
      'the added co-instructor must appear in the course staff list',
    ).toContain(String(coTeacher.id));
    await ownerCtx.close();

    // --- An unrelated instructor must not be able to add staff to that course.
    const strangerCtx = await browser.newContext();
    const strangerPage = await loginAs(strangerCtx, strangerName, password);
    const strangerNonce = await restNonceFor(strangerPage);

    const intrude = await strangerCtx.request.post(STAFF, {
      headers: { 'X-WP-Nonce': strangerNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, user_id: coTeacher.id, role: 'co_instructor', revenue_share: 90 },
    });
    const intrudeText = await intrude.text();
    test.info().attach('stranger-add-staff', {
      body: `${intrude.status()} ${intrudeText.slice(0, 300)}`,
    });
    expect(
      intrude.status(),
      `an unrelated instructor must not add staff to another instructor's course — that is a ` +
        `self-service route into their content and revenue share (got ${intrude.status()}: ` +
        `${intrudeText.slice(0, 200)})`,
    ).toBe(403);

    await strangerCtx.close();
  });
});
