import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

/**
 * Licence enforcement across every Pro addon.
 *
 * Enablement and entitlement are separate in Sikshya: an addon can be switched
 * on while the licence tier does not include it (several Scale addons are
 * enabled on this site at `starter`). What matters is that the plan, not the
 * toggle, decides access.
 *
 * Measured behaviour: a non-entitled addon does not register its routes at all,
 * so its endpoints answer 404 `rest_no_route` rather than 403
 * `sikshya_plan_feature_required`. That is the stronger of the two outcomes — the
 * surface is absent rather than merely refused — so this accepts either, and in
 * both cases requires that nothing of the protected payload comes back.
 * `ProRestAddonPermissions::forRest()` provides the 403 path for addons whose
 * routes are registered regardless of tier.
 *
 * Probe paths are the real registered routes — verified against
 * `rest_get_server()->get_routes()`, not guessed. Three of them were wrong on the
 * first pass and reported a missing feature that was simply at another path.
 *
 * The entitled half is the control: without it, a site where every Pro route
 * failed (expired licence, Pro inactive, broken bootstrap) would satisfy every
 * locked assertion while proving nothing.
 */
type Probe = { addon: string; path: string };

const PROBES: Probe[] = [
  { addon: 'certificates_advanced', path: '/wp-json/sikshya/v1/pro/certificates/advanced' },
  { addon: 'activity_log', path: '/wp-json/sikshya/v1/pro/extended/activity-log?per_page=5' },
  { addon: 'gradebook', path: '/wp-json/sikshya/v1/pro/gradebook' },
  { addon: 'quiz_advanced', path: '/wp-json/sikshya/v1/pro/quiz-advanced/bank-terms' },
  { addon: 'enterprise_reports', path: '/wp-json/sikshya/v1/pro/enterprise-reports/v2/dashboard' },
  { addon: 'social_login', path: '/wp-json/sikshya/v1/pro/social-login' },
  { addon: 'white_label', path: '/wp-json/sikshya/v1/pro/white-label' },
  { addon: 'calendar', path: '/wp-json/sikshya/v1/pro/extended/calendar?limit=10' },
  { addon: 'email_marketing', path: '/wp-json/sikshya/v1/pro/email-marketing/rules' },
  { addon: 'subscriptions', path: '/wp-json/sikshya/v1/pro/plans' },
  { addon: 'multi_instructor', path: '/wp-json/sikshya/v1/pro/multi-instructor/course-staff?course_id=1' },
  { addon: 'public_api_keys', path: '/wp-json/sikshya/v1/scale/public-api/keys' },
  { addon: 'webhooks', path: '/wp-json/sikshya/v1/scale/webhooks' },
];

test.use({ storageState: 'e2e/.auth/admin.json' });

test.describe('pro: licence tier gating', () => {
  test('every non-entitled addon is unreachable, and leaks none of its data', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    const addonsRes = await request.get('/wp-json/sikshya/v1/admin/addons', {
      headers: { 'X-WP-Nonce': nonce },
    });
    expect(addonsRes.ok(), `GET /admin/addons status=${addonsRes.status()}`).toBeTruthy();
    const addonsBody = await addonsRes.json().catch(() => ({}));
    const list: Array<Record<string, unknown>> =
      addonsBody?.addons ?? addonsBody?.data?.addons ?? [];
    expect(list.length, 'the addon catalogue must be readable').toBeGreaterThan(0);

    const entitled = new Map<string, boolean>();
    const enabled = new Map<string, boolean>();
    const tiers = new Map<string, string>();
    for (const a of list) {
      const slug = String(a.id ?? a.slug ?? '');
      if (!slug) continue;
      entitled.set(slug, (a.license_ok ?? a.licenseOk ?? a.entitled) === true);
      // Entitlement and enablement are independent: an addon the licence covers
      // but the site has switched off registers no routes, so it answers 404.
      // Expecting it to respond would be wrong.
      enabled.set(slug, (a.enabled ?? a.is_enabled) === true);
      tiers.set(slug, String(a.tier ?? a.min_tier ?? '?'));
    }

    const locked: string[] = [];
    const open: string[] = [];
    const problems: string[] = [];

    for (const probe of PROBES) {
      if (!entitled.has(probe.addon)) continue; // not in this build's catalogue

      const res = await request.get(probe.path, { headers: { 'X-WP-Nonce': nonce } });
      const text = await res.text();
      const body = (() => {
        try {
          return JSON.parse(text) as Record<string, unknown>;
        } catch {
          return {} as Record<string, unknown>;
        }
      })();

      if (entitled.get(probe.addon) === true) {
        if (enabled.get(probe.addon) !== true) {
          continue; // entitled but switched off — no routes to answer
        }
        open.push(probe.addon);

        // What matters for an entitled+enabled addon is that the route is
        // REACHABLE — not that this probe happened to send valid parameters.
        // A 400 like `invalid_course` (these probes use placeholder ids) still
        // proves the endpoint exists and the plan is not blocking it. Only a
        // missing route or a tier refusal contradict entitlement.
        const blockedCode = String(body.code ?? '');
        if (res.status() === 404) {
          problems.push(
            `${probe.addon} (tier=${tiers.get(probe.addon)}) is entitled and enabled but its route is absent (404): ${text.slice(0, 160)}`,
          );
        } else if (res.status() === 403 && blockedCode === 'sikshya_plan_feature_required') {
          problems.push(
            `${probe.addon} (tier=${tiers.get(probe.addon)}) is entitled but was refused for plan reasons: ${text.slice(0, 160)}`,
          );
        }
        continue;
      }

      locked.push(probe.addon);

      const code = String(body.code ?? '');
      const unreachable = res.status() === 404 && code === 'rest_no_route';
      const refused = res.status() === 403 && code === 'sikshya_plan_feature_required';

      if (!unreachable && !refused) {
        problems.push(
          `${probe.addon} (tier=${tiers.get(probe.addon)}) is NOT entitled but answered ${res.status()} ` +
            `code="${code}" — a paid feature must be unreachable without the plan: ${text.slice(0, 160)}`,
        );
        continue;
      }

      // Whichever way it is closed, the protected payload must not come back.
      //
      // A WP_Error's `data` legitimately carries scalar metadata the admin UI
      // uses to render an upgrade prompt (status, feature, legacy_error_code), so
      // the test is not an allowlist of key names — which would need editing
      // every time one is added. What must never appear is a record collection:
      // an array or nested object is the shape the protected reports, keys and
      // subscriber lists would arrive in.
      const data = body.data as Record<string, unknown> | undefined;
      const collectionKeys =
        data && typeof data === 'object'
          ? Object.entries(data)
              .filter(([, v]) => Array.isArray(v) || (typeof v === 'object' && v !== null))
              .map(([k]) => k)
          : [];
      if (collectionKeys.length > 0) {
        problems.push(
          `${probe.addon} returned collection data (${collectionKeys.join(', ')}) alongside its ${res.status()}: ${text.slice(0, 160)}`,
        );
      }
    }

    test.info().attach('tier-gating', {
      body: JSON.stringify({ locked, open, problems }, null, 2),
    });

    // Report first, so a failure explains itself even when a control trips.
    expect(problems, problems.join('\n')).toEqual([]);

    // Control: the entitled side must answer, or a site where every Pro route
    // failed (expired licence, Pro inactive, broken bootstrap) would satisfy the
    // locked assertions vacuously.
    expect(
      open.length,
      'no entitled addon answered; the locked assertions would pass vacuously',
    ).toBeGreaterThan(0);

    // On a full Scale licence nothing is gated, so there is no lock to observe.
    // That is a property of the site, not a failure — skip rather than assert a
    // falsehood. Run this spec on a lower tier to exercise the gate itself.
    test.skip(
      locked.length === 0,
      'every probed addon is entitled on this licence, so tier gating cannot be observed here',
    );
  });

  test('a locked addon stays locked even when it is switched on', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);

    const addonsRes = await request.get('/wp-json/sikshya/v1/admin/addons', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const addonsBody = await addonsRes.json().catch(() => ({}));
    const list: Array<Record<string, unknown>> =
      addonsBody?.addons ?? addonsBody?.data?.addons ?? [];

    // Pick a non-entitled addon that has a probe route.
    const target = PROBES.find((p) => {
      const a = list.find((x) => String(x.id ?? x.slug ?? '') === p.addon);
      return a && (a.license_ok ?? a.licenseOk ?? a.entitled) !== true;
    });
    test.skip(!target, 'every probed addon is entitled on this licence');

    // Switching an addon on must not grant entitlement. Enabling is a site
    // preference; the plan is what decides access.
    await request.post(`/wp-json/sikshya/v1/admin/addons/${target!.addon}/enable`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const res = await request.get(target!.path, { headers: { 'X-WP-Nonce': nonce } });
    const text = await res.text();
    expect(
      [403, 404],
      `enabling ${target!.addon} must not unlock it without the plan; got ${res.status()}: ${text.slice(0, 200)}`,
    ).toContain(res.status());
  });
});
