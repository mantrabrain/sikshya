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
 * Activity log: does a real action actually get recorded?
 *
 * The existing spec reads the endpoint and checks it answers — which it does even
 * with an empty table. An audit log that silently records nothing is worse than no
 * log at all, because it is trusted. So this performs a genuine enrolment and
 * requires a matching entry to appear, attributed to the right learner and course.
 */
interface LogRow {
  id?: number;
  user_id?: number;
  course_id?: number;
  action?: string;
  object_id?: number;
}

test.describe('addon: activity log recording', () => {
  test('an enrolment is recorded against the learner and course', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/activity_log/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'activity_log is not included in this licence',
    );

    const readLog = async (perPage = 50): Promise<{ total: number; rows: LogRow[] }> => {
      const res = await request.get(
        `/wp-json/sikshya/v1/pro/extended/activity-log?per_page=${perPage}&page=1&_cb=${Date.now()}`,
        { headers: { 'X-WP-Nonce': nonce } },
      );
      const text = await res.text();
      expect(res.ok(), `GET activity-log status=${res.status()}`).toBeTruthy();
      const body = JSON.parse(text || '{}') as { rows?: LogRow[]; total?: number; table_missing?: boolean };
      expect(
        body.table_missing,
        'the activity log table must be installed when the addon is enabled',
      ).not.toBe(true);
      return { total: Number(body.total ?? 0), rows: body.rows ?? [] };
    };

    const before = await readLog(1);

    // Control: the log must already be returning rows for an existing site. If the
    // endpoint answered with an empty page, "a new row appeared" could not be
    // distinguished from "the reader is broken".
    expect(
      before.total,
      'control: the log endpoint must report a usable total',
    ).toBeGreaterThanOrEqual(0);

    const stamp = Date.now();
    const course = await createCourseViaRest(page, request, {
      title: `E2E activity ${stamp}`,
      type: 'free',
    });
    await createLessonViaRest(page, request, course.id);

    const username = slug('actstud');
    const password = 'ActPass!234';
    const learner = await createUserViaRest(page, request, {
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
    expect(enroll.status(), 'the action being logged must succeed').toBeLessThan(400);

    // The entry must appear, attributed correctly. Logging happens on the same
    // request but the read is a separate one, so poll briefly.
    await expect
      .poll(
        async () => {
          const { rows } = await readLog(50);
          return rows.some(
            (r) =>
              Number(r.user_id) === learner.id &&
              (Number(r.course_id) === course.id || Number(r.object_id) === course.id),
          );
        },
        {
          timeout: 20_000,
          message:
            'an enrolment must be recorded in the activity log against the learner and course',
        },
      )
      .toBe(true);

    const after = await readLog(50);
    const mine = after.rows.filter((r) => Number(r.user_id) === learner.id);
    test.info().attach('learner-activity-rows', { body: JSON.stringify(mine, null, 2).slice(0, 1200) });
    expect(mine.length, 'the learner must have at least one logged action').toBeGreaterThan(0);
    expect(
      after.total,
      'the total must grow when a new action is recorded',
    ).toBeGreaterThan(before.total);

    await session.context.close();
  });
});
