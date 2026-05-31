import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  createUserViaRest,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';

// This spec seeds data as admin then acts as a student, so it must run under
// the admin storageState.
test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('learner: free-course enroll + lesson complete', () => {
  test('student can enroll in a free course and mark a lesson complete', async ({
    page,
    request,
    browser,
    baseURL,
  }, testInfo) => {
    // Admin context (we're in the `admin` project storageState).
    const course = await createCourseViaRest(page, request, {
      title: `E2E free course ${Date.now()}`,
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    const username = slug('stud');
    const password = 'StudPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });

    // Switch to a fresh context as the student.
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);
    testInfo.attach('student-nonce-preview', { body: session.restNonce.slice(0, 8) });
    expect(session.restNonce.length).toBeGreaterThan(0);

    // Enroll via sikshya/v1/me/enroll.
    const enrollRes = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    const enrollBody = await enrollRes.json().catch(() => ({}));
    expect(enrollRes.status(), JSON.stringify(enrollBody)).toBeLessThan(400);

    // Mark the lesson complete via sikshya/v1/me/lesson-complete.
    const completeRes = await session.request.post('/wp-json/sikshya/v1/me/lesson-complete', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { lesson_id: lesson.id, course_id: course.id },
    });
    const completeBody = await completeRes.json().catch(() => ({}));
    expect(completeRes.status(), JSON.stringify(completeBody)).toBeLessThan(400);

    await session.context.close();
  });
});
