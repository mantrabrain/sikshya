import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createUserViaRest,
  getAdminNonce,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(!routes.some((r) => /\/pro\//.test(r)), 'Sikshya Pro not active');
});

test.describe('addon: prerequisites enrollment gate', () => {
  test('student cannot enroll in a course whose prerequisite they have not completed', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/prerequisites/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    // Course A is the prerequisite, Course B requires A.
    const courseA = await createCourseViaRest(page, request, {
      title: `E2E prereq A ${Date.now()}`,
      type: 'free',
    });
    const courseB = await createCourseViaRest(page, request, {
      title: `E2E prereq B ${Date.now()}`,
      type: 'free',
    });

    // Wire prereqs: courseB requires courseA.
    const setPrereq = await request.post(
      `/wp-json/sikshya/v1/pro/courses/${courseB.id}/prerequisites`,
      {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: { prerequisite_course_ids: [courseA.id] },
      },
    );
    const setBody = await setPrereq.json().catch(() => ({}));
    expect(setPrereq.status(), JSON.stringify(setBody).slice(0, 200)).toBeLessThan(400);
    expect(setBody?.ok, JSON.stringify(setBody)).toBe(true);
    expect((setBody?.prerequisite_course_ids ?? []).map(Number)).toContain(courseA.id);

    const username = slug('prq');
    const password = 'PrqPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });

    const session = await studentSession(
      browser,
      baseURL ?? 'http://sikshya.local',
      username,
      password,
    );

    // Attempting to enroll in B (without completing A) must be blocked by the gate.
    const blocked = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: courseB.id },
    });
    const blockedBody = await blocked.json().catch(() => ({}));
    test.info().attach('prereq-block-response', {
      body: `status=${blocked.status()}\n${JSON.stringify(blockedBody).slice(0, 300)}`,
    });
    // The prereq gate returns a WP_Error with status 403 → REST wraps it as a non-success response.
    expect(blocked.status(), JSON.stringify(blockedBody)).toBeGreaterThanOrEqual(400);

    // Enrolling in A first should still work.
    const okA = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: courseA.id },
    });
    expect(okA.status()).toBeLessThan(400);

    await session.context.close();
  });
});
