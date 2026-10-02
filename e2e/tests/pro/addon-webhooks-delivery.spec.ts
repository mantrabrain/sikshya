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
 * Outbound webhooks: does a real event actually produce a delivery?
 *
 * The existing spec lists endpoints. An integration customer cares about one
 * thing: when something happens in the LMS, their endpoint hears about it. So this
 * subscribes to `enrollment.created`, performs a genuine learner enrolment through
 * the API, and checks a delivery for that event was recorded against the endpoint.
 *
 * It deliberately does not assert the HTTP call succeeded — there is no receiver
 * here, and a failed POST to an unreachable URL is still a delivery *attempt*,
 * which is what proves the event wiring works. Asserting success would test the
 * network, not the plugin.
 */
test.describe('addon: outbound webhook delivery', () => {
  test('an enrolment queues a delivery to a subscribed endpoint', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/webhooks/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'webhooks is not included in this licence',
    );

    const stamp = Date.now();
    const label = `E2E hook ${slug('wh')}`;

    const create = await request.post('/wp-json/sikshya/v1/scale/webhooks/v2/endpoints', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        label,
        // A local, resolvable URL: the point is that a delivery is attempted, not
        // that a third-party service answered.
        // Deliberately not this site. Pointing the webhook back at the server
        // under test makes WordPress issue a loopback HTTP request from inside a
        // request it is already serving; with only a couple of PHP workers the
        // pool is exhausted and the two requests deadlock until timeouts expire.
        // That stalled a cross-browser run for about ninety minutes.
        //
        // Port 9 is the discard port, so the connection is refused immediately.
        // Nothing here depends on the delivery succeeding: the row is written by
        // the enqueue path when the event fires, and the assertions below check
        // that a delivery for the subscribed event was recorded, not its outcome.
        delivery_url: `http://127.0.0.1:9/sikshya-e2e-webhook-sink?stamp=${stamp}`,
        secret: `shh-${stamp}`,
        events: ['enrollment.created'],
      },
    });
    const createText = await create.text();
    test.info().attach('endpoint-create', { body: createText.slice(0, 500) });
    expect(create.status(), `endpoint creation failed: ${createText.slice(0, 250)}`).toBeLessThan(400);

    const createBody = JSON.parse(createText) as Record<string, unknown>;
    // The payload is `{ ok, item: { id, label, delivery_url, event_key, ... } }`.
    const endpointId = Number(
      (createBody.item as Record<string, unknown>)?.id ??
        createBody.id ??
        (createBody.data as Record<string, unknown>)?.id ??
        createBody.endpoint_id ??
        0,
    );
    expect(endpointId, `the endpoint must have an id: ${createText.slice(0, 250)}`).toBeGreaterThan(0);

    // Baseline delivery count for this endpoint.
    const readDeliveries = async (): Promise<Array<Record<string, unknown>>> => {
      const res = await request.get(
        `/wp-json/sikshya/v1/scale/webhooks/v2/deliveries?endpoint_id=${endpointId}&per_page=50&_cb=${Date.now()}`,
        { headers: { 'X-WP-Nonce': nonce } },
      );
      const text = await res.text();
      if (!res.ok()) return [];
      const body = JSON.parse(text || '{}') as Record<string, unknown>;
      const rows = (((body.data as Record<string, unknown>)?.items ??
        body.items ??
        body.deliveries ??
        []) as Array<Record<string, unknown>>);

      // The `endpoint_id` query parameter is not honoured by this route — it
      // returns the whole delivery log — so narrow to our endpoint here. Without
      // this, the count comparison would be polluted by every other delivery on
      // the site and could pass for the wrong reason.
      return rows.filter((r) => Number(r.endpoint_id) === endpointId);
    };

    const before = await readDeliveries();

    // Now cause a real enrolment.
    const course = await createCourseViaRest(page, request, {
      title: `E2E hook course ${stamp}`,
      type: 'free',
    });
    await createLessonViaRest(page, request, course.id);

    const username = slug('hookstud');
    const password = 'HookPass!234';
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
    expect(enroll.status(), 'the enrolment that should trigger the hook must succeed').toBeLessThan(400);

    // A delivery must appear. Queueing is asynchronous, so poll.
    await expect
      .poll(
        async () => (await readDeliveries()).length,
        {
          timeout: 30_000,
          message:
            'subscribing to enrollment.created and then enrolling a learner must queue a delivery',
        },
      )
      .toBeGreaterThan(before.length);

    const after = await readDeliveries();
    test.info().attach('deliveries', { body: JSON.stringify(after.slice(0, 5), null, 2).slice(0, 1200) });

    // The newest delivery must be for the event we subscribed to, not some other.
    const events = after.map((d) => String(d.event_key ?? ''));
    expect(
      events.some((e) => e.includes('enrollment')),
      `a delivery for enrollment.created must be recorded; saw events: ${events.join(', ').slice(0, 200)}`,
    ).toBeTruthy();

    await session.context.close();
  });
});
