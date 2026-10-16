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
 * Groups & seat licensing: the B2B flow an organisation actually buys.
 *
 * An administrator sells a block of seats, appoints a leader, and attaches a
 * paid course. From there the customer runs themselves: the leader adds learners
 * without admin involvement, those learners get access, and the seat cap is
 * enforced. The last part is the commercial invariant — a leader who can add a
 * learner beyond the purchased count is giving away product.
 */
test.describe('addon: groups and seat licensing', () => {
  test('a leader fills their seats, learners get access, and the cap holds', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    /*
     * Three separate learners sign in here through the real login form — the
     * leader, a learner who gets a seat, and one who is refused — because the
     * cap can only be proven from three different identities. Each sign-in is
     * its own browser context and its own round trip, and the test needs 57
     * seconds of its 120-second budget on an idle machine. That leaves no room
     * for a loaded one: it is the only spec in the suite that signs in three
     * times, and it was the only failure in an otherwise clean cross-browser
     * run, passing on its own immediately afterwards.
     *
     * Tripling the budget rather than trimming the test, because all three
     * identities are the point of it.
     */
    test.slow();

    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/groups_seats/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'groups_seats is not included in this licence',
    );

    const stamp = Date.now();

    // A paid course — group access must bypass the paywall, which is the point
    // of buying seats.
    const course = await createCourseViaRest(page, request, {
      title: `E2E group course ${stamp}`,
      price: 149,
    });
    expect(course.id).toBeGreaterThan(0);

    // The customer: a leader plus two learners, on exactly two seats.
    const leaderName = slug('grpleader');
    const learnerA = slug('grplearnA');
    const learnerB = slug('grplearnB');
    const learnerC = slug('grplearnC');
    const password = 'GroupPass!2026';
    const users: Record<string, number> = {};
    for (const u of [leaderName, learnerA, learnerB, learnerC]) {
      const created = await createUserViaRest(page, request, {
        username: u,
        email: `${u}@example.com`,
        password,
        role: STUDENT_ROLE,
      });
      users[u] = created.id;
    }

    // 1. Admin creates the group with two seats and the leader as owner.
    const create = await request.post('/wp-json/sikshya/v1/pro/groups', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        name: `Acme ${stamp}`,
        seats_total: 2,
        owner_user_id: users[leaderName],
      },
    });
    const createText = await create.text();
    test.info().attach('group-create', { body: createText.slice(0, 400) });
    expect(create.status(), `creating the group failed: ${createText.slice(0, 250)}`).toBeLessThan(400);
    const groupId = Number((JSON.parse(createText) as { id?: number }).id ?? 0);
    expect(groupId, 'the group must have an id').toBeGreaterThan(0);

    // 2. Admin attaches the paid course.
    const attach = await request.post(`/wp-json/sikshya/v1/pro/groups/${groupId}/courses`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { course_id: course.id },
    });
    expect(attach.status(), (await attach.text()).slice(0, 250)).toBeLessThan(400);

    // 3. From here the customer self-serves. The leader is not a site admin.
    const leader = await studentSession(browser, baseURL ?? 'http://sikshya.local', leaderName, password);
    try {
      const mine = await leader.request.get('/wp-json/sikshya/v1/me/groups', {
        headers: { 'X-WP-Nonce': leader.restNonce },
      });
      const mineBody = (await mine.json().catch(() => ({}))) as {
        groups?: Array<{ id?: number; can_manage?: boolean; seats?: { seats_total?: number } }>;
      };
      test.info().attach('me-groups', { body: JSON.stringify(mineBody).slice(0, 600) });
      const own = (mineBody.groups ?? []).find((g) => Number(g.id) === groupId);
      expect(own, 'the owner must see their group at /me/groups').toBeTruthy();
      expect(own?.can_manage, 'the owner must be able to administer it').toBeTruthy();
      expect(own?.seats?.seats_total, 'the purchased seat count must be reported').toBe(2);

      // 4. The leader fills both seats themselves.
      for (const learner of [learnerA, learnerB]) {
        const add = await leader.request.post(`/wp-json/sikshya/v1/pro/groups/${groupId}/members`, {
          headers: { 'X-WP-Nonce': leader.restNonce, 'Content-Type': 'application/json' },
          data: { email: `${learner}@example.com` },
        });
        const addText = await add.text();
        expect(
          add.status(),
          `the leader must be able to add ${learner} without an admin: ${addText.slice(0, 250)}`,
        ).toBeLessThan(400);
      }

      const roster = await leader.request.get(`/wp-json/sikshya/v1/pro/groups/${groupId}/members`, {
        headers: { 'X-WP-Nonce': leader.restNonce },
      });
      const rosterBody = (await roster.json().catch(() => ({}))) as {
        seats?: { seats_used?: number; seats_available?: number };
      };
      expect(rosterBody.seats?.seats_used, 'both seats must now be used').toBe(2);
      expect(rosterBody.seats?.seats_available, 'no seats must remain').toBe(0);

      // 5. The commercial invariant: a third learner is refused.
      const overflow = await leader.request.post(`/wp-json/sikshya/v1/pro/groups/${groupId}/members`, {
        headers: { 'X-WP-Nonce': leader.restNonce, 'Content-Type': 'application/json' },
        data: { email: `${learnerC}@example.com` },
      });
      const overflowText = await overflow.text();
      expect(
        overflow.status(),
        `a third learner on two seats must be refused, got ${overflow.status()}: ${overflowText.slice(0, 200)}`,
      ).toBe(409);
      expect(overflowText, 'the refusal must explain itself').toMatch(/seat/i);

      // 6. A leader must not be able to raise their own seat count.
      const selfServe = await leader.request.post(`/wp-json/sikshya/v1/pro/groups/${groupId}`, {
        headers: { 'X-WP-Nonce': leader.restNonce, 'Content-Type': 'application/json' },
        data: { seats_total: 500 },
      });
      expect(
        selfServe.status(),
        'a leader raising their own seat count would be taking unpaid product',
      ).toBe(403);
    } finally {
      await leader.context.close();
    }

    // 7. A seated learner must actually reach the paid course.
    const seated = await studentSession(browser, baseURL ?? 'http://sikshya.local', learnerA, password);
    try {
      const progress = await seated.request.get(
        `/wp-json/sikshya/v1/me/progress?course_id=${course.id}`,
        { headers: { 'X-WP-Nonce': seated.restNonce } },
      );
      expect(
        progress.status(),
        'a learner holding a seat must have access to the group course without paying',
      ).toBeLessThan(400);
    } finally {
      await seated.context.close();
    }

    // 8. The learner who was refused a seat must have no access.
    const refused = await studentSession(browser, baseURL ?? 'http://sikshya.local', learnerC, password);
    try {
      const res = await refused.page.goto(`/learn/?course_id=${course.id}`, {
        waitUntil: 'domcontentloaded',
      });
      const html = await refused.page.content();
      expect(
        res?.status() ?? 0,
        'the refused learner must not get a server error',
      ).toBeLessThan(500);
      expect(
        /enroll|purchase|buy|add to cart|not enrolled|no access/i.test(html),
        'a learner who never got a seat must not be reading the paid course',
      ).toBeTruthy();
    } finally {
      await refused.context.close();
    }
  });
});
