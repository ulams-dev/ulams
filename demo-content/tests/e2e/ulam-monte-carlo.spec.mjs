import { expect, test } from "@playwright/test";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { messages, waitForMessage } from "../harness/index.mjs";
import { ulamSuite } from "./ulam-common.mjs";

const dir = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "ulam", "monte-carlo");
const suite = ulamSuite({ test, expect }, { name: "ulam-monte-carlo", dir, steps: ["idea", "throw", "converge", "error"] });

test("ulam-monte-carlo: the buttons throw points by keyboard, the estimate and the seed are repeatable, 10,000 throws complete it", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "throw" });
  await waitForMessage(page, "stepChanged", { step: "throw" });
  await expect(frame.locator("#result")).toContainText("No points yet (seed 2026)");
  const plus100 = frame.locator('#buttons button[data-n="100"]');
  await plus100.focus();
  await page.keyboard.press("Enter");
  await expect(frame.locator("#result")).toContainText("100 throws");
  expect(await messages(page, "complete")).toEqual([]);
  await frame.locator("#reset").focus();
  await page.keyboard.press("Enter");
  await expect(frame.locator("#result")).toContainText("No points yet");
  const big = frame.locator('#buttons button[data-n="10000"]');
  await big.focus();
  await page.keyboard.press("Enter");
  await expect(frame.locator("#result")).toContainText("10,000 throws, 7,812 inside the quarter circle. Estimate of π: 3.1248.");
  await waitForMessage(page, "complete");
  await big.focus();
  await page.keyboard.press("Enter");
  await expect(frame.locator("#result")).toContainText("20,000 throws");
  expect((await messages(page, "complete")).length).toBe(1); // once
  await expect(frame.locator("#square")).toHaveAttribute("aria-label", /20,000 random points/);
});

test("ulam-monte-carlo: another seed gives other points, the same seed the same ones, the chart and its table follow", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "converge" });
  await waitForMessage(page, "stepChanged", { step: "converge" });
  await expect(frame.locator("#chart-box")).toBeVisible();
  await frame.locator("#seed").fill("12345");
  await frame.locator("#seed").press("Enter");
  for (let i = 0; i < 10; i++) await frame.locator('#buttons button[data-n="10000"]').click();
  await expect(frame.locator("#result")).toContainText("100,000 throws, 78,490 inside the quarter circle. Estimate of π: 3.1396.");
  await frame.locator(".mc-table summary").click();
  expect(await frame.locator("#data tbody tr").count()).toBeGreaterThan(20);
  await expect(frame.locator("#chart")).toHaveAttribute("aria-label", /error is 0\.0020/);
  await frame.locator("#reset").click();
  await frame.locator('#buttons button[data-n="10000"]').click();
  await expect(frame.locator("#result")).toContainText("10,000 throws, 7,922 inside"); // seed 12345 again, first 10,000 points
});

test("ulam-monte-carlo: the chart belongs to the last two steps and the idea step is a picture without buttons", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "idea" });
  await waitForMessage(page, "stepChanged", { step: "idea" });
  await expect(frame.locator("#chart-box")).toBeHidden();
  await expect(frame.locator('#buttons button[data-n="100"]')).toBeDisabled();
  await page.evaluate(() => window.__host.goToStep("throw"));
  await waitForMessage(page, "stepChanged", { step: "throw" });
  await expect(frame.locator('#buttons button[data-n="100"]')).toBeEnabled();
  await expect(frame.locator("#chart-box")).toBeHidden();
  await page.evaluate(() => window.__host.goToStep("error"));
  await waitForMessage(page, "stepChanged", { step: "error" });
  await expect(frame.locator("#chart-box")).toBeVisible();
});
