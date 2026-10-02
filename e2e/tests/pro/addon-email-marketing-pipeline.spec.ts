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

/**
 * Email Marketing: rule configuration, and the provider dependency.
 *
 * Scope, measured rather than assumed. This addon does not send email — its actions
 * are `{ upsert, tags_add }`, syncing contacts to an external provider (Mailchimp and
 * friends). With no provider configured, the event listener deliberately does no
 * work: an enrolment with an active matching rule queues nothing and schedules no
 * cron. Verified directly against the queue table, which stayed empty.
 *
 * That is correct — there is nothing to sync to — but it means the delivery leg
 * cannot be exercised without real third-party credentials, the same limitation as
 * social login. So this covers what is genuinely verifiable: the rule survives a
 * round trip with its event binding intact, and the addon does not accumulate
 * pointless queued work while unconfigured.
 */
test.describe('addon: email marketing rule pipeline', () => {
  test('a rule round-trips, and an unconfigured provider queues no work', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/email_marketing/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'email_marketing is not included in this licence',
    );

    const stamp = Date.now();

    const countLogs = async (): Promise<number> => {
      const res = await request.get(
        `/wp-json/sikshya/v1/pro/email-marketing/logs?per_page=1&page=1&_cb=${Date.now()}`,
        { headers: { 'X-WP-Nonce': nonce } },
      );
      if (!res.ok()) return -1;
      const body = (await res.json().catch(() => ({}))) as Record<string, unknown>;
      return Number(body.total ?? (body.data as Record<string, unknown>)?.total ?? 0);
    };

    const logsBefore = await countLogs();
    expect(logsBefore, 'the logs endpoint must be readable').toBeGreaterThanOrEqual(0);

    // An active rule on the enrolment event.
    const ruleRes = await request.post('/wp-json/sikshya/v1/pro/email-marketing/rules', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        name: `E2E rule ${stamp}`,
        description: 'Created by the e2e suite.',
        event_key: 'sikshya_user_enrolled',
        is_active: 1,
        priority: 10,
        filters_json: JSON.stringify({}),
        actions_json: JSON.stringify({ upsert: true, tags_add: [`e2e-${stamp}`] }),
      },
    });
    const ruleText = await ruleRes.text();
    test.info().attach('rule-create', { body: ruleText.slice(0, 400) });
    expect(ruleRes.status(), `rule creation failed: ${ruleText.slice(0, 250)}`).toBeLessThan(400);

    // Precondition: the rule must be listed and active, or nothing downstream means anything.
    const list = await request.get('/wp-json/sikshya/v1/pro/email-marketing/rules', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const listBody = (await list.json().catch(() => ({}))) as { rules?: Array<Record<string, unknown>> };
    const mine = (listBody.rules ?? []).find((r) => String(r.name ?? '').includes(String(stamp)));
    expect(mine, 'the new rule must appear in the rules list').toBeTruthy();
    expect(
      String(mine?.event_key ?? ''),
      'precondition: the rule must be bound to the enrolment event',
    ).toBe('sikshya_user_enrolled');

    // Now make the event happen for real.
    const course = await createCourseViaRest(page, request, {
      title: `E2E em course ${stamp}`,
      type: 'free',
    });
    const username = slug('emstud');
    const password = 'EmPass!234';
    await createUserViaRest(page, request, {
      username,
      email: `${username}@example.com`,
      password,
      role: STUDENT_ROLE,
    });
    const session = await studentSession(browser, baseURL ?? 'http://sikshya.local', username, password);
    const enroll = await session.request.post('/wp-json/sikshya/v1/me/enroll', {
      headers: { 'X-WP-Nonce': session.restNonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    expect(enroll.status(), 'the enrolment that should match the rule must succeed').toBeLessThan(400);

    // With no provider configured the listener must stay idle rather than piling up
    // work that can never succeed. `logs` is written by the queue runner, so an
    // unconfigured install should show no growth here even after cron runs.
    for (let i = 0; i < 2; i++) {
      await request.get('/wp-cron.php?doing_wp_cron', { timeout: 30_000 }).catch(() => undefined);
      await new Promise((r) => setTimeout(r, 1000));
    }

    const logsAfter = await countLogs();
    test.info().attach('logs-before-after', {
      body: JSON.stringify({ logsBefore, logsAfter }, null, 2),
    });
    expect(
      logsAfter,
      'with no email provider configured, an enrolment must not generate sync attempts — ' +
        'queuing work that cannot succeed would retry forever',
    ).toBe(logsBefore);

    // The rule must still be intact and editable: deleting it must work, so an admin
    // is not left with undeletable automation.
    const ruleId = Number(mine?.id ?? 0);
    expect(ruleId, 'the rule must have an id').toBeGreaterThan(0);
    const del = await request.delete(`/wp-json/sikshya/v1/pro/email-marketing/rules/${ruleId}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    expect(del.status(), `deleting the rule failed: ${(await del.text()).slice(0, 200)}`).toBeLessThan(400);

    const listAfter = await request.get('/wp-json/sikshya/v1/pro/email-marketing/rules', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const listAfterBody = (await listAfter.json().catch(() => ({}))) as {
      rules?: Array<Record<string, unknown>>;
    };
    expect(
      (listAfterBody.rules ?? []).some((r) => Number(r.id) === ruleId),
      'a deleted rule must not remain in the list',
    ).toBeFalsy();

    await session.context.close();
  });
});
