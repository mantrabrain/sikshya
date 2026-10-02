import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  createUserViaRest,
  getAdminNonce,
  learnUrl,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Free content features that had no coverage: course FAQ and announcements.
 *
 * Both are `repeater_group` fields on the course builder's Course Info tab, and
 * neither meta key is registered for REST — so they are written through
 * `/course-builder/save`, which is also the path the admin UI uses. That makes
 * this a round-trip of the real authoring flow rather than a meta poke: author in
 * the builder, read as a learner on the front end.
 *
 * Announcements are explicitly described as "shown to enrolled learners", so the
 * spec also checks they are not published to the world.
 */
test.describe('learner: course FAQ and announcements', () => {
  test('FAQ and announcements authored in the builder reach the right audience', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);

    const stamp = Date.now();
    const faqQuestion = `FAQ-Q-${stamp}`;
    const faqAnswer = `FAQ-A-${stamp}`;
    const annTitle = `ANN-T-${stamp}`;

    const course = await createCourseViaRest(page, request, {
      title: `E2E extras ${stamp}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    // Author through the builder, exactly as the admin UI does.
    const save = await request.post('/wp-json/sikshya/v1/course-builder/save', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        course_id: course.id,
        title: `E2E extras ${stamp}`,
        description: 'Course with extras.',
        course_status: 'publish',
        course_faq: [{ question: faqQuestion, answer: faqAnswer }],
        course_announcements: [{ title: annTitle, message: `Body ${stamp}` }],
      },
    });
    const saveText = await save.text();
    test.info().attach('builder-save', { body: saveText.slice(0, 600) });
    expect(save.status(), `builder save failed: ${saveText.slice(0, 300)}`).toBeLessThan(400);

    // FAQ belongs on the public course page.
    // An explicitly empty storage state: the file-level `test.use` login must not
    // leak into this context, or "is it public?" would be answered as an admin.
    const publicPage = await browser.newContext({
      baseURL: baseURL ?? 'http://sikshya.local',
      storageState: { cookies: [], origins: [] },
    });
    const anon = await publicPage.newPage();
    await anon.goto(`/?p=${course.id}`, { waitUntil: 'domcontentloaded' });
    const anonText = await anon.locator('body').innerText();
    // The FAQ answer sits in a collapsed accordion panel, so it is in the DOM but
    // not in innerText — assert the rendered markup for it.
    const anonHtml = await anon.content();
    test.info().attach('public-course-page', { body: anonText.slice(0, 2000) });

    expect(
      anonText,
      'the context must be anonymous for this to answer "is it public?"',
    ).not.toContain('About WordPress');
    expect(
      anonText,
      'an authored FAQ question must render on the public course page',
    ).toContain(faqQuestion);
    expect(anonHtml, 'the FAQ answer must be rendered too').toContain(faqAnswer);

    // Announcements are for enrolled learners, so they must NOT be public.
    expect(
      anonHtml,
      'announcements are described as shown to enrolled learners; they must not reach anonymous visitors',
    ).not.toContain(annTitle);
    await publicPage.close();

    // An enrolled learner sees the announcement inside the Learn UI.
    const username = slug('extrastud');
    const password = 'ExtraPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);
    await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });

    await session.page.goto(learnUrl('lesson', lesson.slug), { waitUntil: 'domcontentloaded' });
    // The lesson screen renders announcements inside a tab panel, which is
    // hidden until selected — so read the DOM, not just visible text.
    const lessonHtml = await session.page.content();
    test.info().attach('lesson-html-slice', {
      body: lessonHtml.slice(Math.max(0, lessonHtml.indexOf('announcements') - 200), lessonHtml.indexOf('announcements') + 1500),
    });
    expect(
      lessonHtml,
      'an enrolled learner must receive the announcement on the lesson screen',
    ).toContain(annTitle);

    await session.context.close();
  });
});
