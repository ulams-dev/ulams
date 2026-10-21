import { defineConfig, devices } from "@playwright/test";

/**
 * Accessibility suite for CI and local runs without the API stack: the built reference frontend
 * (`yarn build` first) against a mock tenant API (tests/mock-api). Run it with
 * `yarn workspace @ulams/web test:a11y`. The full-stack scan of every page type stays in
 * tests/e2e/a11y.spec.ts.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const api = process.env.MOCK_API_PORT ?? "4390";

export default defineConfig({
  testDir: "tests/a11y",
  timeout: 60_000,
  expect: { timeout: 15_000 },
  retries: process.env.CI ? 1 : 0,
  reporter: [["list"]],
  workers: process.env.CI ? 2 : undefined,
  use: { ...devices["Desktop Chrome"], trace: "retain-on-failure" },
  projects: [
    { name: "desktop", use: { viewport: { width: 1440, height: 900 } } },
    { name: "phone", use: { ...devices["Pixel 7"], viewport: { width: 360, height: 780 } } },
  ],
  webServer: [
    {
      command: "node tests/mock-api/server.mjs",
      url: `http://127.0.0.1:${api}/api/settings`,
      env: { MOCK_API_PORT: api },
      reuseExistingServer: !process.env.CI,
    },
    {
      command: "node ./dist/server/entry.mjs",
      url: `http://app.localhost:${web}/healthz`,
      reuseExistingServer: !process.env.CI,
      env: {
        HOST: "127.0.0.1",
        PORT: web,
        NODE_ENV: "production",
        ULAMS_TENANT_HOSTS: "{slug}.app.localhost=>http://{slug}.localhost",
        ULAMS_API_INTERNAL_ORIGIN: `http://127.0.0.1:${api}`,
        ULAMS_PLATFORM_HOSTS: "app.localhost",
        ULAMS_DEMO_TENANTS: "coffee,nightsky,polski,zhongwen,nocpl,noczh",
        ULAMS_WARM_TENANTS: "",
        ULAMS_DEFAULT_TENANT: "",
        ULAMS_CONTENT_ORIGIN: "",
        ULAMS_CACHE_TTL: "600",
        ULAMS_COOKIE_SECURE: "false",
        ULAMS_SOCIAL_LOGIN: "google,facebook",
      },
    },
  ],
});
