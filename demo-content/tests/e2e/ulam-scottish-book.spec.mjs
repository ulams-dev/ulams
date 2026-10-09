import { expect, test } from "@playwright/test";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { messages, waitForMessage } from "../harness/index.mjs";
import { ulamSuite } from "./ulam-common.mjs";

const dir = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "ulam", "scottish-book");
const suite = ulamSuite({ test, expect }, { name: "ulam-scottish-book", dir, steps: ["intro", "problem-19", "problem-153", "problem-193"] });

test("ulam-scottish-book: the cards are filtered by poser, with a visible placeholder notice", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "intro" });
  await waitForMessage(page, "stepChanged", { step: "intro" });
  await expect(frame.locator("#banner")).toBeVisible();
  await expect(frame.locator("#banner")).toContainText("not facts");
  await expect(frame.locator(".sb-card")).toHaveCount(3);
  await frame.locator("#poser").selectOption("Placeholder poser B");
  await expect(frame.locator(".sb-card")).toHaveCount(1);
  await expect(frame.locator(".sb-card h3")).toHaveText("Problem 153");
  await frame.locator("#poser").selectOption("");
  await expect(frame.locator(".sb-card")).toHaveCount(3);
});

test("ulam-scottish-book: guessing is keyboard-usable, every guess sends a score and the third right guess passes", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "problem-19" });
  await waitForMessage(page, "stepChanged", { step: "problem-19" });
  await expect(frame.locator(".sb-card")).toHaveCount(1);
  const check = frame.locator(".sb-actions button");
  await expect(check).toBeDisabled();
  await frame.locator('input[value="disproved"]').focus();
  await page.keyboard.press("Space"); // choose with the keyboard
  await expect(check).toBeEnabled();
  await check.focus();
  await page.keyboard.press("Enter");
  await expect(frame.locator(".sb-result")).toContainText("Correct: Answered the other way.");
  let scores = await messages(page, "score");
  expect(scores.map((s) => [s.raw, s.max, s.passed])).toEqual([[1, 3, false]]);
  await expect(frame.locator("#score")).toContainText("1 of 1 guesses right so far (3 problems in all).");
  // a wrong guess on the next card
  await page.evaluate(() => window.__host.goToStep("problem-153"));
  await waitForMessage(page, "stepChanged", { step: "problem-153" });
  await frame.locator('input[value="open"]').check();
  await frame.locator(".sb-actions button").click();
  await expect(frame.locator(".sb-result")).toContainText("Not quite");
  await expect(frame.locator(".sb-result")).toHaveClass(/wrong/);
  // the third, right
  await page.evaluate(() => window.__host.goToStep("problem-193"));
  await waitForMessage(page, "stepChanged", { step: "problem-193" });
  await frame.locator('input[value="open"]').check();
  await frame.locator(".sb-actions button").click();
  scores = await messages(page, "score");
  expect(scores.map((s) => [s.raw, s.max, s.passed])).toEqual([[1, 3, false], [1, 3, false], [2, 3, true]]);
  expect(await messages(page, "complete")).toEqual([]); // the topic completes by its score rule, not by the package
  // the answered card stays answered when we come back
  await page.evaluate(() => window.__host.goToStep("problem-19"));
  await waitForMessage(page, "stepChanged", { step: "problem-19" });
  await expect(frame.locator(".sb-result")).toContainText("Correct");
  await expect(frame.locator('input[value="disproved"]')).toBeDisabled();
});

test("ulam-scottish-book: card text is shown as text, never as markup", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "intro" });
  await waitForMessage(page, "stepChanged", { step: "intro" });
  expect(await frame.locator(".sb-card").first().evaluate((e) => e.querySelectorAll("script, img, a").length)).toBe(0);
});
