import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The white-label section of the platform landing: structure, axe (WCAG 2.2 AA) on desktop and 360 px,
 * no layout shift while it plays, the final frame of every step under reduced motion (screenshots in
 * tests/screens/whitelabel-*.png). Needs the server on :4321, like the other platform tests.
 */
const port = process.env.WEB_BASE_PORT ?? "4321";
const url = `http://app.localhost:${port}/`;
const open = async (page: Page) => {
  await page.goto(url);
  await expect(page.locator("#white-label")).toHaveCount(1);
};

test("sits after the stories and before the comparison, with the list and the calls to action", async ({ page }) => {
  await open(page);
  const ids = await page.locator("main > section[id]").evaluateAll((els) => els.map((e) => e.id));
  expect(ids.indexOf("agents")).toBeLessThan(ids.indexOf("white-label"));
  expect(ids.indexOf("white-label")).toBeLessThan(ids.indexOf("compare"));
  await expect(page.locator("#white-label .u-wl__item")).toHaveCount(4);
  await expect(page.locator("#white-label .u-wl__item h3")).toHaveText(["Bring your brand", "Get your academy", "Bring your content", "See everything"]);
  await expect(page.locator("#white-label .u-wl__cta a").first()).toHaveAttribute("href", "#demos");
  await expect(page.locator("#white-label .u-wl__cta a").nth(1)).toHaveAttribute("href", "#self-host");
  // final mode (the default): no roadmap labels
  await expect(page.locator("#white-label .u-status--coming, #white-label .u-wl__tag, #white-label .u-wl__today")).toHaveCount(0);
  await expect(page.locator("#white-label ulams-story .u-visually-hidden").first()).toContainText("acme.ulams.app");
});

test("axe finds no WCAG 2.2 AA violations, while it plays and at the end", async ({ page }) => {
  await open(page);
  await page.locator("#white-label").scrollIntoViewIfNeeded();
  for (const wait of [1500, 9000]) {
    await page.waitForTimeout(wait);
    const r = await new AxeBuilder({ page }).include("#white-label").withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"]).analyze();
    expect(r.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target).join(" | ")}`)).toEqual([]);
  }
});

test("the scenes follow the rail and nothing shifts", async ({ page }) => {
  await page.addInitScript(() => {
    (window as unknown as { __cls: number }).__cls = 0;
    new PerformanceObserver((list) => {
      for (const e of list.getEntries() as unknown as Array<{ value: number; hadRecentInput: boolean }>) if (!e.hadRecentInput) (window as unknown as { __cls: number }).__cls += e.value;
    }).observe({ type: "layout-shift", buffered: true });
  });
  await open(page);
  await page.locator("#white-label ulams-story").scrollIntoViewIfNeeded();
  const scenes = page.locator("#white-label .u-wl__scene");
  await expect(scenes.nth(0)).toHaveClass(/\bon\b/);
  await expect(scenes.nth(1)).toHaveClass(/\bon\b/, { timeout: 8000 });
  await expect(scenes.nth(3)).toHaveClass(/\bon\b/, { timeout: 16000 });
  const h = await page.locator("#white-label .u-wl__scenes").boundingBox();
  await page.waitForTimeout(1500);
  expect((await page.locator("#white-label .u-wl__scenes").boundingBox())?.height).toBe(h?.height);
  const cls = await page.evaluate(() => (window as unknown as { __cls: number }).__cls);
  expect(cls).toBeLessThan(0.05);
  await page.locator("#white-label [data-pause]").click();
  await expect(page.locator("#white-label [data-pause]")).toHaveAttribute("aria-pressed", "true");
});

test.describe("reduced motion", () => {
  test.use({ contextOptions: { reducedMotion: "reduce" } });

  test("stacks the four scenes in their final state and takes the screenshots", async ({ page }, info) => {
    await open(page);
    await page.locator("#white-label").scrollIntoViewIfNeeded();
    await expect(page.locator("#white-label ulams-story")).toHaveAttribute("data-static", "");
    const scenes = page.locator("#white-label .u-wl__scene");
    await expect(scenes).toHaveCount(4);
    for (let i = 0; i < 4; i++) await expect(scenes.nth(i)).toBeVisible();
    await expect(page.locator("#white-label .u-wl__rail")).toBeHidden();
    expect((await page.locator("#white-label .u-rest").allTextContents()).join("")).toBe("");
    await expect(page.locator("#white-label .u-wl__risk").first()).toHaveClass(/\bon\b/);
    await expect(page.locator("#white-label .u-wl__cmds code").last()).toHaveClass(/\bon\b/);
    const r = await new AxeBuilder({ page }).include("#white-label").withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"]).analyze();
    expect(r.violations).toEqual([]);
    const dir = `tests/screens/whitelabel-${info.project.name}`;
    for (let i = 0; i < 4; i++) await scenes.nth(i).screenshot({ path: `${dir}-step${i + 1}.png` });
    await page.locator("#white-label").screenshot({ path: `${dir}-section.png` });
  });
});
