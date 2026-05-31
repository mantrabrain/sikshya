import { test, expect, type APIRequestContext } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

/**
 * Settings round-trip smoke for every Pro addon. For each addon:
 *  1. Enable the addon via `/sikshya/v1/admin/addons/<id>/enable`
 *  2. GET its settings schema + values via `/sikshya/v1/pro/addons/<id>/settings`
 *  3. If a string field is present in the schema, POST a tagged round-trip value
 *     and assert the GET returns the saved value
 *
 * Each addon runs as its own test for clean per-addon reporting. Skipped if Pro
 * isn't active so the file is safe on Free-only sites.
 */

const PRO_ADDONS = [
  'activity_log',
  'assignments_advanced',
  'calendar',
  'certificates_advanced',
  'community_discussions',
  'content_drip',
  'coupons_advanced',
  'course_bundles',
  'course_reviews',
  'drip_notifications',
  'dynamic_checkout_fields',
  'email_advanced_customization',
  'email_marketing',
  'enterprise_reports',
  'gradebook',
  'instructor_dashboard',
  'live_classes',
  'marketplace_multivendor',
  'multi_instructor',
  'multilingual_enterprise',
  'multisite_scale',
  'prerequisites',
  'public_api_keys',
  'quiz_advanced',
  'reports_advanced',
  'scorm_h5p_pro',
  'social_login',
  'subscriptions',
  'webhooks',
  'white_label',
  'zapier',
] as const;

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  const hasPro = routes.some((r) => /\/pro\//.test(r));
  test.skip(!hasPro, 'Sikshya Pro not active — skipping addon settings round-trips');
});

async function enableAddon(request: APIRequestContext, page: any, addonId: string) {
  const nonce = await getAdminNonce(page);
  const res = await request.post(`/wp-json/sikshya/v1/admin/addons/${addonId}/enable`, {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
  });
  // 200 = enabled, 400 = already enabled or addon not registered.
  return { status: res.status(), body: await res.json().catch(() => ({})) };
}

async function getAddonSettings(request: APIRequestContext, nonce: string, addonId: string) {
  const res = await request.get(`/wp-json/sikshya/v1/pro/addons/${addonId}/settings`, {
    headers: { 'X-WP-Nonce': nonce },
  });
  return { status: res.status(), body: await res.json().catch(() => ({})) };
}

async function postAddonSettings(
  request: APIRequestContext,
  nonce: string,
  addonId: string,
  options: Record<string, unknown>,
) {
  const res = await request.post(`/wp-json/sikshya/v1/pro/addons/${addonId}/settings`, {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { options },
  });
  return { status: res.status(), body: await res.json().catch(() => ({})) };
}

const pickStringFieldKey = (schemaFields: unknown): string | null => {
  if (!Array.isArray(schemaFields)) return null;
  for (const f of schemaFields) {
    if (typeof f === 'object' && f !== null && 'key' in f && 'type' in f) {
      const key = String((f as { key: unknown }).key ?? '');
      const type = String((f as { type: unknown }).type ?? '');
      if (key && (type === 'string' || type === 'textarea' || type === 'password')) {
        return key;
      }
    }
  }
  return null;
};

for (const addonId of PRO_ADDONS) {
  test(`addon settings round-trip · ${addonId}`, async ({ page, request }) => {
    const nonce = await getAdminNonce(page);

    // Step 1: enable the addon (idempotent).
    await enableAddon(request, page, addonId);

    // Step 2: read schema + current values.
    const initial = await getAddonSettings(request, nonce, addonId);

    // Addons can opt out of the generic schema endpoint (they ship their own
    // admin page instead). A 404 "rest_unknown_addon" is therefore an
    // **expected** state for those addons — annotate and skip the round-trip.
    if (initial.status === 404 && /rest_unknown_addon/i.test(JSON.stringify(initial.body))) {
      test.info().annotations.push({
        type: 'addon-settings-status',
        description: 'no generic schema endpoint — addon ships its own admin page',
      });
      return;
    }

    expect(initial.status, JSON.stringify(initial.body).slice(0, 200)).toBeLessThan(400);
    expect(initial.body?.ok ?? initial.body?.success, JSON.stringify(initial.body).slice(0, 200)).toBeTruthy();

    const schemaFields =
      initial.body?.data?.schema?.fields ??
      initial.body?.data?.schema ??
      initial.body?.schema ??
      [];
    const values = initial.body?.data?.values ?? initial.body?.values ?? {};

    test.info().annotations.push({
      type: 'addon-schema-summary',
      description: `fields=${Array.isArray(schemaFields) ? schemaFields.length : 'n/a'}; values=${Object.keys(values).length}`,
    });

    // Step 3: if there's a string field, write a tagged value and verify it persists.
    const writableKey = pickStringFieldKey(schemaFields);
    if (writableKey) {
      const tag = `e2e-${Date.now().toString(36)}`;
      const writeRes = await postAddonSettings(request, nonce, addonId, { [writableKey]: tag });
      expect(writeRes.status, JSON.stringify(writeRes.body).slice(0, 200)).toBeLessThan(400);

      const after = await getAddonSettings(request, nonce, addonId);
      const afterValues = after.body?.data?.values ?? after.body?.values ?? {};
      expect(String(afterValues[writableKey] ?? '')).toBe(tag);
    } else {
      test.info().annotations.push({
        type: 'addon-roundtrip',
        description: 'no string field in schema — skipped write step',
      });
    }
  });
}
