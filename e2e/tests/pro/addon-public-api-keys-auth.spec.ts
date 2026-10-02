import { test, expect } from '@playwright/test';
import { getAdminNonce, slug } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

/**
 * Public API key lifecycle: issue, authenticate, reject, revoke.
 *
 * The existing spec lists keys. This exercises what the keys are for — an
 * unauthenticated, internet-facing surface guarded only by a bearer token — so the
 * properties that matter are the negative ones: no token is refused, a wrong token
 * is refused, and a revoked token stops working. A revoked key that still opens the
 * API is the failure mode that would matter most.
 */
const PING = '/wp-json/sikshya/v1/scale/public-api/ping';

test.describe('addon: public API key authentication', () => {
  test('a key authenticates the public API, and stops working once revoked', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);
    const enable = await request.post('/wp-json/sikshya/v1/admin/addons/public_api_keys/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });
    test.skip(
      enable.status() === 402 || enable.status() === 403,
      'public_api_keys is not included in this licence',
    );

    // Control first: with no credential the endpoint must refuse. If this passes
    // for the wrong reason (e.g. the route is absent) the rest proves nothing, so
    // the status is pinned to 401 rather than "any error".
    const anonymous = await request.get(PING);
    const anonText = await anonymous.text();
    test.info().attach('ping-no-token', { body: `${anonymous.status()} ${anonText.slice(0, 200)}` });
    expect(
      anonymous.status(),
      `the public API must require a bearer token; got ${anonymous.status()}: ${anonText.slice(0, 200)}`,
    ).toBe(401);

    // Issue a key.
    const label = `E2E key ${slug('k')}`;
    const create = await request.post('/wp-json/sikshya/v1/scale/public-api/keys', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: { label },
    });
    const createText = await create.text();
    test.info().attach('key-create', { body: createText.slice(0, 500) });
    expect(create.status(), `key creation failed: ${createText.slice(0, 250)}`).toBeLessThan(400);

    const createBody = JSON.parse(createText) as Record<string, unknown>;
    // The plaintext key is returned once, at creation, and never again.
    const plain = String(
      createBody.key ??
        createBody.api_key ??
        createBody.plain ??
        (createBody.data as Record<string, unknown>)?.key ??
        '',
    );
    const keyId = Number(
      createBody.id ?? (createBody.data as Record<string, unknown>)?.id ?? createBody.key_id ?? 0,
    );
    expect(
      plain.length,
      `the plaintext key must be returned at creation: ${createText.slice(0, 300)}`,
    ).toBeGreaterThan(0);
    expect(keyId, 'the key must have an id for revocation').toBeGreaterThan(0);

    // It must authenticate.
    const authed = await request.get(PING, { headers: { Authorization: `Bearer ${plain}` } });
    const authedText = await authed.text();
    test.info().attach('ping-with-token', { body: `${authed.status()} ${authedText.slice(0, 250)}` });
    expect(
      authed.ok(),
      `a freshly issued key must authenticate; got ${authed.status()}: ${authedText.slice(0, 200)}`,
    ).toBeTruthy();

    // A tampered key must not.
    const forged = `${plain.slice(0, -6)}abc123`;
    const bad = await request.get(PING, { headers: { Authorization: `Bearer ${forged}` } });
    expect(
      bad.status(),
      'a tampered key must be refused, not accepted on a prefix match',
    ).toBe(401);

    // Revoke it, and it must stop working.
    const revoke = await request.delete(`/wp-json/sikshya/v1/scale/public-api/keys/${keyId}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const revokeText = await revoke.text();
    test.info().attach('key-revoke', { body: `${revoke.status()} ${revokeText.slice(0, 200)}` });
    expect(revoke.status(), `revocation failed: ${revokeText.slice(0, 250)}`).toBeLessThan(400);

    const afterRevoke = await request.get(PING, {
      headers: { Authorization: `Bearer ${plain}` },
    });
    const afterText = await afterRevoke.text();
    test.info().attach('ping-after-revoke', {
      body: `${afterRevoke.status()} ${afterText.slice(0, 250)}`,
    });
    expect(
      afterRevoke.status(),
      `a revoked key must stop authenticating; got ${afterRevoke.status()}: ${afterText.slice(0, 200)}`,
    ).toBe(401);
  });
});
