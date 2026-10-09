import { defineConfig, devices } from "@playwright/test";

/**
 * The package tests start their own throwaway servers on free ports (see harness/index.mjs); no stack
 * and never :4321. WebGL runs on SwiftShader (software), so the gravity scene works in headless Chromium.
 */
export default defineConfig({
  testDir: "e2e",
  outputDir: "../test-results",
  timeout: 120_000,
  expect: { timeout: 20_000 },
  workers: 1,
  reporter: [["list"]],
  use: {
    ...devices["Desktop Chrome"],
    viewport: { width: 1280, height: 720 },
    launchOptions: { args: ["--use-angle=swiftshader", "--enable-unsafe-swiftshader", "--ignore-gpu-blocklist"] },
    trace: "retain-on-failure",
  },
});
