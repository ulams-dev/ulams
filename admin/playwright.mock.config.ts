import type { PlaywrightTestConfig } from '@playwright/test';
import { devices } from '@playwright/test';

/**
 * Admin browser tests that need no backend: the built admin is served statically and every API call is
 * answered by `src/e2e/mocked/mockApi.ts`. This is what the `admin-a11y` CI job runs; the other specs in
 * `src/e2e` need a live API. Locally: `yarn playwright install chromium` once, `yarn build` with
 * REACT_APP_API_URL=http://api.mock, then `yarn playwright:mock`.
 */
const port = process.env.ADMIN_MOCK_PORT ?? '4394';

const config: PlaywrightTestConfig = {
  testDir: 'src/e2e/mocked',
  timeout: 90_000,
  expect: { timeout: 20_000 },
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 2 : undefined,
  reporter: [['list']],
  outputDir: 'test-results',
  use: {
    ...devices['Desktop Chrome'],
    baseURL: `http://127.0.0.1:${port}`,
    viewport: { width: 1440, height: 900 },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium' }],
  webServer: {
    command: 'node scripts/serve-dist.cjs',
    url: `http://127.0.0.1:${port}/`,
    env: { PORT: port, RUNTIME_CONFIG: JSON.stringify({ REACT_APP_API_URL: 'http://api.mock' }) },
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
  },
};
export default config;
