import { test, expect } from '@playwright/test';
import { slug } from '../../utils/factories';

// Run unauthenticated (no admin storage state) so the rate limiter applies.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('admin: /auth/web-register rate limiter', () => {
  test('after the threshold attempts, an unauthenticated client gets 429 rate_limited', async ({
    browser,
  }) => {
    // The rate limiter is keyed by IP, so a fresh browser context per attempt
    // still counts toward the same bucket. We use fresh contexts to avoid the
    // "auto-login after success" side effect (which would invalidate the
    // anonymous wp_rest nonce for subsequent attempts).
    const statuses: number[] = [];
    let last429 = false;
    for (let i = 0; i < 14; i++) {
      const ctx = await browser.newContext();
      const page = await ctx.newPage();
      try {
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        const nonce = await page.evaluate(() => {
          const w = window as unknown as { sikshyaFrontend?: { restNonce?: string } };
          return w.sikshyaFrontend?.restNonce ?? '';
        });
        if (!nonce) {
          throw new Error('sikshyaFrontend.restNonce was empty');
        }
        const email = `${slug('rl')}@example.com`;
        const res = await page.request.post('/wp-json/sikshya/v1/auth/web-register', {
          headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
          data: { email, password: 'WhateverPass!234' },
        });
        statuses.push(res.status());
        if (res.status() === 429) {
          last429 = true;
          const body = await res.json().catch(() => ({}));
          expect(String(body?.code ?? '')).toBe('rate_limited');
          break;
        }
      } finally {
        await ctx.close();
      }
    }
    expect(last429, `expected at least one 429 within 14 attempts; statuses=${JSON.stringify(statuses)}`).toBe(true);
  });
});
