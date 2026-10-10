import { expect, test } from "@playwright/test";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { messages, waitForMessage } from "../harness/index.mjs";
import { ulamSuite } from "./ulam-common.mjs";

const dir = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "ulam", "lwow-map");
const steps = ["lwow", "princeton", "harvard", "madison", "los-alamos", "boulder", "santa-fe"];
const suite = ulamSuite({ test, expect }, { name: "ulam-lwow-map", dir, steps });

test("ulam-lwow-map: the map draws the countries, the first stop has the inset, the caption says no historical borders", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "lwow" });
  await waitForMessage(page, "stepChanged", { step: "lwow" });
  expect(await frame.locator("#map path").count()).toBeGreaterThan(150);
  await expect(frame.locator("#banner")).toHaveCount(0);
  await expect(frame.locator("#caption")).toContainText("no historical borders are drawn");
  await expect(frame.locator("#inset-box")).toBeVisible();
  await expect(frame.locator("#inset circle")).toHaveCount(3);
  await expect(frame.locator("#inset")).toContainText("Scottish Café building, 27 Shevchenko Ave.");
  await expect(frame.locator("#inset-box figcaption")).toContainText("not to scale");
  await expect(frame.locator("#leg")).toContainText("where the journey starts");
  await expect(frame.locator("#fx")).toHaveAttribute("aria-label", /Stop 1 of 7: Lwów/);
  expect(await frame.locator("#map path.hi").count()).toBe(1); // Ukraine only
  await page.evaluate(() => window.__host.goToStep("princeton"));
  await waitForMessage(page, "stepChanged", { step: "princeton" });
  await expect(frame.locator("#inset-box")).toBeHidden();
  await expect(frame.locator("#leg")).toContainText("Lwów (today Lviv) to Princeton: about 7,240 km");
  await expect(frame.locator("#fx")).toHaveAttribute("aria-label", /Stop 2 of 7: Princeton/);
});

test("ulam-lwow-map: the stops are a button list: keyboard jumps to a stop, marks it current and respects the range", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "princeton", from: "princeton", to: "los-alamos" });
  await waitForMessage(page, "stepChanged", { step: "princeton" });
  const list = frame.locator("#stops button");
  await expect(list).toHaveCount(7);
  await expect(list.nth(1)).toHaveAttribute("aria-current", "step");
  await expect(list.nth(0)).toBeDisabled(); // before the range
  await expect(list.nth(5)).toBeDisabled(); // after the range
  await list.nth(3).focus();
  await page.keyboard.press("Enter");
  await waitForMessage(page, "stepChanged", { step: "madison" });
  await expect(list.nth(3)).toHaveAttribute("aria-current", "step");
  await expect(list.nth(1)).not.toHaveAttribute("aria-current", "step");
  await expect(frame.locator("#leg")).toContainText("Cambridge, Massachusetts to Madison: about 1,490 km");
  expect(await messages(page, "complete")).toEqual([]); // the topic completes at the end of its range
});

test("ulam-lwow-map: every step's map view shows its stop (the stop's marker is inside the picture)", async ({ page }) => {
  const frame = await suite.open(page, { startStep: "lwow", reducedMotion: "1" });
  await waitForMessage(page, "stepChanged", { step: "lwow" });
  for (const id of steps) {
    await page.evaluate((s) => window.__host.goToStep(s), id);
    await waitForMessage(page, "stepChanged", { step: id });
    const camera = await frame.evaluate(() => document.querySelector("#map g")?.getAttribute("transform") ?? "");
    expect(camera, id).toMatch(/translate\(.*\) scale\(/);
    expect(await frame.locator("#stops button[aria-current='step']").getAttribute("data-id")).toBe(id);
  }
});
