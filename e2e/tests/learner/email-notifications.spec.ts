import { test, expect } from '@playwright/test';
import {
  createCourseViaRest,
  createLessonViaRest,
  createUserViaRest,
  slug,
  STUDENT_ROLE,
} from '../../utils/factories';
import { studentSession } from '../../utils/learner';
import {
  MAILPIT_URL,
  mailpitAvailable,
  waitForMail,
  recipientsOf,
  subjectsOf,
} from '../../utils/mail';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * `email_notifications_basic` is advertised as a free feature, and until now the
 * only coverage was an admin screen listing the templates — nothing asserted that
 * a message is actually produced and addressed correctly. Enrolment is the
 * highest-traffic trigger: it fans out to the learner, the site admin and the
 * course instructor, so a single misrouted recipient leaks one learner's activity
 * to another party.
 */
test.describe('learner: enrolment email notifications', () => {
  test.beforeAll(async ({ request }) => {
    test.skip(
      !(await mailpitAvailable(request)),
      'No mail catcher reachable; set MAILPIT_URL to run this spec.',
    );
  });

  test('enrolling fires the learner and staff notifications, addressed correctly', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    // A unique course title gives every assertion below an unambiguous handle in
    // a mailbox that accumulates across runs.
    const marker = `EmailProbe ${Date.now()}`;
    const course = await createCourseViaRest(page, request, { title: marker, type: 'free' });
    await createLessonViaRest(page, request, course.id);

    const username = slug('mailstud');
    const email = `${username}@example.com`;
    const password = 'MailPass!234';
    await createUserViaRest(page, request, {
      username,
      email,
      password,
      role: STUDENT_ROLE,
    });

    const session = await studentSession(
      browser,
      baseURL ?? 'http://sikshya.local',
      username,
      password,
    );

    const enroll = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    const enrollText = await enroll.text();
    expect(enroll.status(), `enrolment must succeed: ${enrollText.slice(0, 250)}`).toBeLessThan(400);
    // A 200 is not the same as an enrolment: "already enrolled" and other
    // soft refusals answer 200 with `success: false`, and would leave the mail
    // assertions below reasoning about mail that was never going to be sent.
    expect(
      (JSON.parse(enrollText) as { success?: boolean }).success,
      `enrolment returned ${enroll.status()} but did not report success: ${enrollText.slice(0, 250)}`,
    ).toBeTruthy();

    // Three messages fan out from one enrolment (learner, admin, instructor).
    // Waiting for all three keeps a still-in-flight message from being read as
    // a missing one.
    const messages = await waitForMail(request, marker, { minCount: 3 });
    const subjects = subjectsOf(messages);
    const recipients = recipientsOf(messages);

    test.info().attach('enrolment-emails', {
      body: JSON.stringify({ subjects, recipients }, null, 2),
    });

    expect(
      messages.length,
      `no mail mentioning "${marker}" arrived; enrolment notifications are silent`,
    ).toBeGreaterThan(0);

    // The learner must be told they are in.
    expect(
      recipients,
      'the enrolling learner must receive a confirmation',
    ).toContain(email.toLowerCase());

    // And staff must be told somebody enrolled. Both admin-facing mails quote the
    // course title, so assert on the learner-vs-staff split rather than exact copy.
    const staffRecipients = recipients.filter((r) => r !== email.toLowerCase());
    expect(
      staffRecipients.length,
      'at least one staff notification must be sent for a new enrolment',
    ).toBeGreaterThan(0);

    // Guard the thing that actually hurts: a learner must never be sent the
    // staff-facing copy, and staff must not receive the learner's welcome.
    const learnerMail = messages.filter((m) =>
      (m.To ?? []).some((t) => (t.Address ?? '').toLowerCase() === email.toLowerCase()),
    );
    for (const mail of learnerMail) {
      expect(
        mail.Subject.toLowerCase(),
        `learner was sent a staff-facing notification: "${mail.Subject}"`,
      ).not.toMatch(/new (student|enrollment|enrolment)/);
    }

    // The inverse, and the sharper test: the learner-facing template must be
    // addressed to the learner. Asserting only that *some* message reached the
    // learner misses the case where the welcome copy itself is delivered to the
    // admin address, which is a disclosure rather than a missing email.
    const welcome = messages.filter((m) => /you.re in|welcome to/i.test(m.Subject ?? ''));
    expect(
      welcome.length,
      `the learner-facing enrolment template never fired; subjects seen: ${JSON.stringify(subjects)}`,
    ).toBeGreaterThan(0);
    for (const mail of welcome) {
      expect(
        (mail.To ?? []).map((t) => (t.Address ?? '').toLowerCase()),
        `the learner welcome "${mail.Subject}" was delivered to the wrong recipient`,
      ).toContain(email.toLowerCase());
    }

    // And the staff copy must name the learner who actually enrolled, which is
    // what proves the notification was built from the enrolling user's context
    // rather than from whoever's session happened to be ambient.
    const staffMail = messages.filter((m) => /new (student|enrollment|enrolment)/i.test(m.Subject ?? ''));
    expect(staffMail.length, 'no staff-facing enrolment notification was sent').toBeGreaterThan(0);
    for (const mail of staffMail) {
      const detail = await request.get(`${MAILPIT_URL}/api/v1/message/${mail.ID}`);
      const body = JSON.stringify(await detail.json().catch(() => ({})));
      expect(
        body,
        `the staff notification "${mail.Subject}" does not name the enrolling learner — ` +
          'the notification was built for a different user',
      ).toContain(email);
    }

    await session.context.close();
  });
});
