import { test, expect } from '@playwright/test';
import { getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/quiz-advanced\//.test(r)),
    'quiz_advanced routes not registered',
  );
});

test.describe('addon: quiz_advanced bank-terms + pool-preview', () => {
  test('bank-terms returns terms array; pool-preview rejects empty tag', async ({
    page,
    request,
  }) => {
    const nonce = await getAdminNonce(page);

    await request.post('/wp-json/sikshya/v1/admin/addons/quiz_advanced/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const terms = await request.get('/wp-json/sikshya/v1/pro/quiz-advanced/bank-terms', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const termsBody = await terms.json().catch(() => ({}));
    expect(terms.ok(), JSON.stringify(termsBody).slice(0, 200)).toBeTruthy();
    expect(termsBody?.ok).toBe(true);
    expect(Array.isArray(termsBody?.terms ?? [])).toBe(true);

    // pool-preview with no tag should 400.
    const empty = await request.get('/wp-json/sikshya/v1/pro/quiz-advanced/pool-preview', {
      headers: { 'X-WP-Nonce': nonce },
    });
    const emptyBody = await empty.json().catch(() => ({}));
    expect(empty.status(), JSON.stringify(emptyBody).slice(0, 200)).toBe(400);

    // pool-preview with a tag returns the breakdown envelope.
    const preview = await request.get(
      '/wp-json/sikshya/v1/pro/quiz-advanced/pool-preview?tag=e2e-nonexistent-tag',
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const previewBody = await preview.json().catch(() => ({}));
    expect(preview.ok(), JSON.stringify(previewBody).slice(0, 200)).toBeTruthy();
    expect(previewBody?.ok).toBe(true);
    expect(typeof previewBody?.combined_count).toBe('number');
    expect(Array.isArray(previewBody?.sample_question_ids ?? [])).toBe(true);
  });
});
