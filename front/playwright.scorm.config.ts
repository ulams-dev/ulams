import { defineConfig, devices } from "@playwright/test";

/**
 * SCORM playback specs for the React player (front/src/lib/scorm-player), run once against each app's own
 * service worker: the legacy front (front/public) and the admin (admin/public). No API stack and no network:
 * tests/scorm-playback/server.mjs builds the harness page and answers the LMS calls itself.
 * Locally: `yarn playwright install chromium` once, then `yarn test:scorm` (UPDATE_GOLDEN=1 rewrites the
 * golden traces; see api/packages/scorm/tests/fixtures/playback/README.md).
 */
const frontPort = Number(process.env.SCORM_FRONT_PORT ?? 4610);
const adminPort = Number(process.env.SCORM_ADMIN_PORT ?? 4611);

export default defineConfig({
  testDir: "tests/scorm-playback",
  testMatch: "*.spec.ts",
  timeout: 60_000,
  expect: { timeout: 20_000 },
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  // one worker: the mock server records the requests of one test at a time
  workers: 1,
  reporter: [["list"]],
  outputDir: "test-results/pw",
  use: { ...devices["Desktop Chrome"], trace: "retain-on-failure" },
  projects: [
    { name: "legacy-front", metadata: { app: "front" }, use: { baseURL: `http://127.0.0.1:${frontPort}` } },
    { name: "admin", metadata: { app: "admin" }, use: { baseURL: `http://127.0.0.1:${adminPort}` } },
  ],
  webServer: [
    {
      command: `node tests/scorm-playback/server.mjs --app front --port ${frontPort}`,
      url: `http://127.0.0.1:${frontPort}/`,
      reuseExistingServer: !process.env.CI,
      timeout: 120_000,
    },
    {
      command: `node tests/scorm-playback/server.mjs --app admin --port ${adminPort}`,
      url: `http://127.0.0.1:${adminPort}/`,
      reuseExistingServer: !process.env.CI,
      timeout: 120_000,
    },
  ],
});
