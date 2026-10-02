import { expect, type APIRequestContext } from '@playwright/test';

/**
 * Settings helpers that do not clobber their neighbours.
 *
 * `/settings/save` REPLACES the tab it is given: any key belonging to that tab
 * which is absent from `values` is reset to its default. That is reasonable for
 * the admin UI, which posts the whole tab and relies on "absent means unchecked"
 * to clear a checkbox — but it makes a partial save destructive. Two specs in
 * this suite independently hit it: one enabled coupons and silently switched the
 * offline gateway off, breaking a later checkout spec depending on run order.
 *
 * Always read the tab, apply a patch, write the whole tab back.
 */
export async function readSettingsTab(
  request: APIRequestContext,
  nonce: string,
  tab: string,
): Promise<Record<string, unknown>> {
  const res = await request.get(`/wp-json/sikshya/v1/settings/values?tab=${encodeURIComponent(tab)}`, {
    headers: { 'X-WP-Nonce': nonce },
  });
  const body = await res.json().catch(() => ({}));
  expect(res.ok(), `GET settings/values?tab=${tab} failed`).toBeTruthy();
  return (body?.data?.values ?? body?.values ?? {}) as Record<string, unknown>;
}

export async function writeSettingsTab(
  request: APIRequestContext,
  nonce: string,
  tab: string,
  values: Record<string, unknown>,
): Promise<void> {
  const res = await request.post('/wp-json/sikshya/v1/settings/save', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { tab, values },
  });
  expect(res.ok(), `settings/save tab=${tab} failed: ${(await res.text()).slice(0, 200)}`).toBeTruthy();
}

/**
 * Apply `patch` to a tab while preserving every other key in it.
 * Returns the original tab so a test can restore it verbatim afterwards.
 */
export async function patchSettingsTab(
  request: APIRequestContext,
  nonce: string,
  tab: string,
  patch: Record<string, unknown>,
): Promise<Record<string, unknown>> {
  const original = await readSettingsTab(request, nonce, tab);
  await writeSettingsTab(request, nonce, tab, { ...original, ...patch });
  return original;
}
