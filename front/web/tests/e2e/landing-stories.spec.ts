import { expect, test, type Page } from "@playwright/test";

/** The merged policy lifecycle and the separate agent-interface showcase. */
const port = process.env.WEB_BASE_PORT ?? "4321";
const url = `http://app.localhost:${port}/`;

async function open(page: Page) {
  await page.goto(url);
  await expect(page.locator("#agents")).toHaveCount(1);
}

test("the policy lifecycle appears once at the top and uses a neutral example caption", async ({ page }, info) => {
  await open(page);
  await expect(page.locator(".u-hero ulams-policy-story")).toHaveCount(1);
  await expect(page.locator(".u-lc, #builder")).toHaveCount(0);
  if (info.project.name !== "phone") await page.locator(".ps-navigation button").last().click();
  await expect(page.locator(".policy-story .u-status")).toHaveCount(0);
  await expect(page.locator(".policy-story figcaption")).toContainText("Illustrative policy lifecycle");
});

test("workflow tabs: roving tabindex, aria wiring, arrow keys, transcripts", async ({ page }) => {
  await open(page);
  const tabs = page.locator('#agents [role="tab"]');
  await expect(tabs).toHaveCount(6);
  await expect(page.locator("#agents ulams-workflows")).toBeVisible();
  for (let i = 0; i < 6; i++) {
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
  await expect(tabs.nth(5)).toHaveAttribute("aria-selected", "true");
  await page.keyboard.press("Home");
  await expect(tabs.first()).toHaveAttribute("aria-selected", "true");
  // the transcript is plain text in the DOM, whatever the animation shows
  const transcript = page.locator("#agents-panel-claude-code ul.u-visually-hidden");
  await expect(transcript).toContainText("ulams builder start --from runbook.md --defaults --json");
  await expect(transcript).toContainText("ulams courses publish");
  await expect(page.locator("#agents-panel-claude-mcp ul.u-visually-hidden")).toContainText("courses_publish");
  await expect(page.locator("#agents-panel-cli ul.u-visually-hidden")).toContainText("ulams apply --file course.yaml --dry-run");
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

test("policy playback advances, pauses and replays without losing chapter selection", async ({ page }, info) => {
  test.skip(info.project.name === "phone", "phones stack the chapters instead of switching them");
  await page.clock.install();
  await open(page);
  const story = page.locator("ulams-policy-story");
  await story.scrollIntoViewIfNeeded();
  await page.waitForFunction(() => customElements.get("ulams-policy-story"));
  await page.clock.fastForward(11000);
  await expect(story.locator('[role="tab"]').nth(1)).toHaveAttribute("aria-selected", "true");
  await story.locator("[data-play]").click();
  await page.clock.fastForward(12000);
  await expect(story.locator('[role="tab"]').nth(1)).toHaveAttribute("aria-selected", "true");
  await story.locator("[data-replay]").click();
  await expect(story.locator('[role="tab"]').first()).toHaveAttribute("aria-selected", "true");
});

test("no layout shift while the stories play", async ({ page }) => {
  await page.addInitScript(() => {
    (window as unknown as { __cls: number }).__cls = 0;
    new PerformanceObserver((list) => {
      for (const e of list.getEntries() as unknown as Array<{ value: number; hadRecentInput: boolean }>) if (!e.hadRecentInput) (window as unknown as { __cls: number }).__cls += e.value;
    }).observe({ type: "layout-shift", buffered: true });
  });
  await open(page);
  // Measure playback after the initial page fonts settle, separately from navigation loading.
  await page.evaluate(async () => {
    await document.fonts.ready;
    (window as unknown as { __cls: number }).__cls = 0;
  });
  for (const id of ["living-course", "agents"]) {
    await page.locator(`#${id}`).scrollIntoViewIfNeeded();
    await page.waitForTimeout(2500);
  }
  const cls = await page.evaluate(() => (window as unknown as { __cls: number }).__cls);
  expect(cls).toBeLessThan(0.05);
});

test.describe("reduced motion", () => {
  test.use({ contextOptions: { reducedMotion: "reduce" } });

  test("the policy stays still, while agent examples show their complete transcript", async ({ page }, info) => {
    await open(page);
    const story = page.locator("ulams-policy-story");
    await expect(story.locator("[data-play]")).toHaveAttribute("aria-pressed", "true");
    await expect(story.locator('[role="tab"]').first()).toHaveAttribute("aria-selected", "true");
    if (info.project.name !== "phone") await story.locator('[role="tab"]').last().click();
    await expect(story.locator(".ps-panel:visible").last()).toContainText("New certificate");
    await page.locator("#agents").scrollIntoViewIfNeeded();
    await expect(page.locator("#agents ulams-story").first()).toHaveAttribute("data-static", "");
    await expect(page.locator("#agents [data-pause]")).toBeHidden();
    for (let i = 0; i < 5; i++) {
      await page.locator('#agents [role="tab"]').nth(i).click();
      await expect(page.locator("#agents .u-wf__panel[data-active] .u-rest")).toHaveText(await page.locator("#agents .u-wf__panel[data-active] .u-rest").evaluateAll((els) => els.map(() => "")));
    }
  });
});

test("interface buttons have equal widths and heights", async ({ page }, info) => {
  test.skip(info.project.name === "phone", "narrow screens scroll a row of label-sized chips");
  await open(page);
  const sizes = await page.locator('#agents [role="tab"]').evaluateAll((els) => els.map((el) => {
    const box = el.getBoundingClientRect();
    return { width: box.width, height: box.height };
  }));
  expect(Math.max(...sizes.map((s) => s.width)) - Math.min(...sizes.map((s) => s.width))).toBeLessThan(1);
  expect(Math.max(...sizes.map((s) => s.height)) - Math.min(...sizes.map((s) => s.height))).toBeLessThan(1);
});

test("interface selector has icons, no redundant availability badges and a visible active state", async ({ page }) => {
  await open(page);
  const selector = page.locator("#agents .u-wf__tabs");
  await expect(selector.locator(".u-status--available")).toHaveCount(0);
  await expect(selector.locator(".u-wf__tab-icon")).toHaveCount(6);
  const tabs = selector.locator('[role="tab"]');
  const inactive = await tabs.nth(2).evaluate((el) => getComputedStyle(el).backgroundColor);
  await tabs.nth(2).click();
  await expect(tabs.nth(2)).toHaveAttribute("aria-selected", "true");
  await page.waitForTimeout(250);
  const active = await tabs.nth(2).evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(active).not.toBe(inactive);
});

test("the interface stage fits the active panel, so no tab leaves empty space", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await open(page);
  const tabs = page.locator('#agents [role="tab"]');
  const heights = new Set<number>();
  for (let i = 0; i < (await tabs.count()); i++) {
    await tabs.nth(i).click();
    const { box, panel } = await page.evaluate(() => ({
      box: document.querySelector("#agents .u-wf__panels")!.getBoundingClientRect().height,
      panel: document.querySelector("#agents .u-wf__panel[data-active]")!.getBoundingClientRect().height,
    }));
    expect(Math.abs(box - panel)).toBeLessThan(1);
    heights.add(Math.round(panel));
  }
  expect(heights.size).toBeGreaterThan(1);
});

test("documentation and GitHub links open in a new tab and say so", async ({ page }) => {
  await open(page);
  const links = page.locator('a[href^="https://ulams-dev.github.io"], a[href^="https://github.com"]');
  expect(await links.count()).toBeGreaterThan(3);
  for (const link of await links.all()) {
    await expect(link).toHaveAttribute("target", "_blank");
    await expect(link).toHaveAttribute("rel", /noopener/);
    await expect(link.locator(".u-newtab")).toHaveCount(1);
  }
  await expect(page.locator('a[href^="/"][target="_blank"]')).toHaveCount(0);
});

test("phones read the policy chapters one under another", async ({ page }, info) => {
  test.skip(info.project.name !== "phone", "wider screens switch chapters in place");
  await open(page);
  const story = page.locator("ulams-policy-story");
  await expect(story.locator(".ps-navigation")).toBeHidden();
  await expect(story.locator("[data-play]")).toBeVisible();
  await expect(story.locator(".ps-panel:visible")).toHaveCount(7);
  const tops = await story.locator(".ps-panel").evaluateAll((els) => els.map((el) => el.getBoundingClientRect().top));
  for (let i = 1; i < tops.length; i++) expect(tops[i]!).toBeGreaterThan(tops[i - 1]!);
  await story.locator(".ps-panel").last().scrollIntoViewIfNeeded();
  await expect(story.locator(".ps-panel").last()).toHaveAttribute("data-inview", "");
});
