import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The diagrams of the content-cost and reporting pages, in English, Polish and Chinese, on desktop and at 360 px
 * (the `phone` project). Checked: axe (WCAG 2.2 AA), no horizontal scroll, the static final state with reduced motion
 * and without JS, the scroll-triggered animation with its pause and replay buttons from the keyboard, and no layout
 * shift while the diagrams play.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const base = `http://app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
const PAGES = [
  { slug: "content-cost", ids: ["dg-benchmark", "dg-pipeline", "dg-living-loop", "dg-audiences"] },
  { slug: "reporting", ids: ["dg-dashboard", "dg-dataflow", "dg-roles"] },
];
const PREFIXES = [
  ["", "en"],
  ["/pl", "pl"],
  ["/zh", "zh-Hans"],
] as const;

/** Every animated part of the diagrams is fully shown and not transformed. */
async function expectFinalState(page: Page) {
  const hidden = await page.evaluate(() =>
    [...document.querySelectorAll<HTMLElement>(".dg [data-dg-step], .dg [data-dg-pop], .dg [data-dg-grow]")]
      .filter((el) => {
        const style = getComputedStyle(el);
        return Number(style.opacity) < 0.5 || (style.transform !== "none" && style.transform !== "matrix(1, 0, 0, 1, 0, 0)");
      })
      .map((el) => el.className || el.tagName),
  );
  expect(hidden).toEqual([]);
}

test.describe("with reduced motion", () => {
  test.use({ contextOptions: { reducedMotion: "reduce" } });

  for (const { slug, ids } of PAGES) {
    for (const [prefix, lang] of PREFIXES) {
      test(`${prefix}/${slug}/: every diagram is shown in its final state and axe finds nothing`, async ({ page }) => {
        await page.goto(`${base}${prefix}/${slug}/`);
        await expect(page.locator("html")).toHaveAttribute("lang", lang);
        await expect(page.locator("main#main")).toHaveCount(1);
        expect(await page.locator("main section.dg").evaluateAll((els) => els.map((el) => el.id))).toEqual(ids);
        for (const id of ids) {
          const figure = page.locator(`#${id} figure`);
          await expect(figure).toBeVisible();
          await expect(figure.locator("h3")).toHaveCount(1);
        }
        // no animation, so no control to show
        await expect(page.locator(".dg [data-dg-control]:visible")).toHaveCount(0);
        await expect(page.locator("html.js-dg")).toHaveCount(0);
        await expectFinalState(page);
        expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
        const axe = await new AxeBuilder({ page }).withTags(TAGS).analyze();
        expect(axe.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
      });
    }
  }
});

test.describe("without JavaScript", () => {
  test.use({ javaScriptEnabled: false });

  test("the diagrams render complete, with the controls hidden", async ({ page }) => {
    for (const { slug } of PAGES) {
      await page.goto(`${base}/${slug}/`);
      await expectFinalState(page);
      await expect(page.locator(".dg [data-dg-control]:visible")).toHaveCount(0);
      await expect(page.locator("#dg-pipeline")).toHaveCount(slug === "content-cost" ? 1 : 0);
    }
  });
});

test.describe("with motion", () => {
  test.use({ contextOptions: { reducedMotion: "no-preference" } });

  test("diagrams play when scrolled into view, without layout shift, and end in the final state", async ({ page }) => {
    await page.addInitScript(() => {
      (window as unknown as { __cls: number }).__cls = 0;
      new PerformanceObserver((list) => {
        for (const entry of list.getEntries() as Array<PerformanceEntry & { value: number; hadRecentInput: boolean }>) {
          if (!entry.hadRecentInput) (window as unknown as { __cls: number }).__cls += entry.value;
        }
      }).observe({ type: "layout-shift", buffered: true });
    });
    await page.goto(`${base}/content-cost/`);
    await expect(page.locator("html.js-dg")).toHaveCount(1);
    const pipeline = page.locator("#dg-pipeline");
    await expect(pipeline).toHaveClass(/is-armed/);
    await expect(pipeline).not.toHaveClass(/is-played/);
    const before = await page.evaluate(() => (window as unknown as { __cls: number }).__cls);
    await pipeline.scrollIntoViewIfNeeded();
    await expect(pipeline).toHaveClass(/is-played/);
    await page.waitForTimeout(3500);
    const shift = (await page.evaluate(() => (window as unknown as { __cls: number }).__cls)) - before;
    expect(shift).toBeLessThan(0.01);
    for (const el of await pipeline.locator("[data-dg-step], [data-dg-grow], [data-dg-pop]").all()) {
      expect(Number(await el.evaluate((node) => getComputedStyle(node).opacity))).toBeGreaterThan(0.5);
    }
  });

  test("the looping Living Course diagram pauses and plays from the keyboard", async ({ page }) => {
    await page.goto(`${base}/content-cost/`);
    const loop = page.locator("#dg-living-loop");
    await loop.scrollIntoViewIfNeeded();
    await expect(loop).toHaveClass(/is-played/);
    const button = loop.locator("[data-dg-control='pause']");
    await expect(button).toBeVisible();
    await expect(button).toHaveText("Pause animation");
    await expect(loop.locator(".dg-loop__pulse").first()).not.toHaveCSS("animation-name", "none");
    await button.focus();
    await page.keyboard.press("Enter");
    await expect(loop).toHaveClass(/is-paused/);
    await expect(button).toHaveText("Play animation");
    await expect(loop.locator(".dg-loop__pulse").first()).toHaveCSS("animation-play-state", "paused");
    await page.keyboard.press("Space");
    await expect(loop).not.toHaveClass(/is-paused/);
    await expect(button).toHaveText("Pause animation");
  });

  test("a one-shot diagram replays from its button and axe stays clean once played", async ({ page }) => {
    await page.goto(`${base}/pl/reporting/`);
    const dash = page.locator("#dg-dashboard");
    await dash.scrollIntoViewIfNeeded();
    await expect(dash).toHaveClass(/is-played/);
    const replay = dash.locator("[data-dg-control='replay']");
    await expect(replay).toBeVisible();
    await expect(replay).toContainText("Odtwórz");
    await replay.focus();
    await page.keyboard.press("Enter");
    await expect(dash).toHaveClass(/is-played/);
    // scroll through the rest so every diagram finishes, then scan
    for (const id of ["dg-dataflow", "dg-roles"]) await page.locator(`#${id}`).scrollIntoViewIfNeeded();
    await page.waitForTimeout(3500);
    await expectFinalState(page);
    const axe = await new AxeBuilder({ page }).withTags(TAGS).analyze();
    expect(axe.violations.map((v) => v.id)).toEqual([]);
  });
});
