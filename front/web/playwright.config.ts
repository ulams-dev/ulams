import { defineConfig, devices } from "@playwright/test";

/**
 * Smoke tests against a running server (default: the one on :4321, see README).
 * WEB_BASE_PORT changes the port; the tenant hosts are <slug>.app.localhost.
 */
export default defineConfig({
  testDir: "tests/e2e",
  timeout: 60_000,
  // the first request to a cold page waits for the PHP API
  expect: { timeout: 15_000 },
  retries: process.env.CI ? 1 : 0,
  reporter: [["list"]],
  use: { ...devices["Desktop Chrome"], trace: "retain-on-failure" },
  projects: [
    { name: "desktop", use: { viewport: { width: 1440, height: 900 } } },
    { name: "phone", use: { ...devices["Pixel 7"], viewport: { width: 360, height: 780 } } },
  ],
});
