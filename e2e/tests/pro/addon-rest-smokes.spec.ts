import { test, expect, type APIRequestContext, type Page } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

/**
 * Per-addon REST smoke: hits the addon's primary GET endpoint and asserts the
 * server returns `ok: true` (or equivalent) with the expected shape. These
 * are *operational* smokes — they prove the addon boots, registers routes,
 * runs its query, and serializes a response. The deeper write paths for the
 * impactful flows live in their own dedicated specs.
 */

type Probe = {
  addon: string;
  path: string;
  /** Selector for "this response is healthy". */
  okWhen: (body: unknown, status: number) => boolean;
};

const PROBES: Probe[] = [
  {
    addon: 'certificates_advanced',
    path: '/wp-json/sikshya/v1/pro/certificates/advanced',
    okWhen: (b, s) =>
      s < 400 && typeof b === 'object' && b !== null && (b as { ok?: boolean }).ok === true,
  },
  {
    addon: 'activity_log',
    path: '/wp-json/sikshya/v1/pro/extended/activity-log?per_page=5',
    okWhen: (b, s) => s < 400 && typeof b === 'object' && b !== null,
  },
  {
    addon: 'gradebook',
    path: '/wp-json/sikshya/v1/pro/gradebook',
    okWhen: (b, s) => s < 400,
  },
  {
    addon: 'quiz_advanced',
    path: '/wp-json/sikshya/v1/pro/quiz-advanced/bank-terms',
    okWhen: (b, s) => s < 400,
  },
  {
    addon: 'enterprise_reports',
    path: '/wp-json/sikshya/v1/pro/enterprise-reports/v2/dashboard',
    okWhen: (b, s) => s < 400,
  },
  {
    addon: 'social_login',
    path: '/wp-json/sikshya/v1/pro/social-login',
    okWhen: (b, s) => s < 400,
  },
  {
    addon: 'white_label',
    path: '/wp-json/sikshya/v1/pro/white-label',
    okWhen: (b, s) => s < 400,
  },
  {
    addon: 'calendar',
    path: '/wp-json/sikshya/v1/pro/extended/calendar?limit=10',
    okWhen: (b, s) => s < 400,
  },
  {
    addon: 'email_marketing',
    path: '/wp-json/sikshya/v1/pro/email-marketing/rules',
    okWhen: (b, s) => s < 400,
  },
  {
    addon: 'subscriptions',
    path: '/wp-json/sikshya/v1/pro/plans',
    okWhen: (b, s) => s < 400,
  },
  {
    addon: 'scorm_h5p_pro',
    path: '/wp-json/sikshya/v1/pro/scorm-h5p/reports/courses/1',
    okWhen: (b, s) => s < 400 || s === 404, // 404 is OK when course doesn't have SCORM data
  },
];

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(!routes.some((r) => /\/pro\//.test(r)), 'Sikshya Pro not active');
});

const enableAddon = async (request: APIRequestContext, page: Page, addonId: string) => {
  const nonce = await getAdminNonce(page);
  await request.post(`/wp-json/sikshya/v1/admin/addons/${addonId}/enable`, {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
  });
};

for (const probe of PROBES) {
  test(`addon REST smoke · ${probe.addon} responds at ${probe.path.split('/sikshya/v1')[1]}`, async ({
    page,
    request,
  }) => {
    await enableAddon(request, page, probe.addon);
    const nonce = await getAdminNonce(page);

    const res = await request.get(probe.path, { headers: { 'X-WP-Nonce': nonce } });
    const status = res.status();
    const body = await res.json().catch(() => null);

    test.info().annotations.push({
      type: 'response-meta',
      description: `status=${status} bodyType=${typeof body}`,
    });

    expect(probe.okWhen(body, status), `body: ${JSON.stringify(body).slice(0, 200)}`).toBe(true);
  });
}
