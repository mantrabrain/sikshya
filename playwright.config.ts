import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

dotenv.config({ path: path.resolve(__dirname, 'e2e/.env') });

const baseURL = process.env.WP_BASE_URL ?? 'http://sikshya.local';

/**
 * Cross-browser projects are opt-in via `PW_CROSS_BROWSER=1`.
 *
 * The suite is serial (`workers: 1`), so enabling WebKit and Firefox by default
 * would triple every local and CI run for engines that rarely regress. Run them
 * deliberately — before a release, or when touching front-end CSS/JS:
 *
 *   PW_CROSS_BROWSER=1 npx playwright test
 *   PW_CROSS_BROWSER=1 npx playwright test --project=admin-webkit
 */
const crossBrowser = process.env.PW_CROSS_BROWSER === '1';

const adminMatch = /tests\/(smoke|admin|pro|a11y\/admin)\/.*\.spec\.ts$/;
const publicMatch = /tests\/(learner|checkout|a11y\/public)\/.*\.spec\.ts$/;
const storageState = 'e2e/.auth/admin.json';

export default defineConfig({
  testDir: './e2e/tests',
  outputDir: './test-results',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : [['list'], ['html', { open: 'never' }]],
  // Several specs sign two or three real users in through wp-login.php against a
  // dev server with few PHP workers, so a single test legitimately spans more
  // than a minute. 60s was being hit by exactly those specs.
  timeout: 120_000,
  expect: { timeout: 10_000 },

  globalSetup: './e2e/global-setup.ts',

  use: {
    baseURL,
    headless: true,
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },

  projects: [
    {
      name: 'admin',
      testMatch: adminMatch,
      use: { ...devices['Desktop Chrome'], storageState },
    },
    {
      name: 'public',
      testMatch: publicMatch,
      use: { ...devices['Desktop Chrome'], storageState },
    },
    ...(crossBrowser
      ? [
          {
            name: 'admin-webkit',
            testMatch: adminMatch,
            use: { ...devices['Desktop Safari'], storageState },
          },
          {
            name: 'public-webkit',
            testMatch: publicMatch,
            use: { ...devices['Desktop Safari'], storageState },
          },
          {
            name: 'admin-firefox',
            testMatch: adminMatch,
            use: { ...devices['Desktop Firefox'], storageState },
          },
          {
            name: 'public-firefox',
            testMatch: publicMatch,
            use: { ...devices['Desktop Firefox'], storageState },
          },
        ]
      : []),
  ],
});
