import { expect, test } from "@playwright/test";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { messages, waitForMessage } from "../harness/index.mjs";
import { ulamSuite } from "./ulam-common.mjs";

const dir = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "ulam", "automaton");
const suite = ulamSuite({ test, expect }, { name: "ulam-automaton", dir, steps: ["rule30", "rule90", "rule110", "life", "ulam-growth"], completeAt: "the third step visited" });

test("ulam-automaton: Step, Play, Pause, Run to the end and the rule number work with the keyboard", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "rule30" });
  await waitForMessage(page, "stepChanged", { step: "rule30" });
  await expect(frame.locator("#status")).toContainText("Rule 30, generation 0: 1 cells on in this row.");
  await frame.locator("#step").focus();
  for (let i = 0; i < 3; i++) await page.keyboard.press("Enter");
  await expect(frame.locator("#status")).toContainText("Rule 30, generation 3: 6 cells on in this row.");
  await frame.locator("#play").focus();
  await page.keyboard.press("Enter");
  await expect(frame.locator("#play")).toHaveAttribute("aria-pressed", "true");
  await expect(frame.locator("#status")).toContainText(/generation (\d{2,3})/);
  await page.keyboard.press("Enter"); // pause
  await expect(frame.locator("#play")).toHaveText("Play");
  const paused = await frame.locator("#status").textContent();
  await page.waitForTimeout(600);
  expect(await frame.locator("#status").textContent()).toBe(paused);
  await frame.locator("#run").click();
  await expect(frame.locator("#status")).toContainText("generation 100");
  await expect(frame.locator("#status")).toContainText("The picture is full");
  await frame.locator("#rule").fill("90");
  await frame.locator("#rule").press("Enter");
  await expect(frame.locator("#status")).toContainText("Rule 90, generation 0");
  await frame.locator("#run").click();
  await expect(frame.locator("#status")).toContainText("Rule 90, generation 100: 8 cells on in this row."); // 100 = 1100100 in binary: 3 ones, 2^3 cells
  await frame.locator("#rule").fill("999");
  await frame.locator("#rule").press("Enter");
  await expect(frame.locator("#rule")).toHaveValue("255");
});

test("ulam-automaton: the Game of Life grid can be edited with the arrow keys and Space, and steps by the rule", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "life" });
  await waitForMessage(page, "stepChanged", { step: "life" });
  await expect(frame.locator("#status")).toContainText("Generation 0: 5 live cells.");
  await frame.locator("#preset").selectOption("blinker");
  await expect(frame.locator("#status")).toContainText("Generation 0: 3 live cells.");
  await frame.locator("#ca").focus();
  await page.keyboard.press("Space");
  await expect(frame.locator("#status")).toContainText("4 live cells"); // the cursor starts at the top-left corner: toggled on
  await page.keyboard.press("ArrowRight");
  await page.keyboard.press("Space");
  await expect(frame.locator("#status")).toContainText("5 live cells");
  await frame.locator("#reset").click();
  await expect(frame.locator("#status")).toContainText("Generation 0: 3 live cells.");
  await frame.locator("#step").click();
  await expect(frame.locator("#status")).toContainText("Generation 1: 3 live cells.");
  await frame.locator("#preset").selectOption("r-pentomino");
  await frame.locator("#run").click();
  await expect(frame.locator("#status")).toContainText("Generation 50:");
});

test("ulam-automaton: the Schrandt-Ulam rule counts 1, 5, 9, 13, 25 cells (OEIS A170896)", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "ulam-growth" });
  await waitForMessage(page, "stepChanged", { step: "ulam-growth" });
  await expect(frame.locator("#status")).toContainText("Generation 0: 1 cells on.");
  for (const [g, n] of [[1, 5], [2, 9], [3, 13], [4, 25]]) {
    await frame.locator("#step").click();
    await expect(frame.locator("#status")).toContainText(`Generation ${g}: ${n} cells on.`);
  }
  await expect(frame.locator("#hint")).toContainText("Schrandt-Ulam rule");
  await expect(frame.locator("#hint")).toContainText("A170896");
});

test("ulam-automaton: three different steps visited complete it, once; with reduced motion there is no Play button", async ({ page }) => {
  await suite.open(page, { startStep: "rule30", reducedMotion: "1" });
  await waitForMessage(page, "stepChanged", { step: "rule30" });
  expect(await messages(page, "complete")).toEqual([]);
  await expect(page.frameLocator("iframe").locator("#play")).toBeHidden();
  await page.evaluate(() => window.__host.goToStep("rule90"));
  await waitForMessage(page, "stepChanged", { step: "rule90" });
  expect(await messages(page, "complete")).toEqual([]);
  await page.evaluate(() => window.__host.goToStep("rule110"));
  await waitForMessage(page, "complete");
  await page.evaluate(() => window.__host.goToStep("life"));
  await waitForMessage(page, "stepChanged", { step: "life" });
  expect((await messages(page, "complete")).length).toBe(1);
  // Step still works without Play, and Run to the end draws the finished picture at once
  await page.frameLocator("iframe").locator("#run").click();
  await expect(page.frameLocator("iframe").locator("#status")).toContainText("Generation 50:");
});
