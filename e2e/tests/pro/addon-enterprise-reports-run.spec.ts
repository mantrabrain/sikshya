import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Enterprise Reports: a schedule must be runnable, and the run must be visible.
 *
 * The existing spec reads the dashboard. What an enterprise customer relies on is
 * that a scheduled report can actually be produced and tracked — so this creates a
 * schedule, triggers a manual run, and requires that run to show up in the dashboard
 * attached to its schedule. A run that is created but never surfaces is indistinguishable
 * from a report that never ran.
 */
test.describe('addon: enterprise report runs', () => {
  test('a schedule can be created, run on demand, and the run is tracked', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post(
      '/wp-json/sikshya/v1/admin/addons/enterprise_reports/enable',
      { headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' } },
    );
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'enterprise_reports is not included in this licence',
    );

    const label = `E2E schedule ${slug('sch')}`;
    const create = await request.post(
      '/wp-json/sikshya/v1/pro/enterprise-reports/v2/schedules',
      {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        data: {
          label,
          report_type: 'overview',
          frequency: 'weekly',
          day_of_week: 1,
          hour: 6,
          status: 'active',
          executive_summary: 1,
        },
      },
    );
    const createText = await create.text();
    test.info().attach('schedule-create', { body: createText.slice(0, 400) });
    expect(create.status(), `creating the schedule failed: ${createText.slice(0, 250)}`).toBeLessThan(400);
    const scheduleId = Number((JSON.parse(createText) as { id?: number }).id ?? 0);
    expect(scheduleId, `the schedule must have an id: ${createText.slice(0, 250)}`).toBeGreaterThan(0);

    // Precondition: it must be listed, otherwise the run below could attach to
    // something else entirely.
    const dashBefore = await request.get(
      '/wp-json/sikshya/v1/pro/enterprise-reports/v2/dashboard',
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const beforeBody = (await dashBefore.json().catch(() => ({}))) as {
      schedules?: Array<Record<string, unknown>>;
      runs?: Array<Record<string, unknown>>;
    };
    expect(dashBefore.ok(), 'the dashboard must be readable').toBeTruthy();
    expect(
      (beforeBody.schedules ?? []).some((s) => Number(s.id) === scheduleId),
      'precondition: the new schedule must appear on the dashboard',
    ).toBeTruthy();
    const runsBefore = (beforeBody.runs ?? []).filter((r) => Number(r.schedule_id) === scheduleId).length;

    // Run it on demand.
    const run = await request.post('/wp-json/sikshya/v1/pro/enterprise-reports/v2/run', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { schedule_id: scheduleId },
    });
    const runText = await run.text();
    test.info().attach('run-now', { body: runText.slice(0, 400) });
    expect(run.status(), `running the report failed: ${runText.slice(0, 250)}`).toBeLessThan(400);
    const runId = Number((JSON.parse(runText) as { run_id?: number }).run_id ?? 0);
    expect(runId, `a run id must come back: ${runText.slice(0, 250)}`).toBeGreaterThan(0);

    // The run must be tracked against the schedule.
    await expect
      .poll(
        async () => {
          const res = await request.get(
            `/wp-json/sikshya/v1/pro/enterprise-reports/v2/dashboard?_cb=${Date.now()}`,
            { headers: { 'X-WP-Nonce': nonce } },
          );
          const body = (await res.json().catch(() => ({}))) as {
            runs?: Array<Record<string, unknown>>;
          };
          return (body.runs ?? []).filter((r) => Number(r.schedule_id) === scheduleId).length;
        },
        {
          timeout: 30_000,
          message: 'a manual run must be tracked against its schedule on the dashboard',
        },
      )
      .toBeGreaterThan(runsBefore);

    // Clean up so the dev site is not left with a live weekly schedule.
    const del = await request.delete(
      `/wp-json/sikshya/v1/pro/enterprise-reports/v2/schedules/${scheduleId}`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    expect(del.status(), 'the schedule must be deletable').toBeLessThan(400);
  });
});
