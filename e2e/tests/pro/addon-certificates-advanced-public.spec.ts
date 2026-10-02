import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  createUserViaRest,
  getAdminNonce,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * The public certificate page that Advanced Certificates adds.
 *
 * It serves `/{certificate_base}/{template-slug}/?hash={verification_code}` and
 * deliberately 404s when opened without a hash. That makes the hash the only
 * credential, so the security property is worth pinning: a correct hash renders
 * the holder's certificate, and a wrong or absent one renders nothing — it must
 * not fall back to some other certificate or to a bare template.
 */
test.describe('addon: advanced certificate public page', () => {
  test('a certificate is viewable by its hash, and not without it', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post(
      '/wp-json/sikshya/v1/admin/addons/certificates_advanced/enable',
      { headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' } },
    );
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'certificates_advanced is not included in this licence',
    );

    const stamp = Date.now();
    const course = await createCourseViaRest(page, request, {
      title: `E2E advcert ${stamp}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    const username = slug('advcert');
    const password = 'AdvPass!234';
    const learner = await createUserViaRest(page, request, {
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
    const complete = await session.request.post('/wp-json/sikshya/v1/me/lesson-complete', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id, lesson_id: lesson.id },
    });
    expect(complete.ok(), `completion failed: ${(await complete.text()).slice(0, 200)}`).toBeTruthy();

    // Find the issued certificate.
    const issued = await request.get('/wp-json/sikshya/v1/admin/issued-certificates?per_page=50', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const issuedText = await issued.text();
    expect(issued.ok(), `GET /admin/issued-certificates status=${issued.status()}`).toBeTruthy();
    const issuedBody = JSON.parse(issuedText) as Record<string, unknown>;
    const rows = ((issuedBody.certificates ??
      (issuedBody.data as Record<string, unknown>)?.certificates ??
      (issuedBody.data as Record<string, unknown>)?.rows ??
      issuedBody.rows ??
      []) as Array<Record<string, unknown>>);
    const mine = rows.find(
      (r) => Number(r.course_id) === course.id && Number(r.user_id) === learner.id,
    );
    expect(mine, 'completing the course must issue a certificate').toBeTruthy();

    const hash = String(mine?.verification_code ?? mine?.code ?? '');
    expect(hash.length, 'the certificate must carry a verification hash').toBeGreaterThan(0);

    // Resolve the template slug the public URL needs.
    const templateId = Number(mine?.template_post_id ?? mine?.template_id ?? 0);
    let templateSlug = '';
    if (templateId > 0) {
      const tpl = await request.get(`/wp-json/wp/v2/sikshya_certificate/${templateId}?context=edit`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      const tplBody = await tpl.json().catch(() => ({}));
      templateSlug = String(tplBody?.slug ?? '');
    }
    test.info().attach('certificate-row', {
      body: JSON.stringify({ hash: hash.slice(0, 12) + '…', templateId, templateSlug }, null, 2),
    });
    test.skip(
      templateSlug === '',
      'could not resolve the certificate template slug from the issued row',
    );

    const anonCtx = await browser.newContext({
      baseURL: baseURL ?? 'http://sikshya.local',
      storageState: { cookies: [], origins: [] },
    });
    const anon = await anonCtx.newPage();

    // With the correct hash: the certificate renders for anyone holding the link.
    const ok = await anon.goto(`/certificates/${templateSlug}/?hash=${encodeURIComponent(hash)}`, {
      waitUntil: 'domcontentloaded',
    });
    const okHtml = await anon.content();
    test.info().attach('certificate-with-hash', { body: okHtml.slice(0, 2000) });
    expect(ok?.status() ?? 0, 'a valid hash must render the certificate page').toBeLessThan(400);
    expect(
      okHtml,
      'the rendered certificate must name the course it was awarded for',
    ).toContain(`E2E advcert ${stamp}`);

    // Without a hash: a real 404, not a bare template.
    const bare = await anon.goto(`/certificates/${templateSlug}/`, { waitUntil: 'domcontentloaded' });
    expect(
      bare?.status() ?? 0,
      'opening the template with no hash must 404 rather than expose a blank certificate',
    ).toBe(404);

    // With a tampered hash: must not render somebody else's certificate.
    const forged = `${hash.slice(0, -6)}abc123`;
    const bad = await anon.goto(`/certificates/${templateSlug}/?hash=${encodeURIComponent(forged)}`, {
      waitUntil: 'domcontentloaded',
    });
    const badHtml = await anon.content();
    expect(
      (bad?.status() ?? 0) === 404 || !badHtml.includes(`E2E advcert ${stamp}`),
      'a tampered hash must not render a certificate',
    ).toBeTruthy();

    await anonCtx.close();
    await session.context.close();
  });
});
