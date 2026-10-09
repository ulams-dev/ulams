import { expect, test } from "@playwright/test";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { messages, waitForMessage } from "../harness/index.mjs";
import { inFrame, ulamSuite } from "./ulam-common.mjs";

const dir = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "ulam", "spiral");
const suite = ulamSuite({ test, expect }, { name: "ulam-spiral", dir, steps: ["grid", "primes", "diagonals", "explore"], completeAt: "explore" });

test("ulam-spiral: keyboard only, the arrow keys read the numbers along the spiral", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "grid" });
  await waitForMessage(page, "stepChanged", { step: "grid" });
  const canvas = frame.locator("#sp");
  await canvas.focus();
  await expect(frame.locator("#cell")).toContainText("1 is not prime"); // the first number is selected to begin with
  await page.keyboard.press("Enter");
  await expect(frame.locator("#cell")).toContainText("1 is not prime");
  await page.keyboard.press("ArrowRight");
  await expect(frame.locator("#cell")).toContainText("2 is prime");
  await page.keyboard.press("ArrowUp");
  await expect(frame.locator("#cell")).toContainText("3 is prime");
  await page.keyboard.press("ArrowLeft");
  await expect(frame.locator("#cell")).toContainText("4 is not prime: it is divisible by 2 (a perfect square)");
  await page.keyboard.press("Home");
  await expect(frame.locator("#cell")).toContainText("1 is not prime");
});

test("ulam-spiral: each step's picture and summary are right, and the controls change them", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "primes" });
  await waitForMessage(page, "stepChanged", { step: "primes" });
  await expect(frame.locator("#summary")).toContainText("Numbers 1 to 2,500: 2,500 numbers, 367 primes (14.7%).");
  await expect(frame.locator("#show-primes")).toBeChecked();
  await page.evaluate(() => window.__host.goToStep("diagonals"));
  await waitForMessage(page, "stepChanged", { step: "diagonals" });
  await expect(frame.locator("#summary")).toContainText("Numbers 41 to 1,640");
  await expect(frame.locator("#summary")).toContainText("40 of 40 values of n² + n + 41 are prime (100.0%)");
  await page.evaluate(() => window.__host.goToStep("explore"));
  await waitForMessage(page, "complete");
  await frame.locator("#count").fill("100");
  await frame.locator("#count").press("Enter");
  await frame.locator("#start").fill("1");
  await frame.locator("#start").press("Enter");
  await expect(frame.locator("#summary")).toContainText("100 numbers, 25 primes (25.0%)");
  await frame.locator("#show-poly").check();
  await expect(frame.locator("#summary")).toContainText("8 of 8 values of n² + n + 41 are prime");
  await frame.locator("#start").fill("0");
  await frame.locator("#start").press("Tab");
  await expect(frame.locator("#start")).toHaveValue("1"); // clamped
});

test("ulam-spiral: a big spiral (40,000 numbers) draws and stays keyboard-usable", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "explore" });
  await waitForMessage(page, "stepChanged", { step: "explore" });
  await frame.locator("#count").fill("40000");
  await frame.locator("#count").press("Enter");
  await expect(frame.locator("#summary")).toContainText("40,000 numbers, 4,203 primes");
  await frame.locator("#sp").focus();
  await page.keyboard.press("ArrowRight");
  await expect(frame.locator("#cell")).toContainText("2 is prime");
  expect(await messages(page, "error")).toEqual([]);
});
