import { expect, test, type Page } from "@playwright/test";

/**
 * The three animated stories on the platform landing (Living Course update, course builder,
 * "run it from anywhere" tabs): structure, keyboard tabs, transcripts, reduced motion, layout shift,
 * and the final frame of each story as a screenshot under tests/screens/ for review.
 * Needs the API for the demo cards of the page, like the other platform tests.
 */
const port = process.env.WEB_BASE_PORT ?? "4321";
const url = `http://app.localhost:${port}/`;

async function open(page: Page) {
  await page.goto(url);
  await expect(page.locator("#agents")).toHaveCount(1);
}

test("stories are on the landing, in order, and the final mode shows no roadmap labels", async ({ page }) => {
  await open(page);
  const ids = await page.locator("main > section[id]").evaluateAll((els) => els.map((e) => e.id));
  expect(ids.indexOf("living")).toBeGreaterThanOrEqual(0);
  expect(ids.indexOf("living")).toBeLessThan(ids.indexOf("builder"));
  expect(ids.indexOf("builder")).toBeLessThan(ids.indexOf("agents"));
  expect(ids.indexOf("agents")).toBeLessThan(ids.indexOf("product"));
  await expect(page.locator("#agents .u-status--coming")).toHaveCount(0);
  await expect(page.locator("#living .u-status--coming")).toHaveCount(0);
  await expect(page.locator("#agents")).not.toContainText("planned interface");
  for (const id of ["living", "builder", "agents", "product", "how", "compare-intro"]) {
    if (await page.locator(`#${id}`).count()) await expect(page.locator(`#${id}`)).not.toContainText(/roadmap|coming soon/i);
  }
  await expect(page.locator(".u-status--coming")).toHaveCount(0);
});

test("workflow tabs: roving tabindex, aria wiring, arrow keys, transcripts", async ({ page }) => {
  await open(page);
  const tabs = page.locator('#agents [role="tab"]');
  await expect(tabs).toHaveCount(5);
  await expect(page.locator("#agents ulams-workflows")).toBeVisible();
  for (let i = 0; i < 5; i++) {
    const controls = await tabs.nth(i).getAttribute("aria-controls");
    await expect(page.locator(`#${controls}[role="tabpanel"]`)).toHaveCount(1);
  }
  await expect(tabs.first()).toHaveAttribute("aria-selected", "true");
  await expect(tabs.first()).toHaveAttribute("tabindex", "0");
  await expect(tabs.nth(1)).toHaveAttribute("tabindex", "-1");
  await tabs.first().focus();
  await page.keyboard.press("ArrowRight");
  await expect(tabs.nth(1)).toHaveAttribute("aria-selected", "true");
  await expect(tabs.nth(1)).toBeFocused();
  await page.keyboard.press("End");
  await expect(tabs.nth(4)).toHaveAttribute("aria-selected", "true");
  await page.keyboard.press("Home");
  await expect(tabs.first()).toHaveAttribute("aria-selected", "true");
  // the transcript is plain text in the DOM, whatever the animation shows
  const transcript = page.locator("#agents-panel-claude-code ul.u-visually-hidden");
  await expect(transcript).toContainText("ulams builder start --source runbook.md --tenant oncall --json");
  await expect(transcript).toContainText("ulams courses publish");
  await expect(page.locator("#agents-panel-claude-mcp ul.u-visually-hidden")).toContainText("ulams.analytics.at_risk");
  await expect(page.locator("#agents-panel-cli ul.u-visually-hidden")).toContainText("ulams courses push course/ --dry-run");
  await expect(page.locator("#agents-panel-api ul.u-visually-hidden")).toContainText("/api/admin/topics");
});

test("tabs auto-advance slowly and Pause stops them", async ({ page }) => {
  await open(page);
  await page.locator("#agents").scrollIntoViewIfNeeded();
  await page.locator("#agents [data-pause]").click();
  await expect(page.locator("#agents [data-pause]")).toHaveAttribute("aria-pressed", "true");
  await page.mouse.move(0, 0);
  await page.waitForTimeout(1500);
  await expect(page.locator('#agents [role="tab"]').first()).toHaveAttribute("aria-selected", "true");
});

test("typing is in progress, then reaches the whole text", async ({ page }) => {
  await open(page);
  await page.locator("#living").scrollIntoViewIfNeeded();
  const added = page.locator("#living .u-lc__added span[data-at]");
  await expect(added).toHaveClass(/on/, { timeout: 8000 });
  await expect(added).toContainText("Budget over 30 days: 21.6 minutes", { timeout: 8000 });
});

test("no layout shift while the stories play", async ({ page }) => {
  await page.addInitScript(() => {
    (window as unknown as { __cls: number }).__cls = 0;
    new PerformanceObserver((list) => {
      for (const e of list.getEntries() as unknown as Array<{ value: number; hadRecentInput: boolean }>) if (!e.hadRecentInput) (window as unknown as { __cls: number }).__cls += e.value;
    }).observe({ type: "layout-shift", buffered: true });
  });
  await open(page);
  for (const id of ["living", "builder", "agents"]) {
    await page.locator(`#${id}`).scrollIntoViewIfNeeded();
    await page.waitForTimeout(2500);
  }
  const cls = await page.evaluate(() => (window as unknown as { __cls: number }).__cls);
  expect(cls).toBeLessThan(0.05);
});

test.describe("reduced motion", () => {
  test.use({ contextOptions: { reducedMotion: "reduce" } });

  test("shows the final frame of every story without typing, and the captions", async ({ page }, info) => {
    await open(page);
    for (const id of ["living", "builder", "agents"]) {
      await page.locator(`#${id}`).scrollIntoViewIfNeeded();
      await expect(page.locator(`#${id} ulams-story`).first()).toHaveAttribute("data-static", "");
    }
    // no half-typed text: nothing is waiting to be typed
    await expect(page.locator("#living .u-rest")).toHaveText(["", ""]);
    await expect(page.locator("#living .u-lc__done")).toHaveClass(/on/);
    await expect(page.locator("#living .u-lc__new2")).toHaveClass(/on/);
    await expect(page.locator("#builder .u-bs__pub")).toHaveClass(/on/);
    await expect(page.locator("#builder .u-bs__cost b")).toHaveText("$0.18");
    for (const caption of ["Your source changes", "ulams finds every lesson that cites it", "You approve, learners keep their progress"]) {
      await expect(page.locator("#living")).toContainText(caption);
    }
    await expect(page.locator("#agents [data-pause]")).toBeHidden();
    // screenshots of each story's final frame
    const dir = `tests/screens/${info.project.name}`;
    await page.locator("#living ulams-story").screenshot({ path: `${dir}-living.png` });
    await page.locator("#builder ulams-story").screenshot({ path: `${dir}-builder.png` });
    const keys = ["claude-code", "claude-mcp", "cli", "api", "studio"];
    for (let i = 0; i < keys.length; i++) {
      await page.locator('#agents [role="tab"]').nth(i).click();
      await page.locator("#agents .u-wf__stage").screenshot({ path: `${dir}-agents-${keys[i]}.png` });
    }
  });
});
