import { expect, test } from "@playwright/test";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { messages, waitForMessage } from "../harness/index.mjs";
import { ulamSuite } from "./ulam-common.mjs";

const dir = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "ulam", "scottish-book");
const problems = [1, 19, 38, 43, 59, 152, 153, 184, 193];
const suite = ulamSuite({ test, expect }, { name: "ulam-scottish-book", dir, steps: ["intro", ...problems.map((n) => `problem-${n}`)] });

test("ulam-scottish-book: the cards are filtered by poser and the sources are on the single card", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "intro" });
  await waitForMessage(page, "stepChanged", { step: "intro" });
  await expect(frame.locator("#banner")).toHaveCount(0);
  await expect(frame.locator(".sb-card")).toHaveCount(9);
  await expect(frame.locator("#score")).toContainText("9 problems, 4 of them to guess");
  await frame.locator("#poser").selectOption("Hugo Steinhaus");
  await expect(frame.locator(".sb-card")).toHaveCount(2);
  await expect(frame.locator(".sb-card h3").first()).toHaveText("Problem 152");
  await expect(frame.locator(".sb-card").first()).toContainText("caviar");
  await frame.locator("#poser").selectOption("");
  await expect(frame.locator(".sb-card")).toHaveCount(9);
  await page.evaluate(() => window.__host.goToStep("problem-193"));
  await waitForMessage(page, "stepChanged", { step: "problem-193" });
  await expect(frame.locator(".sb-card")).toContainText("What became of it: It is the last entry in the book.");
  await expect(frame.locator(".sb-card")).toContainText("Sources: MacTutor, The Scottish Book");
  await expect(frame.locator(".sb-guess")).toHaveCount(0); // nothing to guess on this card
});

test("ulam-scottish-book: guessing is keyboard-usable, every guess sends a score and the third right guess passes", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "problem-19" });
  await waitForMessage(page, "stepChanged", { step: "problem-19" });
  await expect(frame.locator(".sb-card")).toHaveCount(1);
  const check = frame.locator(".sb-actions button");
  await expect(check).toBeDisabled();
  await frame.locator('input[value="yes"]').focus();
  await page.keyboard.press("ArrowDown"); // a radio group moves with the arrows: "No"
  await expect(frame.locator('input[value="no"]')).toBeChecked();
  await expect(check).toBeEnabled();
  await check.focus();
  await page.keyboard.press("Enter");
  await expect(frame.locator(".sb-result")).toContainText("Correct: No. No. In 2022");
  await waitForMessage(page, "score");
  let scores = await messages(page, "score");
  expect(scores.map((s) => [s.raw, s.max, s.passed])).toEqual([[1, 4, false]]);
  await expect(frame.locator("#score")).toContainText("1 of 1 guesses right so far (4 problems to guess, 9 problems in all).");
  // a wrong guess on the next card
  await page.evaluate(() => window.__host.goToStep("problem-153"));
  await waitForMessage(page, "stepChanged", { step: "problem-153" });
  await frame.locator('input[value="yes"]').check();
  await frame.locator(".sb-actions button").click();
  await expect(frame.locator(".sb-result")).toContainText("Not quite");
  await expect(frame.locator(".sb-result")).toHaveClass(/wrong/);
  // the third guess, right: two right out of three so far is not yet 60 percent of four, the fourth passes
  await page.evaluate(() => window.__host.goToStep("problem-59"));
  await waitForMessage(page, "stepChanged", { step: "problem-59" });
  await frame.locator('input[value="yes"]').check();
  await frame.locator(".sb-actions button").click();
  await page.evaluate(() => window.__host.goToStep("problem-184"));
  await waitForMessage(page, "stepChanged", { step: "problem-184" });
  await frame.locator('input[value="yes"]').check();
  await frame.locator(".sb-actions button").click();
  await expect.poll(async () => (await messages(page, "score")).length).toBe(4);
  scores = await messages(page, "score");
  expect(scores.map((s) => [s.raw, s.max, s.passed])).toEqual([[1, 4, false], [1, 4, false], [2, 4, false], [3, 4, true]]);
  expect(await messages(page, "complete")).toEqual([]); // the topic completes by its score rule, not by the package
  // the answered card stays answered when we come back
  await page.evaluate(() => window.__host.goToStep("problem-19"));
  await waitForMessage(page, "stepChanged", { step: "problem-19" });
  await expect(frame.locator(".sb-result")).toContainText("Correct");
  await expect(frame.locator('input[value="no"]')).toBeDisabled();
});

test("ulam-scottish-book: card text is shown as text, never as markup", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "intro" });
  await waitForMessage(page, "stepChanged", { step: "intro" });
  expect(await frame.locator(".sb-card").first().evaluate((e) => e.querySelectorAll("script, img, a").length)).toBe(0);
});
